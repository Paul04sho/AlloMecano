<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Les statistiques de l'administration (A5) : l'entonnoir de la recherche à l'avis sur 7 ou 30 jours, et trois indicateurs de santé
 * (délai de validation des dossiers, taux de reproposition, mécanos passés indisponibles seuls). Tout est compté, rien n'est nominatif :
 * ni numéro ni coordonnée (RG-P2, RG-P4). Les jours sont pleins, de 00:00 UTC (Abidjan = GMT).
 */
final class StatistiquesService
{
    public const PERIODES = [7, 30];
    private const ETAPES = [
        'recherches' => ['Recherches', 'Position et panne envoyées'],
        'contacts' => ['Contacts', 'Appel ou WhatsApp lancé'],
        'joints' => ['A pu joindre', 'Réponse « oui » après le contact'],
        'avis' => ['Avis laissés', 'Note et commentaire facultatif'],
    ];

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    public static function periode(mixed $valeur): int
    {
        return (int) $valeur === 7 ? 7 : 30;
    }

    /** Début d'une période de N jours pleins qui se termine aujourd'hui : « du 22 septembre au 21 octobre » pour 30 jours. */
    public static function debut(\DateTimeImmutable $maintenant, int $jours): \DateTimeImmutable
    {
        return $maintenant->setTime(0, 0)->modify('-' . ($jours - 1) . ' days');
    }

