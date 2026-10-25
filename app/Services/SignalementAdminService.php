<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Les signalements vus par l'administration (A4) : liste triée par gravité puis ancienneté, détail avec le contexte de la mise en relation,
 * classement sans suite avec note obligatoire, sanction (suspension décidée depuis A3). Lu sous 24 h (RG-S2) ; un critique vise 2 h.
 * Chaque lecture, classement et sanction est journalisé (RG-C5). Le mécano ne sait jamais qui l'a signalé (RG-S1).
 */
final class SignalementAdminService
{
    /** Gravité de chaque motif de C7 : ce qui met quelqu'un en danger passe avant ce qui coûte de l'argent, avant le reste. */
    public const GRAVITES = [
        'critique' => ['identite', 'securite', 'comportement'],
        'important' => ['prix_abusif', 'pas_venu'],
        'normal' => ['autre'],
    ];
    public const GRAVITE_LIBELLES = ['critique' => 'Critique', 'important' => 'Important', 'normal' => 'Normal'];
    public const NOTE_MAX = 500;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    public static function gravite(string $motif): string
    {
        foreach (self::GRAVITES as $gravite => $motifs) {
            if (in_array($motif, $motifs, true)) {
                return $gravite;
            }
        }
        return 'normal';
    }

    /** « SIG-2026-0187 » */
    public static function numero(int $id, string $creeLe): string
    {
        return sprintf('SIG-%s-%04d', substr($creeLe, 0, 4), $id);
    }

    /** Liste de motifs d'une gravité, pour un IN (…) : ce sont nos constantes, jamais une saisie. */
    private static function motifsSql(string $gravite): string
    {
        return "'" . implode("', '", self::GRAVITES[$gravite]) . "'";
    }

