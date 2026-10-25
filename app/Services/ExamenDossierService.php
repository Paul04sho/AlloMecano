<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * L'examen d'un dossier par un admin (A2, RG-DO4 à RG-DO6, RG-DO8, RG-DO9, RG-P6) : pièce par pièce « conforme » ou « à reprendre » avec motif,
 * concordances avec les pièces, puis valider, renvoyer pour correction ou refuser. Chaque geste est journalisé (RG-C5).
 */
final class ExamenDossierService
{
    /** Les cinq pièces obligatoires ; l'atelier est facultatif (RG-DO2). */
    public const OBLIGATOIRES = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait'];
    public const PIECES = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait', 'atelier'];

    /** Motifs proposés pour une pièce à reprendre (Figma 168:381). */
    public const MOTIFS = ['photo_floue' => 'Photo floue', 'visage_non_visible' => 'Visage non visible', 'piece_expiree' => 'Pièce expirée', 'nom_different' => 'Nom différent', 'illisible' => 'Illisible', 'autre' => 'Autre'];

    /** Libellés : [titre de la ligne, « la … » (journal), « votre … » (message), titre court du visionneur]. */
    public const NOMS = [
        'cni_recto' => ['CNI · recto', 'la CNI recto', 'le recto de votre pièce d’identité', 'Pièce d’identité · recto'],
        'cni_verso' => ['CNI · verso', 'la CNI verso', 'le verso de votre pièce d’identité', 'Pièce d’identité · verso'],
        'permis' => ['Permis', 'le permis', 'votre permis de conduire', 'Permis de conduire'],
        'diplome' => ['Diplôme', 'le diplôme', 'votre diplôme ou attestation de métier', 'Diplôme ou attestation'],
        'portrait' => ['Photo de vous', 'la photo de profil', 'votre photo de profil', 'Photo de profil'],
        'atelier' => ['Atelier', 'la photo de l’atelier', 'votre photo d’atelier', 'Atelier'],
    ];

    public function __construct(private readonly Database $db, private readonly ?PhotoPubliqueService $photos = null, private readonly ?MailService $mail = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Le dossier tel que l'examen l'affiche.
     *
     * @return ?array<string, mixed>
     */
    public function dossier(string $publicId, ?\DateTimeImmutable $maintenant = null): ?array
    {
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.prenom, m.nom, m.email, m.telephone, m.statut_dossier, m.dossier_envoye_le, m.date_naissance, m.motif_refus, c.nom AS commune
               FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id LEFT JOIN communes c ON c.id = q.commune_id
              WHERE m.public_id = :p AND m.supprime_le IS NULL',
            ['p' => $publicId]
        );
        if ($m === null) {
            return null;
        }
        $id = (int) $m['id'];
        $specs = array_column($this->db->tous('SELECT s.libelle FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id WHERE ms.mecanicien_id = :m ORDER BY s.ordre', ['m' => $id]), 'libelle');
        $pieces = array_fill_keys(self::PIECES, null);
        foreach ($this->db->tous('SELECT id, type_piece, statut, motif, mime, fichier FROM pieces_dossier WHERE mecanicien_id = :m AND statut <> \'remplacee\' AND fichier_supprime_le IS NULL ORDER BY id', ['m' => $id]) as $p) {
            $pieces[$p['type_piece']] = ['id' => (int) $p['id'], 'statut' => (string) $p['statut'], 'motif' => $p['motif'], 'mime' => (string) $p['mime'], 'fichier' => (string) $p['fichier']];
        }
        $consultees = [];
        foreach ($this->db->tous('SELECT j.cible_id, MAX(j.cree_le) AS le FROM journal_admin j JOIN pieces_dossier p ON p.id = j.cible_id WHERE j.action = \'consulter_piece\' AND j.cible_type = \'piece\' AND p.mecanicien_id = :m GROUP BY j.cible_id', ['m' => $id]) as $c) {
            $consultees[(int) $c['cible_id']] = (string) $c['le'];
        }
        $envoye = $m['dossier_envoye_le'] !== null ? new \DateTimeImmutable((string) $m['dossier_envoye_le'], new \DateTimeZone('UTC')) : null;
        return [
            'id' => $id, 'public_id' => (string) $m['public_id'], 'prenom' => (string) $m['prenom'], 'nom' => (string) $m['nom'], 'nom_complet' => $m['prenom'] . ' ' . $m['nom'], 'email' => $m['email'],
            'statut' => (string) $m['statut_dossier'], 'numero' => DossierService::numero($id, $m['dossier_envoye_le']), 'commune' => (string) ($m['commune'] ?? ''), 'specialites' => $specs,
            'envoye_le' => $envoye, 'jauge' => DossierService::jauge($m['dossier_envoye_le'], $maintenant), 'pieces' => $pieces, 'consultees' => $consultees, 'motif_refus' => $m['motif_refus'],
            'declare' => ['nom' => (string) $m['nom'], 'prenom' => (string) $m['prenom'], 'ne_le' => $m['date_naissance'] !== null ? new \DateTimeImmutable((string) $m['date_naissance'], new \DateTimeZone('UTC')) : null],
        ];
    }