    /**
     * @return array{
     *   periode: int, autre: int, du: \DateTimeImmutable, au: \DateTimeImmutable, maj: \DateTimeImmutable,
     *   etapes: list<array{cle: string, titre: string, sous: string, valeur: int, autre: int, conversion: ?string, largeur: float}>,
     *   validation: array{heures: ?int, decisions: int, objectif: int, mois_dernier: ?int, plus_long: ?int},
     *   reproposition: array{pct: ?int, reproposes: int, sans_reponse: int},
     *   indisponibles: array{mecanos: int, revenus: int, seuil: int}
     * }
     */
    public function tout(int $periode, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $periode = self::periode($periode);
        $autre = $periode === 7 ? 30 : 7;
        $debut = self::debut($maintenant, $periode)->format('Y-m-d H:i:s');
        $n = $this->compter($debut);
        $m = $this->compter(self::debut($maintenant, $autre)->format('Y-m-d H:i:s'));
        $etapes = [];
        $precedent = null;
        $maximum = max(1, $n['recherches']);
        foreach (self::ETAPES as $cle => [$titre, $sous]) {
            $etapes[] = [
                'cle' => $cle, 'titre' => $titre, 'sous' => $sous, 'valeur' => $n[$cle], 'autre' => $m[$cle], 'largeur' => round(100 * $n[$cle] / $maximum, 2),
                'conversion' => match (true) {
                    $precedent === null => 'base',
                    $precedent[1] === 0 => null,
                    default => round(100 * $n[$cle] / $precedent[1]) . Format::FINE . '% ' . ($cle === 'avis' ? 'de ceux qui ont pu joindre' : 'des ' . mb_strtolower($precedent[0], 'UTF-8')),
                },
            ];
            $precedent = [$titre, $n[$cle]];
        }
        $moisCourant = $maintenant->modify('first day of this month')->setTime(0, 0);
        $moisPrecedent = $moisCourant->modify('-1 month');
        $duMois = $this->delai($moisCourant->format('Y-m-d H:i:s'), null);
        $duMoisPrecedent = $this->delai($moisPrecedent->format('Y-m-d H:i:s'), $moisCourant->format('Y-m-d H:i:s'));
        $d = $this->delai($debut, null);
        $r = $this->db->un(
            'SELECT COUNT(*) AS sans_reponse, COALESCE(SUM(EXISTS(SELECT 1 FROM contacts c2 WHERE c2.contact_precedent_id = c.id)), 0) AS reproposes
               FROM contacts c WHERE c.cree_le >= :d AND (c.statut IN (\'manque\', \'occupe\', \'echoue\') OR c.issue_client = \'pas_reponse\')',
            ['d' => $debut]
        );
        $sans = (int) ($r['sans_reponse'] ?? 0);
        $repro = (int) ($r['reproposes'] ?? 0);
        $i = $this->db->un(
            'SELECT COUNT(DISTINCT h.mecanicien_id) AS mecanos,
                    COUNT(DISTINCT CASE WHEN EXISTS(SELECT 1 FROM historique_disponibilite r WHERE r.mecanicien_id = h.mecanicien_id AND r.etat = \'disponible\'
                                                      AND r.debut > h.debut AND r.debut <= h.debut + INTERVAL 1 DAY) THEN h.mecanicien_id END) AS revenus
               FROM historique_disponibilite h WHERE h.raison = \'auto_injoignable\' AND h.debut >= :d',
            ['d' => $debut]
        );
        return [
            'periode' => $periode, 'autre' => $autre, 'du' => self::debut($maintenant, $periode), 'au' => $maintenant, 'maj' => $maintenant, 'etapes' => $etapes,
            'validation' => [
                'heures' => $d['moyenne'] !== null ? (int) round($d['moyenne'] / 60) : null, 'decisions' => $d['n'], 'objectif' => Parametres::int('dossier.delai_h', 48),
                'mois_dernier' => $duMoisPrecedent['moyenne'] !== null ? (int) round($duMoisPrecedent['moyenne'] / 60) : null,
                'plus_long' => $duMois['maxi'] !== null ? (int) round($duMois['maxi'] / 60) : null,
            ],
            'reproposition' => ['pct' => $sans > 0 ? (int) round(100 * $repro / $sans) : null, 'reproposes' => $repro, 'sans_reponse' => $sans],
            'indisponibles' => ['mecanos' => (int) ($i['mecanos'] ?? 0), 'revenus' => (int) ($i['revenus'] ?? 0), 'seuil' => Parametres::int('injoignable.seuil', 3)],
        ];
    }

    /** @return array{recherches: int, contacts: int, joints: int, avis: int} */
    private function compter(string $debut): array
    {
        $l = $this->db->un(
            'SELECT (SELECT COUNT(*) FROM recherches WHERE cree_le >= :d1) AS recherches,
                    (SELECT COUNT(*) FROM contacts WHERE cree_le >= :d2) AS contacts,
                    (SELECT COUNT(*) FROM contacts WHERE cree_le >= :d3 AND issue_client IN (\'arrive\', \'pas_entendus\')) AS joints,
                    (SELECT COUNT(*) FROM avis WHERE cree_le >= :d4) AS avis',
            ['d1' => $debut, 'd2' => $debut, 'd3' => $debut, 'd4' => $debut]
        );
        return ['recherches' => (int) $l['recherches'], 'contacts' => (int) $l['contacts'], 'joints' => (int) $l['joints'], 'avis' => (int) $l['avis']];
    }

    /**
     * Du dossier envoyé à la décision de l'équipe (valider, renvoyer, refuser), en minutes. Un dossier renvoyé puis renvoyé de nouveau compte
     * depuis son dernier envoi : une décision antérieure à cet envoi n'est pas comptée.
     *
     * @return array{n: int, moyenne: ?float, maxi: ?int}
     */
    private function delai(string $debut, ?string $fin): array
    {
        $params = ['d' => $debut];
        if ($fin !== null) {
            $params['f'] = $fin;
        }
        $l = $this->db->un(
            'SELECT COUNT(*) AS n, AVG(TIMESTAMPDIFF(MINUTE, m.dossier_envoye_le, h.cree_le)) AS moyenne, MAX(TIMESTAMPDIFF(MINUTE, m.dossier_envoye_le, h.cree_le)) AS maxi
               FROM historique_dossier h JOIN mecaniciens m ON m.id = h.mecanicien_id
              WHERE h.admin_id IS NOT NULL AND h.de_statut = \'en_attente\' AND h.vers_statut IN (\'valide\', \'a_reprendre\', \'refuse\')
                AND h.cree_le >= :d' . ($fin !== null ? ' AND h.cree_le < :f' : '') . ' AND m.dossier_envoye_le IS NOT NULL AND m.dossier_envoye_le <= h.cree_le',
            $params
        );
        return ['n' => (int) $l['n'], 'moyenne' => $l['moyenne'] !== null ? (float) $l['moyenne'] : null, 'maxi' => $l['maxi'] !== null ? (int) $l['maxi'] : null];
    }

    /**
     * L'export CSV de ce que l'écran montre (séparateur « ; », accents lisibles dans Excel grâce au BOM UTF-8). Rien de nominatif.
     *
     * @param array<string, mixed> $s le résultat de tout()
     */
    public static function csv(array $s): string
    {
        $ligne = static fn (array $champs): string => implode(';', array_map(static fn ($c): string => '"' . str_replace('"', '""', (string) $c) . '"', $champs)) . "\r\n";
        $sortie = "\u{FEFF}" . $ligne(['Statistiques allomécano', 'du ' . Format::date($s['du']) . ' au ' . Format::date($s['au'])]);
        $sortie .= $ligne(['Étape', $s['periode'] . ' jours', $s['autre'] . ' jours', 'Conversion']);
        foreach ($s['etapes'] as $e) {
            $sortie .= $ligne([$e['titre'], $e['valeur'], $e['autre'], str_replace(Format::FINE, ' ', (string) ($e['conversion'] ?? ''))]);
        }
        $v = $s['validation'];
        $r = $s['reproposition'];
        $i = $s['indisponibles'];
        $sortie .= $ligne([]);
        $sortie .= $ligne(['Indicateur', 'Valeur', 'Détail']);
        $sortie .= $ligne(['Délai moyen de validation (heures)', $v['heures'] ?? '', 'objectif : moins de ' . $v['objectif'] . ' h · ' . $v['decisions'] . ' décisions']);
        $sortie .= $ligne(['Taux de reproposition (%)', $r['pct'] ?? '', $r['reproposes'] . ' clients sur ' . $r['sans_reponse'] . ' sans réponse']);
        $sortie .= $ligne(['Mécanos passés indisponibles automatiquement', $i['mecanos'], $i['revenus'] . ' remis disponibles dans la journée']);
        return $sortie;
    }
}