    /**
     * Les chiffres de l'en-tête et de l'alerte : ouverts, critiques, critiques en retard sur la cible, classés ce mois-ci.
     *
     * @return array{ouverts: int, critiques: int, en_retard: int, classes_mois: int, cible_h: int}
     */
    public function decompte(?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cible = Parametres::int('signalement.critique_h', 2);
        $o = $this->db->un(
            'SELECT COUNT(*) AS ouverts, COALESCE(SUM(motif IN (' . self::motifsSql('critique') . ')), 0) AS critiques,
                    COALESCE(SUM(motif IN (' . self::motifsSql('critique') . ') AND cree_le < :limite), 0) AS en_retard
               FROM signalements WHERE statut IN (\'nouveau\', \'en_cours\')',
            ['limite' => $maintenant->modify("-$cible hours")->format('Y-m-d H:i:s')]
        );
        $classes = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM signalements WHERE statut IN (\'sans_suite\', \'sanction\') AND traite_le >= :debut',
            ['debut' => $maintenant->format('Y-m-01 00:00:00')]
        );
        return ['ouverts' => (int) ($o['ouverts'] ?? 0), 'critiques' => (int) ($o['critiques'] ?? 0), 'en_retard' => (int) ($o['en_retard'] ?? 0), 'classes_mois' => $classes, 'cible_h' => $cible];
    }

    /**
     * La colonne de gauche : « ouverts » par gravité puis ancienneté (le plus ancien d'abord), « classes » sur 30 jours, les derniers d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function liste(string $onglet, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($onglet === 'classes') {
            $ou = 's.statut IN (\'sans_suite\', \'sanction\') AND s.traite_le >= :depuis';
            $ordre = 's.traite_le DESC, s.id DESC';
            $params = ['depuis' => $maintenant->modify('-30 days')->format('Y-m-d H:i:s')];
        } else {
            $ou = 's.statut IN (\'nouveau\', \'en_cours\')';
            $ordre = 'CASE WHEN s.motif IN (' . self::motifsSql('critique') . ') THEN 0 WHEN s.motif IN (' . self::motifsSql('important') . ') THEN 1 ELSE 2 END, s.cree_le ASC, s.id ASC';
            $params = [];
        }
        $lignes = $this->db->tous(
            "SELECT s.id, s.public_id, s.motif, s.statut, s.cree_le, s.traite_le, m.prenom, m.nom, q.nom AS quartier
               FROM signalements s JOIN mecaniciens m ON m.id = s.mecanicien_id LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id
              WHERE $ou ORDER BY $ordre LIMIT 100",
            $params
        );
        $utc = new \DateTimeZone('UTC');
        return array_map(function (array $l) use ($utc, $maintenant, $onglet): array {
            $cree = new \DateTimeImmutable((string) $l['cree_le'], $utc);
            return [
                'public_id' => (string) $l['public_id'], 'numero' => self::numero((int) $l['id'], (string) $l['cree_le']), 'gravite' => self::gravite((string) $l['motif']),
                'titre' => SignalementService::MOTIFS[$l['motif']] ?? 'Autre', 'mecano' => $l['prenom'] . ' ' . $l['nom'], 'quartier' => (string) ($l['quartier'] ?? ''),
                'quand' => $onglet === 'classes' && $l['traite_le'] !== null
                    ? ($l['statut'] === 'sanction' ? 'Sanction ' : 'Classé ') . self::ecoule(new \DateTimeImmutable((string) $l['traite_le'], $utc), $maintenant)
                    : self::ecoule($cree, $maintenant),
                'statut' => (string) $l['statut'],
            ];
        }, $lignes);
    }

    /** « il y a 2 h 40 », « il y a 5 h », « il y a 12 min », « hier · 19:12 », « le 23 octobre · 19:12 » (Figma 179:651). */
    public static function ecoule(\DateTimeImmutable $date, \DateTimeImmutable $maintenant): string
    {
        $s = max(0, $maintenant->getTimestamp() - $date->getTimestamp());
        $i = Format::INSECABLE;
        if ($s < 60) {
            return 'à l’instant';
        }
        if ($s < 3600) {
            return 'il y a ' . intdiv($s, 60) . $i . 'min';
        }
        if ($s < 86400 && $date->format('Y-m-d') === $maintenant->format('Y-m-d')) {
            $h = intdiv($s, 3600);
            $m = intdiv($s % 3600, 60);
            return 'il y a ' . $h . $i . 'h' . ($m > 0 ? $i . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
        }
        if ($date->format('Y-m-d') === $maintenant->modify('-1 day')->format('Y-m-d')) {
            return 'hier · ' . Format::heure($date);
        }
        $jour = trim((string) preg_replace('/\s\d{4}$/', '', Format::date($date)));
        return 'le ' . $jour . ' · ' . Format::heure($date);
    }

    /** « aujourd’hui à 14:34 », « hier à 19:12 », « le 23 octobre à 19:12 » */
    public static function quand(\DateTimeImmutable $date, \DateTimeImmutable $maintenant): string
    {
        if ($date->format('Y-m-d') === $maintenant->format('Y-m-d')) {
            return 'aujourd’hui à ' . Format::heure($date);
        }
        if ($date->format('Y-m-d') === $maintenant->modify('-1 day')->format('Y-m-d')) {
            return 'hier à ' . Format::heure($date);
        }
        return 'le ' . trim((string) preg_replace('/\s\d{4}$/', '', Format::date($date))) . ' à ' . Format::heure($date);
    }

    /**
     * Le détail à droite (Figma 179:680) : mécano concerné, récit, client, contexte de la mise en relation.
     *
     * @return ?array<string, mixed>
     */
    public function detail(string $publicId, ?\DateTimeImmutable $maintenant = null): ?array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $s = $this->db->un(
            'SELECT s.id, s.public_id, s.motif, s.description, s.photo_fichier, s.statut, s.decision, s.cree_le, s.traite_le, s.mecanicien_id,
                    m.public_id AS mecano_public, m.prenom, m.nom, m.telephone AS mecano_telephone, m.statut_dossier, m.note_moyenne, m.nb_avis, m.dispo_lat, m.dispo_lon, q.nom AS quartier,
                    ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y,
                    cl.prenom AS client_prenom, cl.telephone AS client_telephone, cl.jeton_hash,
                    k.canal, k.cree_le AS appel_le, k.duree_s, k.resume, k.quartier_libelle, k.lat AS appel_lat, k.lon AS appel_lon,
                    a.prenom AS admin_prenom, a.nom AS admin_nom,
                    (SELECT COUNT(*) FROM signalements p WHERE p.mecanicien_id = s.mecanicien_id AND p.id <> s.id) AS passes,
                    (SELECT COUNT(*) FROM pieces_dossier pp WHERE pp.mecanicien_id = m.id AND pp.type_piece = \'portrait\' AND pp.fichier IS NOT NULL AND pp.fichier_supprime_le IS NULL AND pp.statut <> \'remplacee\') AS portrait_piece
               FROM signalements s JOIN mecaniciens m ON m.id = s.mecanicien_id LEFT JOIN quartiers q ON q.id = m.atelier_quartier_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
               LEFT JOIN clients cl ON cl.id = s.client_id LEFT JOIN contacts k ON k.id = s.contact_id LEFT JOIN admins a ON a.id = s.traite_par_admin_id
              WHERE s.public_id = :p',
            ['p' => $publicId]
        );
        if ($s === null) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        $cree = new \DateTimeImmutable((string) $s['cree_le'], $utc);
        $lieu = (string) ($s['quartier_libelle'] ?? '');
        if ($s['appel_lat'] !== null && $s['dispo_lat'] !== null) { // la position du client n'existe plus au bout de 24 h (RG-P1) : alors le quartier seul
            $m = GeoService::distanceM((float) $s['appel_lat'], (float) $s['appel_lon'], (float) $s['dispo_lat'], (float) $s['dispo_lon']);
            $lieu .= ($lieu !== '' ? ' · ' : '') . Format::distance($m) . ' à vol d’oiseau';
        }
        $appel = null;
        if ($s['appel_le'] !== null) {
            $le = self::quand(new \DateTimeImmutable((string) $s['appel_le'], $utc), $maintenant);
            $appel = match ($s['canal']) {
                'app' => ucfirst($le) . ($s['duree_s'] !== null ? ', ' . max(1, (int) round((int) $s['duree_s'] / 60)) . Format::INSECABLE . 'min' : ''),
                'whatsapp' => 'WhatsApp, ' . $le,
                default => 'Appel direct, ' . $le,
            };
        }
        $panne = null;
        if ($s['resume'] !== null) {
            $morceaux = explode(' · ', (string) $s['resume']);
            $panne = count($morceaux) > 1 ? $morceaux[0] . ' · ' . mb_strtolower(implode(' · ', array_slice($morceaux, 1)), 'UTF-8') : (string) $s['resume'];
        }
        $statut = (string) $s['statut_dossier'];
        return [
            'id' => (int) $s['id'], 'public_id' => (string) $s['public_id'], 'numero' => self::numero((int) $s['id'], (string) $s['cree_le']), 'motif' => (string) $s['motif'],
            'gravite' => self::gravite((string) $s['motif']), 'titre' => SignalementService::MOTIFS[$s['motif']] ?? 'Autre', 'statut' => (string) $s['statut'], 'ouvert' => in_array($s['statut'], ['nouveau', 'en_cours'], true),
            'cree_le' => $cree, 'recu' => 'reçu ' . self::quand($cree, $maintenant), 'ecoule' => self::ecoule($cree, $maintenant), 'description' => $s['description'],
            'decision' => $s['decision'], 'traite_le' => $s['traite_le'] !== null ? new \DateTimeImmutable((string) $s['traite_le'], $utc) : null,
            'traite_par' => $s['admin_nom'] !== null ? mb_substr((string) $s['admin_prenom'], 0, 1) . '. ' . $s['admin_nom'] : null,
            'mecano' => [
                'public_id' => (string) $s['mecano_public'], 'nom_complet' => $s['prenom'] . ' ' . $s['nom'], 'telephone' => (string) $s['mecano_telephone'], 'statut' => $statut, 'quartier' => (string) ($s['quartier'] ?? ''),
                'note' => $s['note_moyenne'] !== null ? (float) $s['note_moyenne'] : null, 'nb_avis' => (int) $s['nb_avis'], 'passes' => (int) $s['passes'],
                'photo' => PhotoService::portrait($s), 'portrait_piece' => (int) $s['portrait_piece'] > 0, 'suspendable' => $statut === 'valide',
            ],
            'client' => ['prenom' => (string) ($s['client_prenom'] ?? ''), 'ref' => $s['jeton_hash'] !== null ? substr((string) $s['jeton_hash'], 0, 16) : null,
                'masque' => $s['client_telephone'] !== null ? CompteService::masquer((string) $s['client_telephone']) : null],
            'contexte' => ['appel' => $appel, 'panne' => $panne, 'lieu' => $lieu !== '' ? $lieu : null, 'photo' => $s['photo_fichier'] !== null],
        ];
    }

    /** Premier regard d'un admin : « nouveau » passe à « en cours », une seule fois, et c'est journalisé (RG-S2 : lu sous 24 h). */
    public function marquerLu(int $admin, string $publicId, string $ip): void
    {
        $s = $this->db->un('SELECT id FROM signalements WHERE public_id = :p AND statut = \'nouveau\'', ['p' => $publicId]);
        if ($s === null) {
            return;
        }
        $this->db->transaction(function () use ($admin, $s, $ip): void {
            $this->db->modifier('signalements', ['statut' => 'en_cours'], ['id' => (int) $s['id']]);
            JournalAdminService::depuisBase()->noter($admin, 'lire_signalement', 'signalement', (int) $s['id'], [], $ip);
        });
    }

    /**
     * « Classer sans suite » : la note est obligatoire (Figma 179:759). Le client voit l'état de son signalement passer à « traité » (C7).
     *
     * @return array{ok: true}|array{ok: false, erreur: string}
     */
    public function classer(int $admin, string $publicId, string $note, string $ip, ?\DateTimeImmutable $maintenant = null): array
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > self::NOTE_MAX) {
            return ['ok' => false, 'erreur' => 'Écrivez une note pour classer ce signalement (' . self::NOTE_MAX . ' caractères au plus).'];
        }
        $s = $this->db->un('SELECT id FROM signalements WHERE public_id = :p AND statut IN (\'nouveau\', \'en_cours\')', ['p' => $publicId]);
        if ($s === null) {
            return ['ok' => false, 'erreur' => 'Ce signalement est déjà classé.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->db->transaction(function () use ($admin, $s, $note, $ip, $maintenant): void {
            $this->db->modifier('signalements', ['statut' => 'sans_suite', 'traite_par_admin_id' => $admin, 'traite_le' => $maintenant->format('Y-m-d H:i:s'), 'decision' => $note], ['id' => (int) $s['id']]);
            JournalAdminService::depuisBase()->noter($admin, 'classer_signalement', 'signalement', (int) $s['id'], ['note' => $note], $ip);
        });
        return ['ok' => true];
    }