    /** « DOSSIER 1 SUR 12 · SUIVANT : IBRAHIM OUATTARA » : où l'on en est dans la file des dossiers en attente. @return array{rang: int, total: int, suivant: ?array{public_id: string, nom: string}} */
    public function position(string $publicId): array
    {
        $file = $this->db->tous('SELECT public_id, CONCAT(prenom, \' \', nom) AS nom FROM mecaniciens WHERE statut_dossier = \'en_attente\' AND supprime_le IS NULL ORDER BY dossier_envoye_le ASC, id ASC');
        $rang = 0;
        foreach ($file as $i => $l) {
            if ($l['public_id'] === $publicId) {
                $rang = $i + 1;
                break;
            }
        }
        $suivant = $rang > 0 && isset($file[$rang]) ? ['public_id' => (string) $file[$rang]['public_id'], 'nom' => (string) $file[$rang]['nom']] : null;
        return ['rang' => $rang, 'total' => count($file), 'suivant' => $suivant];
    }

    /**
     * Ce qui s'est passé sur ce dossier (A2 · journal d'audit) : l'envoi par le mécano, puis chaque geste des admins, le plus récent d'abord.
     *
     * @return list<array{le: \DateTimeImmutable, qui: string, texte: string}>
     */
    public function journal(array $dossier, int $limite = 40): array
    {
        $utc = new \DateTimeZone('UTC');
        $ids = array_filter(array_map(static fn (?array $p): ?int => $p['id'] ?? null, $dossier['pieces']));
        $in = $ids !== [] ? implode(',', array_map('intval', $ids)) : '0';
        $lignes = $this->db->tous(
            "SELECT j.action, j.details, j.cree_le, a.prenom, a.nom FROM journal_admin j JOIN admins a ON a.id = j.admin_id
              WHERE (j.cible_type = 'mecanicien' AND j.cible_id = :m) OR (j.cible_type = 'piece' AND j.cible_id IN ($in)) ORDER BY j.id DESC LIMIT " . (int) $limite,
            ['m' => $dossier['id']]
        );
        $r = [];
        foreach ($lignes as $l) {
            $d = $l['details'] !== null ? (array) json_decode((string) $l['details'], true) : [];
            $piece = self::NOMS[$d['type'] ?? ''][1] ?? 'la pièce';
            $texte = match ($l['action']) {
                'ouvrir_dossier' => 'Ouvre le dossier',
                'consulter_piece' => 'Consulte ' . $piece,
                'piece_conforme' => 'Marque ' . $piece . ' « Conforme »',
                'piece_a_reprendre' => 'Marque ' . $piece . ' « À reprendre » · motif : ' . mb_strtolower(self::MOTIFS[$d['motif'] ?? ''] ?? '', 'UTF-8'),
                'concordances' => 'Coche « Le visage correspond » et les 3 concordances',
                'valider_dossier' => 'Valide le dossier',
                'renvoyer_dossier' => 'Renvoie le dossier pour correction (' . (int) ($d['pieces'] ?? 1) . ' pièce' . ((int) ($d['pieces'] ?? 1) > 1 ? 's' : '') . ')',
                'refuser_dossier' => 'Refuse le dossier · motif : ' . ($d['motif'] ?? ''),
                default => (string) $l['action'],
            };
            $r[] = ['le' => new \DateTimeImmutable((string) $l['cree_le'], $utc), 'qui' => $l['prenom'] . ' ' . $l['nom'], 'texte' => $texte];
        }
        if ($dossier['envoye_le'] !== null) {
            $r[] = ['le' => $dossier['envoye_le'], 'qui' => $dossier['nom_complet'], 'texte' => 'Envoie son dossier : ' . count(array_filter(array_intersect_key($dossier['pieces'], array_flip(self::OBLIGATOIRES)))) . ' pièces obligatoires'];
        }
        return $r;
    }

