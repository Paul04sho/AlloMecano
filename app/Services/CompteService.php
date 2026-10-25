<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Les comptes vus par l'administration (A3) : liste et fiche d'un mécanicien, suspension et levée (RG-D6, RG-DO8), suppression (RG-P5),
 * numéros des clients (RG-C5 : chaque affichage est journalisé). La suspension retire la fiche des résultats tout de suite (RG-R3 ne retient que « validé »).
 */
final class CompteService
{
    public const PAR_PAGE = 8;
    /** Durées proposées (Figma 178:675) : code → [libellé, secondes ou nul = jusqu'à nouvel ordre, « suspendu … » dans le message]. */
    public const DUREES = ['24h' => ['24 h', 86400, 'pendant 24 h'], '7j' => ['7 jours', 604800, 'pendant 7 jours'], 'indefinie' => ['Jusqu’à nouvel ordre', null, 'jusqu’à nouvel ordre']];

    public function __construct(private readonly Database $db, private readonly ?MailService $mail = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    public static function numero(int $id): string
    {
        return sprintf('MEC-%04d', $id);
    }

    /** @return array{mecaniciens: int, clients: int} « 214 MÉCANICIENS · 1 862 NUMÉROS CLIENTS » */
    public function decompte(): array
    {
        return [
            'mecaniciens' => (int) $this->db->valeur('SELECT COUNT(*) FROM mecaniciens WHERE supprime_le IS NULL AND statut_dossier <> \'brouillon\''),
            'clients' => (int) $this->db->valeur('SELECT COUNT(*) FROM clients WHERE telephone IS NOT NULL'),
        ];
    }

    /**
     * Une page de mécaniciens, la dernière activité d'abord (Figma 175:372).
     *
     * @return array{lignes: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function mecaniciens(string $recherche = '', int $page = 1, ?\DateTimeImmutable $maintenant = null): array
    {
        $this->leverEchues($maintenant);
        $conditions = ['m.supprime_le IS NULL', 'm.statut_dossier <> \'brouillon\''];
        $params = [];
        $recherche = trim($recherche);
        if ($recherche !== '') {
            if (preg_match('/^MEC-(\d+)$/i', $recherche, $n) === 1) {
                $conditions[] = 'm.id = :numero';
                $params['numero'] = (int) $n[1];
            } else {
                $chiffres = preg_replace('/\D/', '', $recherche) ?? '';
                $ou = ['CONCAT(m.prenom, \' \', m.nom) LIKE :nom1', 'CONCAT(m.nom, \' \', m.prenom) LIKE :nom2'];
                $params['nom1'] = $params['nom2'] = '%' . addcslashes($recherche, '%_\\') . '%';
                if (strlen($chiffres) >= 4) {
                    $ou[] = 'm.telephone LIKE :tel';
                    $params['tel'] = '%' . $chiffres . '%';
                }
                $conditions[] = '(' . implode(' OR ', $ou) . ')';
            }
        }
        $ou = implode(' AND ', $conditions);
        $total = (int) $this->db->valeur("SELECT COUNT(*) FROM mecaniciens m WHERE $ou", $params);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        $page = min(max(1, $page), $pages);
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $params['depuis'] = $maintenant->modify('-30 days')->format('Y-m-d H:i:s');
        $lignes = $this->db->tous(
            "SELECT m.id, m.public_id, m.prenom, m.nom, m.statut_dossier, m.note_moyenne, m.nb_avis, m.identite_verifiee_le, c.nom AS commune,
                    COALESCE(m.vu_le, m.etat_depuis, m.modifie_le, m.cree_le) AS activite,
                    (SELECT COUNT(*) FROM contacts k WHERE k.mecanicien_id = m.id AND k.cree_le >= :depuis) AS appels,
                    (SELECT COUNT(*) FROM signalements s WHERE s.mecanicien_id = m.id) AS signalements,
                    ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y,
                    (SELECT COUNT(*) FROM pieces_dossier pp WHERE pp.mecanicien_id = m.id AND pp.type_piece = 'portrait' AND pp.fichier IS NOT NULL AND pp.fichier_supprime_le IS NULL AND pp.statut <> 'remplacee') AS portrait_piece
               FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id LEFT JOIN communes c ON c.id = q.commune_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = 'portrait' AND ph.active = 1
              WHERE $ou ORDER BY activite DESC, m.id DESC LIMIT " . self::PAR_PAGE . ' OFFSET ' . (($page - 1) * self::PAR_PAGE),
            $params
        );
        return ['lignes' => array_map(fn (array $l): array => $this->ligne($l, $maintenant), $lignes), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @param array<string, mixed> $l @return array<string, mixed> */
    private function ligne(array $l, \DateTimeImmutable $maintenant): array
    {
        return [
            'id' => (int) $l['id'], 'public_id' => (string) $l['public_id'], 'nom_complet' => $l['prenom'] . ' ' . $l['nom'], 'commune' => (string) ($l['commune'] ?? ''),
            'statut' => (string) $l['statut_dossier'], 'note' => $l['note_moyenne'] !== null ? (float) $l['note_moyenne'] : null, 'appels' => (int) $l['appels'], 'signalements' => (int) $l['signalements'],
            'activite' => self::activite((string) $l['activite'], $maintenant), 'numero' => self::numero((int) $l['id']),
            'photo' => PhotoService::portrait($l), 'portrait_piece' => (int) $l['portrait_piece'] > 0,
        ];
    }

    /** « il y a 12 min », « il y a 3 h », « hier, 19:40 », « 18 octobre » : la dernière activité en une ligne (Figma 175:412 à 175:574). */
    public static function activite(string $date, \DateTimeImmutable $maintenant): string
    {
        $utc = new \DateTimeZone('UTC');
        $d = new \DateTimeImmutable($date, $utc);
        $s = $maintenant->getTimestamp() - $d->getTimestamp();
        if ($s < 60) {
            return 'à l’instant';
        }
        if ($s < 3600) {
            return 'il y a ' . intdiv($s, 60) . Format::INSECABLE . 'min';
        }
        if ($s < 86400 && $d->format('Y-m-d') === $maintenant->format('Y-m-d')) {
            return 'il y a ' . intdiv($s, 3600) . Format::INSECABLE . 'h';
        }
        if ($d->format('Y-m-d') === $maintenant->modify('-1 day')->format('Y-m-d')) {
            return 'hier, ' . Format::heure($d);
        }
        $jour = trim((string) preg_replace('/\s\d{4}$/', '', Format::date($d)));
        return $d->format('Y') === $maintenant->format('Y') ? $jour : Format::date($d);
    }

    /**
     * Le tiroir d'un compte (Figma 175:580) : identité, chiffres, historique complet.
     *
     * @return ?array<string, mixed>
     */
    public function fiche(string $publicId, ?\DateTimeImmutable $maintenant = null): ?array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.prenom, m.nom, m.telephone, m.statut_dossier, m.note_moyenne, m.nb_avis, m.identite_verifiee_le, m.cree_le, m.dossier_envoye_le, c.nom AS commune,
                    ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id LEFT JOIN communes c ON c.id = q.commune_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE m.public_id = :p AND m.supprime_le IS NULL',
            ['p' => $publicId]
        );
        if ($m === null) {
            return null;
        }
        $id = (int) $m['id'];
        $specs = array_column($this->db->tous('SELECT s.libelle FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id WHERE ms.mecanicien_id = :m ORDER BY s.ordre', ['m' => $id]), 'libelle');
        $appels = (int) $this->db->valeur('SELECT COUNT(*) FROM contacts WHERE mecanicien_id = :m AND cree_le >= :d', ['m' => $id, 'd' => $maintenant->modify('-30 days')->format('Y-m-d H:i:s')]);
        $signalements = (int) $this->db->valeur('SELECT COUNT(*) FROM signalements WHERE mecanicien_id = :m', ['m' => $id]);
        $suspension = $this->db->un('SELECT id, motif, debut, fin_prevue FROM suspensions WHERE mecanicien_id = :m AND levee_le IS NULL ORDER BY id DESC LIMIT 1', ['m' => $id]);
        $historique = $this->historique($id, $m, $maintenant);
        $suspensions = count(array_filter($historique, static fn (array $e): bool => $e['type'] === 'suspension'));
        return [
            'id' => $id, 'public_id' => (string) $m['public_id'], 'numero' => self::numero($id), 'inscrit_le' => new \DateTimeImmutable((string) $m['cree_le'], new \DateTimeZone('UTC')),
            'nom_complet' => $m['prenom'] . ' ' . $m['nom'], 'prenom' => (string) $m['prenom'], 'telephone' => (string) $m['telephone'], 'statut' => (string) $m['statut_dossier'],
            'commune' => (string) ($m['commune'] ?? ''), 'specialites' => $specs, 'note' => $m['note_moyenne'] !== null ? (float) $m['note_moyenne'] : null, 'nb_avis' => (int) $m['nb_avis'],
            'verifie_le' => $m['identite_verifiee_le'], 'appels' => $appels, 'signalements' => $signalements, 'suspension' => $suspension, 'suspensions' => $suspensions, 'historique' => $historique,
            'photo' => PhotoService::portrait($m),
        ];
    }

    /**
     * Tout ce qui est arrivé à ce compte, le plus récent d'abord : dossier envoyé, validé ou refusé, signalements et suites, suspensions et levées.
     *
     * @param array<string, mixed> $m
     * @return list<array{le: \DateTimeImmutable, type: string, icone: string, ref: ?string, texte: string}>
     */
    private function historique(int $id, array $m, \DateTimeImmutable $maintenant): array
    {
        $utc = new \DateTimeZone('UTC');
        $e = [];
        $ajout = static function (string $le, string $type, string $icone, string $texte, ?string $ref = null) use (&$e, $utc): void {
            $e[] = ['le' => new \DateTimeImmutable($le, $utc), 'type' => $type, 'icone' => $icone, 'ref' => $ref, 'texte' => $texte];
        };
        if ($m['dossier_envoye_le'] !== null) {
            $ajout((string) $m['dossier_envoye_le'], 'dossier', 'fichier', 'Dossier ' . DossierService::numero($id, (string) $m['dossier_envoye_le']) . ' envoyé.');
        }
        foreach ($this->db->tous('SELECT h.vers_statut, h.motif, h.cree_le, a.prenom, a.nom FROM historique_dossier h LEFT JOIN admins a ON a.id = h.admin_id WHERE h.mecanicien_id = :m ORDER BY h.id', ['m' => $id]) as $h) {
            $qui = $h['prenom'] !== null ? mb_substr((string) $h['prenom'], 0, 1) . '. ' . $h['nom'] : null;
            $texte = match ($h['vers_statut']) {
                'valide' => 'Dossier validé par ' . $qui . ' : 5 pièces conformes.',
                'a_reprendre' => 'Dossier renvoyé pour correction par ' . $qui . ($h['motif'] ? ' · ' . $h['motif'] : '') . '.',
                'refuse' => 'Dossier refusé par ' . $qui . ($h['motif'] ? ' · ' . $h['motif'] : '') . '.',
                'suspendu' => null,
                default => null,
            };
            if ($texte !== null && $qui !== null) {
                $ajout((string) $h['cree_le'], 'dossier', $h['vers_statut'] === 'valide' ? 'coche' : 'fichier', $texte);
            }
        }
        foreach ($this->db->tous('SELECT s.public_id, s.motif, s.description, s.statut, s.decision, s.cree_le, a.prenom, a.nom FROM signalements s LEFT JOIN admins a ON a.id = s.traite_par_admin_id WHERE s.mecanicien_id = :m ORDER BY s.id', ['m' => $id]) as $s) {
            $qui = $s['prenom'] !== null ? 'M. ' . $s['nom'] : null;
            $cite = '« ' . ($s['description'] !== null ? mb_substr((string) $s['description'], 0, 60) : (SignalementService::MOTIFS[$s['motif']] ?? '')) . ' »';
            $suite = match ($s['statut']) {
                'sans_suite' => 'classé sans suite' . ($qui ? ' par ' . $qui : ''),
                'sanction' => 'sanction décidée' . ($qui ? ' par ' . $qui : ''),
                'en_cours' => 'en cours de traitement',
                default => 'en attente de lecture',
            };
            $ajout((string) $s['cree_le'], 'signalement', 'drapeau', $cite . ' : ' . $suite . '.', 'SIG-' . substr((string) $s['cree_le'], 0, 4) . '-' . sprintf('%04d', (int) $this->db->valeur('SELECT id FROM signalements WHERE public_id = :p', ['p' => $s['public_id']])));
        }
        foreach ($this->db->tous('SELECT su.motif, su.debut, su.fin_prevue, su.levee_le, a.prenom, a.nom FROM suspensions su LEFT JOIN admins a ON a.id = su.admin_id WHERE su.mecanicien_id = :m ORDER BY su.id', ['m' => $id]) as $su) {
            $qui = mb_substr((string) $su['prenom'], 0, 1) . '. ' . $su['nom'];
            $ajout((string) $su['debut'], 'suspension', 'cadenas', 'Compte suspendu par ' . $qui . ' · ' . rtrim((string) $su['motif'], '. ') . ($su['fin_prevue'] !== null ? '' : ' (jusqu’à nouvel ordre)') . '.');
            if ($su['levee_le'] !== null) {
                $ajout((string) $su['levee_le'], 'suspension_levee', 'coche', 'Suspension levée.');
            }
        }
        usort($e, static fn (array $a, array $b): int => $b['le'] <=> $a['le']);
        return $e;
    }

    /** « Bonjour Adama, votre compte allomécano est suspendu jusqu’à nouvel ordre. Motif : … » : l'aperçu exact (Figma 178:689). */
    public static function messageSuspension(string $prenom, string $motif, string $duree): string
    {
        $motif = trim($motif);
        $motif = $motif === '' ? '…' : mb_strtolower(mb_substr($motif, 0, 1), 'UTF-8') . mb_substr($motif, 1);
        return 'Bonjour ' . $prenom . ', votre compte allomécano est suspendu ' . (self::DUREES[$duree][2] ?? self::DUREES['indefinie'][2]) . '. Motif : ' . rtrim($motif, '.') . '. Votre fiche revient dès que la suspension est levée. Vos avis sont conservés.';
    }

    /**
     * Suspendre un compte validé (RG-D6) : indisponible tout de suite, fiche hors des résultats, mécano prévenu.
     *
     * @return array{ok: true}|array{ok: false, erreur: string}
     */
    public function suspendre(int $admin, string $publicId, string $motif, string $duree, string $ip, ?\DateTimeImmutable $maintenant = null): array
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            return ['ok' => false, 'erreur' => 'Indiquez le motif de la suspension (500 caractères au plus) : il est cité tel quel au mécanicien.'];
        }
        if (!isset(self::DUREES[$duree])) {
            return ['ok' => false, 'erreur' => 'Choisissez la durée de la suspension.'];
        }
        $m = $this->db->un('SELECT id, prenom, email, statut_dossier FROM mecaniciens WHERE public_id = :p AND supprime_le IS NULL', ['p' => $publicId]);
        if ($m === null) {
            return ['ok' => false, 'erreur' => 'Ce compte est introuvable.'];
        }
        if ($m['statut_dossier'] === 'suspendu') {
            return ['ok' => false, 'erreur' => 'Ce compte est déjà suspendu.'];
        }
        if ($m['statut_dossier'] !== 'valide') {
            return ['ok' => false, 'erreur' => 'Seul un compte dont le dossier est validé peut être suspendu.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $secondes = self::DUREES[$duree][1];
        $id = (int) $m['id'];
        $this->db->transaction(function () use ($admin, $id, $m, $motif, $duree, $secondes, $maintenant, $ip): void {
            $this->db->inserer('suspensions', ['mecanicien_id' => $id, 'admin_id' => $admin, 'motif' => $motif, 'debut' => $maintenant->format('Y-m-d H:i:s'),
                'fin_prevue' => $secondes !== null ? $maintenant->modify("+$secondes seconds")->format('Y-m-d H:i:s') : null]);
            $this->db->modifier('mecaniciens', ['statut_dossier' => 'suspendu', 'etat' => 'indisponible', 'etat_depuis' => $maintenant->format('Y-m-d H:i:s')], ['id' => $id]);
            $this->db->inserer('historique_dossier', ['mecanicien_id' => $id, 'de_statut' => 'valide', 'vers_statut' => 'suspendu', 'admin_id' => $admin, 'motif' => mb_substr($motif, 0, 500), 'cree_le' => $maintenant->format('Y-m-d H:i:s')]);
            $this->db->inserer('historique_disponibilite', ['mecanicien_id' => $id, 'etat' => 'indisponible', 'raison' => 'suspension', 'debut' => $maintenant->format('Y-m-d H:i:s')]);
            JournalAdminService::depuisBase()->noter($admin, 'suspendre_compte', 'mecanicien', $id, ['motif' => $motif, 'duree' => $duree], $ip);
            $this->prevenir($id, $m['email'], 'suspension', 'Votre compte est suspendu', self::messageSuspension((string) $m['prenom'], $motif, $duree));
        });
        return ['ok' => true];
    }

    /** Lever une suspension : le compte redevient « validé » (indisponible : le mécano se remet disponible d'un geste). */
    public function lever(int $admin, string $publicId, string $ip, ?\DateTimeImmutable $maintenant = null): array
    {
        $m = $this->db->un('SELECT id, prenom, email, statut_dossier FROM mecaniciens WHERE public_id = :p AND supprime_le IS NULL', ['p' => $publicId]);
        if ($m === null || $m['statut_dossier'] !== 'suspendu') {
            return ['ok' => false, 'erreur' => 'Ce compte n’est pas suspendu.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->db->transaction(function () use ($admin, $m, $maintenant, $ip): void {
            $this->rendre((int) $m['id'], $maintenant, $admin);
            JournalAdminService::depuisBase()->noter($admin, 'lever_suspension', 'mecanicien', (int) $m['id'], [], $ip);
            $this->prevenir((int) $m['id'], $m['email'], 'suspension', 'Votre compte est de nouveau actif', 'Bonjour ' . $m['prenom'] . ', la suspension de votre compte allomécano est levée. Vous pouvez vous rendre disponible depuis votre espace.');
        });
        return ['ok' => true];
    }

    /** Lève les suspensions dont la durée est écoulée (24 h, 7 jours). Lancé à chaque ouverture de la liste et chaque heure par la purge. @return int comptes rendus */
    public function leverEchues(?\DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $n = 0;
        foreach ($this->db->tous('SELECT su.mecanicien_id AS id FROM suspensions su JOIN mecaniciens m ON m.id = su.mecanicien_id
            WHERE su.levee_le IS NULL AND su.fin_prevue IS NOT NULL AND su.fin_prevue <= :n AND m.statut_dossier = \'suspendu\'', ['n' => $maintenant->format('Y-m-d H:i:s')]) as $l) {
            $this->rendre((int) $l['id'], $maintenant, null);
            $n++;
        }
        return $n;
    }

    private function rendre(int $mecano, \DateTimeImmutable $maintenant, ?int $admin): void
    {
        $this->db->executer('UPDATE suspensions SET levee_le = :n, levee_par_admin_id = :a WHERE mecanicien_id = :m AND levee_le IS NULL', ['n' => $maintenant->format('Y-m-d H:i:s'), 'a' => $admin, 'm' => $mecano]);
        $this->db->modifier('mecaniciens', ['statut_dossier' => 'valide'], ['id' => $mecano]);
        $this->db->inserer('historique_dossier', ['mecanicien_id' => $mecano, 'de_statut' => 'suspendu', 'vers_statut' => 'valide', 'admin_id' => $admin, 'motif' => 'Suspension levée', 'cree_le' => $maintenant->format('Y-m-d H:i:s')]);
    }

    /**
     * Supprimer un compte (RG-P5) : les données personnelles sont anonymisées, les pièces effacées tout de suite, la fiche disparaît. Les avis et les contacts restent, sans nom.
     *
     * @return array{ok: bool, erreur?: string}
     */
    public function supprimer(int $admin, string $publicId, string $ip): array
    {
        $m = $this->db->un('SELECT id FROM mecaniciens WHERE public_id = :p AND supprime_le IS NULL', ['p' => $publicId]);
        if ($m === null) {
            return ['ok' => false, 'erreur' => 'Ce compte est introuvable.'];
        }
        $id = (int) $m['id'];
        $fichiers = array_column($this->db->tous('SELECT fichier FROM pieces_dossier WHERE mecanicien_id = :m AND fichier IS NOT NULL AND fichier_supprime_le IS NULL', ['m' => $id]), 'fichier');
        $this->db->transaction(function () use ($admin, $id, $ip): void {
            $maintenant = gmdate('Y-m-d H:i:s');
            $this->db->modifier('mecaniciens', [
                'prenom' => 'Compte', 'nom' => 'supprimé', 'telephone' => '+000' . str_pad((string) $id, 10, '0', STR_PAD_LEFT), 'whatsapp' => null, 'email' => null, 'google_sub' => null,
                'mot_de_passe_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'presentation' => null, 'slug' => null, 'etat' => 'indisponible', 'statut_dossier' => 'refuse',
                'dispo_lat' => null, 'dispo_lon' => null, 'supprime_le' => $maintenant, 'date_naissance' => null,
            ], ['id' => $id]);
            $this->db->executer('UPDATE pieces_dossier SET fichier = NULL, fichier_supprime_le = :n WHERE mecanicien_id = :m', ['n' => $maintenant, 'm' => $id]);
            $this->db->executer('UPDATE photos SET active = 0 WHERE mecanicien_id = :m', ['m' => $id]);
            $this->db->executer('DELETE FROM abonnements_push WHERE mecanicien_id = :m', ['m' => $id]);
            JournalAdminService::depuisBase()->noter($admin, 'supprimer_compte', 'mecanicien', $id, [], $ip);
        });
        $racine = realpath(dirname(__DIR__, 2) . '/storage/pieces');
        foreach ($fichiers as $f) { // les fichiers eux-mêmes, hors transaction : ce qui ne peut pas être effacé le sera par la purge
            $chemin = $racine === false ? false : realpath($racine . DIRECTORY_SEPARATOR . $f);
            if ($chemin !== false && str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR)) {
                @unlink($chemin);
            }
        }
        return ['ok' => true];
    }

    // ---------- Numéros des clients (RG-C5) ----------

    /** @return array{lignes: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function clients(string $recherche = '', int $page = 1, ?\DateTimeImmutable $maintenant = null): array
    {
        $params = [];
        $ou = 'c.telephone IS NOT NULL';
        $chiffres = preg_replace('/\D/', '', trim($recherche)) ?? '';
        if (trim($recherche) !== '') {
            $ou .= ' AND (c.prenom LIKE :p' . (strlen($chiffres) >= 4 ? ' OR c.telephone LIKE :t' : '') . ')';
            $params['p'] = '%' . addcslashes(trim($recherche), '%_\\') . '%';
            if (strlen($chiffres) >= 4) {
                $params['t'] = '%' . $chiffres . '%';
            }
        }
        $total = (int) $this->db->valeur("SELECT COUNT(*) FROM clients c WHERE $ou", $params);
        $pages = max(1, (int) ceil($total / self::PAR_PAGE));
        $page = min(max(1, $page), $pages);
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $lignes = $this->db->tous(
            "SELECT c.id, c.jeton_hash, c.prenom, c.telephone, c.vu_le, (SELECT COUNT(*) FROM contacts k WHERE k.client_id = c.id) AS contacts
               FROM clients c WHERE $ou ORDER BY c.vu_le DESC, c.id DESC LIMIT " . self::PAR_PAGE . ' OFFSET ' . (($page - 1) * self::PAR_PAGE),
            $params
        );
        return ['total' => $total, 'page' => $page, 'pages' => $pages, 'lignes' => array_map(static fn (array $l): array => [
            'ref' => substr((string) $l['jeton_hash'], 0, 16), 'prenom' => (string) ($l['prenom'] ?? '—'), 'masque' => self::masquer((string) $l['telephone']), 'contacts' => (int) $l['contacts'],
            'activite' => self::activite((string) $l['vu_le'], $maintenant),
        ], $lignes)];
    }

    /** « +225 07 •• •• 56 78 » : seuls les deux premiers et les quatre derniers chiffres se lisent sans geste de l'admin. */
    public static function masquer(string $telephone): string
    {
        $c = substr(preg_replace('/\D/', '', $telephone) ?? '', -10);
        return strlen($c) === 10 ? '+225' . Format::INSECABLE . substr($c, 0, 2) . Format::INSECABLE . '••' . Format::INSECABLE . '••' . Format::INSECABLE . substr($c, 6, 2) . Format::INSECABLE . substr($c, 8, 2) : '••••';
    }

    /** Le numéro d'un client, pour l'admin qui le demande : l'affichage est inscrit au journal (RG-C5). @return ?string */
    public function afficherNumero(int $admin, string $ref, string $ip): ?string
    {
        if (preg_match('/^[0-9a-f]{16}$/', $ref) !== 1) {
            return null;
        }
        $c = $this->db->un('SELECT id, telephone FROM clients WHERE LEFT(jeton_hash, 16) = :r AND telephone IS NOT NULL', ['r' => $ref]);
        if ($c === null) {
            return null;
        }
        JournalAdminService::depuisBase()->noter($admin, 'afficher_numero', 'client', (int) $c['id'], [], $ip);
        return Format::telephone((string) $c['telephone']);
    }

    /** Un message au mécano : e-mail tout de suite s'il en a un, sinon la notification attend dans son espace. */
    private function prevenir(int $mecano, mixed $email, string $type, string $titre, string $texte): void
    {
        $envoye = is_string($email) && $email !== '' && ($this->mail ?? MailService::depuisEnv())->envoyer($email, $titre, $texte . "\n\nallomécano");
        $this->db->inserer('notifications', ['mecanicien_id' => $mecano, 'type' => $type, 'canal' => is_string($email) && $email !== '' ? 'email' : 'push', 'statut' => $envoye ? 'envoyee' : 'en_attente',
            'charge' => json_encode(['titre' => $titre, 'texte' => $texte, 'lien' => '/pro'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'cree_le' => gmdate('Y-m-d H:i:s'), 'envoyee_le' => $envoye ? gmdate('Y-m-d H:i:s') : null]);
    }
}