    /**
     * La suspension du mécano est décidée depuis A3 : le signalement passe en « sanction », avec le motif de la suspension pour décision (RG-S2).
     *
     * @return bool vrai si un signalement ouvert a été traité
     */
    public function sanctionner(int $admin, string $publicId, string $motif, string $ip, ?\DateTimeImmutable $maintenant = null): bool
    {
        $s = $this->db->un('SELECT id FROM signalements WHERE public_id = :p AND statut IN (\'nouveau\', \'en_cours\')', ['p' => $publicId]);
        if ($s === null) {
            return false;
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->db->transaction(function () use ($admin, $s, $motif, $ip, $maintenant): void {
            $this->db->modifier('signalements', ['statut' => 'sanction', 'traite_par_admin_id' => $admin, 'traite_le' => $maintenant->format('Y-m-d H:i:s'), 'decision' => mb_substr($motif, 0, self::NOTE_MAX)], ['id' => (int) $s['id']]);
            JournalAdminService::depuisBase()->noter($admin, 'sanctionner_signalement', 'signalement', (int) $s['id'], ['motif' => $motif], $ip);
        });
        return true;
    }

    /**
     * La photo jointe par le client (RG-S3), pour l'admin seulement : chaque consultation est journalisée.
     *
     * @return ?array{chemin: string} nul si le signalement n'a pas de photo ou si le fichier a disparu
     */
    public function photo(int $admin, string $publicId, string $ip, ?string $dossier = null): ?array
    {
        $s = $this->db->un('SELECT id, photo_fichier FROM signalements WHERE public_id = :p AND photo_fichier IS NOT NULL', ['p' => $publicId]);
        if ($s === null) {
            return null;
        }
        $racine = realpath($dossier ?? dirname(__DIR__, 2) . '/storage/signalements');
        $chemin = $racine !== false ? realpath($racine . DIRECTORY_SEPARATOR . $s['photo_fichier']) : false;
        if ($chemin === false || !str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR)) {
            return null;
        }
        JournalAdminService::depuisBase()->noter($admin, 'voir_photo_signalement', 'signalement', (int) $s['id'], [], $ip);
        return ['chemin' => $chemin];
    }
}