    /** « J'ouvre le dossier » : une ligne au journal, sauf si cet admin vient déjà de l'ouvrir (moins de 10 minutes). */
    public function ouvrir(int $admin, array $dossier, string $ip): void
    {
        $recent = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM journal_admin WHERE admin_id = :a AND action = \'ouvrir_dossier\' AND cible_type = \'mecanicien\' AND cible_id = :m AND cree_le > :d',
            ['a' => $admin, 'm' => $dossier['id'], 'd' => gmdate('Y-m-d H:i:s', time() - 600)]
        );
        if ($recent === 0) {
            JournalAdminService::depuisBase()->noter($admin, 'ouvrir_dossier', 'mecanicien', $dossier['id'], [], $ip);
        }
    }

    /**
     * Le fichier d'une pièce pour la visionneuse, après avoir inscrit la consultation au journal (RG-P6).
     *
     * @return ?array{chemin: string, piece: int}
     */
    public function consulter(int $admin, string $publicId, string $type, string $ip): ?array
    {
        if (!in_array($type, self::PIECES, true)) {
            return null;
        }
        $p = $this->db->un(
            'SELECT p.id, p.fichier FROM pieces_dossier p JOIN mecaniciens m ON m.id = p.mecanicien_id
              WHERE m.public_id = :m AND p.type_piece = :t AND p.statut <> \'remplacee\' AND p.fichier IS NOT NULL AND p.fichier_supprime_le IS NULL ORDER BY p.id DESC LIMIT 1',
            ['m' => $publicId, 't' => $type]
        );
        $racine = realpath(dirname(__DIR__, 2) . '/storage/pieces');
        $chemin = $p !== null && $racine !== false ? realpath($racine . '/' . $p['fichier']) : false;
        if ($chemin === false || !str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR)) {
            return null;
        }
        JournalAdminService::depuisBase()->noter($admin, 'consulter_piece', 'piece', (int) $p['id'], ['type' => $type], $ip);
        return ['chemin' => $chemin, 'piece' => (int) $p['id']];
    }

    /**
     * Le message envoyé au mécano quand des pièces sont à reprendre (Figma 168:406), mot pour mot l'aperçu de l'admin.
     *
     * @param array<string, array{motif: string, precision: string}> $reprises type de pièce → motif et précision
     */
    public static function message(string $prenom, array $reprises): string
    {
        if ($reprises === []) {
            return '';
        }
        $liste = array_map(static fn (string $t): string => self::NOMS[$t][2], array_keys($reprises));
        $n = count($liste);
        $texte = 'Bonjour ' . $prenom . ', votre dossier est presque complet. ' . ($n === 1 ? 'Une pièce est' : $n . ' pièces sont') . ' à reprendre : '
            . ($n === 1 ? $liste[0] : implode(', ', array_slice($liste, 0, -1)) . ' et ' . end($liste)) . '. ';
        foreach ($reprises as $type => $r) {
            $motif = mb_strtolower(self::MOTIFS[$r['motif']] ?? '', 'UTF-8');
            $texte .= ($n === 1 ? 'Motif : ' : ucfirst(self::NOMS[$type][1]) . ' · motif : ') . $motif . '. ';
            $precision = trim($r['precision']);
            if ($precision !== '') {
                $texte .= $precision . (preg_match('/[.!?]$/u', $precision) === 1 ? ' ' : '. ');
            }
        }
        $expiree = count(array_filter($reprises, static fn (array $r): bool => $r['motif'] === 'piece_expiree')) === $n;
        $court = $n === 1 ? preg_replace('/^votre permis de conduire$/', 'votre permis', $liste[0]) : null;
        $texte .= $n === 1
            ? 'Envoyez une ' . ($expiree ? 'photo' : 'nouvelle photo') . ' de ' . $court . ($expiree ? ' en cours de validité' : '') . ' depuis votre espace. '
            : 'Envoyez de nouvelles photos de ces pièces depuis votre espace. ';
        return $texte . 'Les autres pièces sont conformes, vous n’avez rien d’autre à refaire.';
    }

    /**
     * Décision de l'admin sur le dossier : valider, renvoyer pour correction ou refuser. Tout ou rien (une transaction).
     *
     * @param array<string, mixed> $saisie action (valider|renvoyer|refuser), piece[type] (conforme|a_reprendre), motif[type], precision[type], concordance[nom|prenom|ne_le], visage, motif_refus
     * @return array{ok: true, issue: string}|array{ok: false, erreur: string}
     */
    public function decider(int $admin, string $publicId, array $saisie, string $ip, ?\DateTimeImmutable $maintenant = null): array
    {
        $d = $this->dossier($publicId, $maintenant);
        if ($d === null) {
            return ['ok' => false, 'erreur' => 'Ce dossier est introuvable.'];
        }
        if ($d['statut'] !== 'en_attente') {
            return ['ok' => false, 'erreur' => 'Ce dossier n’est plus en attente : il a déjà été traité.'];
        }
        $action = (string) ($saisie['action'] ?? '');
        $journal = JournalAdminService::depuisBase();
        if ($action === 'refuser') {
            $motif = trim((string) ($saisie['motif_refus'] ?? ''));
            if ($motif === '' || mb_strlen($motif) > 500) {
                return ['ok' => false, 'erreur' => 'Un refus est définitif : indiquez le motif (500 caractères au plus).'];
            }
            $this->db->transaction(function () use ($d, $admin, $motif, $journal, $ip): void {
                $this->db->modifier('mecaniciens', ['statut_dossier' => 'refuse', 'motif_refus' => $motif, 'valide_par_admin_id' => null, 'etat' => 'indisponible'], ['id' => $d['id']]);
                $this->historique($d['id'], 'en_attente', 'refuse', $admin, $motif);
                $journal->noter($admin, 'refuser_dossier', 'mecanicien', $d['id'], ['motif' => $motif], $ip);
                $this->notifier($d, 'dossier_refuse', 'Votre dossier n’a pas pu être validé', 'Bonjour ' . $d['prenom'] . ', votre dossier n’a pas pu être validé. Motif : ' . $motif);
            });
            return ['ok' => true, 'issue' => 'refuse'];
        }
        if (!in_array($action, ['valider', 'renvoyer'], true)) {
            return ['ok' => false, 'erreur' => 'Action inconnue.'];
        }

        // Décision de chaque pièce présente : « conforme » ou « à reprendre » ; les cinq obligatoires doivent toutes avoir une décision
        $decisions = [];
        $reprises = [];
        foreach (self::PIECES as $type) {
            $p = $d['pieces'][$type];
            $v = $saisie['piece'][$type] ?? null;
            if ($p === null) {
                if (in_array($type, self::OBLIGATOIRES, true)) {
                    return ['ok' => false, 'erreur' => 'Il manque une pièce obligatoire : ' . self::NOMS[$type][3] . '.'];
                }
                continue;
            }
            if (!in_array($v, ['conforme', 'a_reprendre'], true)) {
                if (in_array($type, self::OBLIGATOIRES, true)) {
                    return ['ok' => false, 'erreur' => 'Examinez les cinq pièces : « ' . self::NOMS[$type][3] . ' » n’a pas encore de décision.'];
                }
                continue; // atelier facultatif sans décision : ni publié ni reproché
            }
            $decisions[$type] = $v;
            if ($v === 'a_reprendre') {
                $motif = (string) ($saisie['motif'][$type] ?? '');
                $precision = trim((string) ($saisie['precision'][$type] ?? ''));
                if (!isset(self::MOTIFS[$motif]) || ($motif === 'autre' && $precision === '') || mb_strlen($precision) > 300) {
                    return ['ok' => false, 'erreur' => 'Choisissez le motif de « ' . self::NOMS[$type][3] . ' »' . ($motif === 'autre' ? ' et précisez-le' : '') . '.'];
                }
                $reprises[$type] = ['motif' => $motif, 'precision' => $precision];
            }
        }
        $obligatoiresAReprendre = array_intersect(array_keys($reprises), self::OBLIGATOIRES);
        if ($action === 'valider') {
            if ($obligatoiresAReprendre !== []) {
                return ['ok' => false, 'erreur' => 'Validation impossible : une pièce est à reprendre. Elle se débloque quand les 5 pièces sont conformes.'];
            }
            foreach (['nom', 'prenom', 'ne_le'] as $c) {
                if (empty($saisie['concordance'][$c])) {
                    return ['ok' => false, 'erreur' => 'Validation impossible : cochez les trois concordances avec la pièce d’identité.'];
                }
            }
            if (empty($saisie['visage'])) {
                return ['ok' => false, 'erreur' => 'Validation impossible : cochez « Le visage correspond à la pièce ».'];
            }
        } elseif ($obligatoiresAReprendre === []) {
            return ['ok' => false, 'erreur' => 'Rien à renvoyer : aucune pièce obligatoire n’est à reprendre.'];
        }

        $issue = $action === 'valider' ? 'valide' : 'a_reprendre';
        $this->db->transaction(function () use ($d, $admin, $action, $decisions, $reprises, $saisie, $journal, $ip, $issue, $maintenant): void {
            foreach ($decisions as $type => $v) {
                $p = $d['pieces'][$type];
                $statut = $v === 'conforme' ? 'validee' : 'a_reprendre';
                $this->db->modifier('pieces_dossier', ['statut' => $statut, 'motif' => $v === 'a_reprendre' ? $this->motifPiece($reprises[$type]) : null,
                    'examinee_par_admin_id' => $admin, 'examinee_le' => gmdate('Y-m-d H:i:s')], ['id' => $p['id']]);
                if ($p['statut'] !== $statut) {
                    $journal->noter($admin, $v === 'conforme' ? 'piece_conforme' : 'piece_a_reprendre', 'piece', $p['id'], ['type' => $type] + ($v === 'a_reprendre' ? ['motif' => $reprises[$type]['motif']] : []), $ip);
                }
            }
            if ($action === 'valider') {
                $journal->noter($admin, 'concordances', 'mecanicien', $d['id'], ['nom' => 1, 'prenom' => 1, 'ne_le' => 1, 'visage' => 1], $ip);
                $this->publier($d, $admin, $maintenant, $decisions);
                $journal->noter($admin, 'valider_dossier', 'mecanicien', $d['id'], [], $ip);
                $this->historique($d['id'], 'en_attente', 'valide', $admin, null);
                $this->notifier($d, 'dossier_valide', 'Votre dossier est validé', 'Bonjour ' . $d['prenom'] . ', votre dossier est validé. Vous pouvez vous rendre disponible depuis votre espace.');
                return;
            }
            $this->db->modifier('mecaniciens', ['statut_dossier' => 'a_reprendre'], ['id' => $d['id']]);
            $this->historique($d['id'], 'en_attente', 'a_reprendre', $admin, implode(', ', array_map(static fn (string $t): string => self::NOMS[$t][3] . ' : ' . self::MOTIFS[$reprises[$t]['motif']], array_keys($reprises))));
            $journal->noter($admin, 'renvoyer_dossier', 'mecanicien', $d['id'], ['pieces' => count($reprises)], $ip);
            $this->notifier($d, 'dossier_a_reprendre', 'Une pièce est à reprendre', self::message($d['prenom'], $reprises));
        });
        return ['ok' => true, 'issue' => $issue];
    }

    /** « Motif : pièce expirée. Le permis a expiré le… » tel qu'on le garde sur la pièce (300 caractères au plus). @param array{motif: string, precision: string} $r */
    private function motifPiece(array $r): string
    {
        return mb_substr(self::MOTIFS[$r['motif']] . ($r['precision'] !== '' ? ' · ' . $r['precision'] : ''), 0, 300);
    }

    /** RG-DO5, RG-DO9 : le dossier est validé, le poinçon apparaît ; le slug naît ; les photos publiques sont générées. @param array<string, mixed> $d @param array<string, string> $decisions */
    private function publier(array $d, int $admin, ?\DateTimeImmutable $maintenant, array $decisions): void
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $slug = $this->db->valeur('SELECT slug FROM mecaniciens WHERE id = :i', ['i' => $d['id']]);
        $this->db->modifier('mecaniciens', [
            'statut_dossier' => 'valide', 'identite_verifiee_le' => $maintenant->format('Y-m-d'), 'valide_par_admin_id' => $admin, 'motif_refus' => null,
            'slug' => $slug ?: $this->slug($d),
        ], ['id' => $d['id']]);
        $photos = $this->photos ?? new PhotoPubliqueService();
        $racine = dirname(__DIR__, 2) . '/storage/pieces/';
        foreach (['portrait', 'atelier'] as $type) {
            $p = $d['pieces'][$type] ?? null;
            if ($p === null || ($decisions[$type] ?? null) !== 'conforme') { // seules les pièces conformes deviennent publiques
                continue;
            }
            $g = $photos->generer($racine . $p['fichier'], $d['public_id'], $p['id'], $type);
            if ($g === null) {
                continue;
            }
            $this->db->executer('UPDATE photos SET active = 0 WHERE mecanicien_id = :m AND type = :t', ['m' => $d['id'], 't' => $type]);
            $this->db->inserer('photos', ['mecanicien_id' => $d['id'], 'type' => $type, 'piece_id' => $p['id']] + $g + ['active' => 1]);
        }
    }

    /** « koffi-yao-riviera-3 » : prénom, nom, quartier de l'atelier ; un numéro si le slug existe déjà. @param array<string, mixed> $d */
    private function slug(array $d): string
    {
        $quartier = (string) $this->db->valeur('SELECT q.slug FROM mecaniciens m LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id WHERE m.id = :i', ['i' => $d['id']]);
        $texte = static fn (string $s): string => trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Format::ascii($s))), '-');
        $base = substr($texte($d['prenom'] . ' ' . $d['nom'] . ' ' . $quartier), 0, 80);
        $slug = $base;
        for ($i = 2; (int) $this->db->valeur('SELECT COUNT(*) FROM mecaniciens WHERE slug = :s', ['s' => $slug]) > 0; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    private function historique(int $mecano, string $de, string $vers, int $admin, ?string $motif): void
    {
        $this->db->inserer('historique_dossier', ['mecanicien_id' => $mecano, 'de_statut' => $de, 'vers_statut' => $vers, 'admin_id' => $admin, 'motif' => $motif !== null ? mb_substr($motif, 0, 500) : null, 'cree_le' => gmdate('Y-m-d H:i:s')]);
    }

    /** Le mécano est prévenu : par e-mail s'il en a un (envoyé tout de suite), sinon une notification qui attend dans son espace. @param array<string, mixed> $d */
    private function notifier(array $d, string $type, string $titre, string $texte): void
    {
        $email = $d['email'] ?? null;
        $envoye = false;
        if (is_string($email) && $email !== '') {
            $envoye = ($this->mail ?? MailService::depuisEnv())->envoyer($email, $titre, $texte . "\n\nallomécano");
        }
        $this->db->inserer('notifications', [
            'mecanicien_id' => $d['id'], 'type' => $type, 'canal' => $email ? 'email' : 'push', 'statut' => $envoye ? 'envoyee' : 'en_attente',
            'charge' => json_encode(['titre' => $titre, 'texte' => $texte, 'lien' => '/pro'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'cree_le' => gmdate('Y-m-d H:i:s'), 'envoyee_le' => $envoye ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }
}
