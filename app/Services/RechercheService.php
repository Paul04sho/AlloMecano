<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Ulid;

/**
 * Recherche des mécanos (RG-R2 à RG-R9) et liste figée (RG-R7).
 * Requête de référence : database/recherche.sql. Les requêtes préparées sont natives : chaque paramètre
 * n'apparaît qu'une fois, d'où :lat1, :lat2… et le rectangle de pré-filtre calculé en PHP.
 */
final class RechercheService
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Lance la recherche, enregistre la recherche et la liste montrée, renvoie son identifiant public.
     *
     * @param array{source: string, lat: float, lon: float, precision_m: ?int, quartier: ?string, repere: ?string} $position
     * @param ?int $rayonDemande rayon imposé par le client (« Chercher jusqu'à 20 km ») ; sinon les paliers (RG-R5)
     * @return array{public_id: string, nb: int, rayon_km: int}
     */
    public function lancer(int $client, array $position, string $vehicule, string $panne, ?string $precisions, ?int $rayonDemande = null): array
    {
        $panneId = (int) $this->db->valeur('SELECT id FROM pannes WHERE code = :c', ['c' => $panne]);
        $lieu = $position['quartier'] !== null ? $this->db->un(
            'SELECT q.id, co.rive FROM quartiers q JOIN communes co ON co.id = q.commune_id WHERE q.slug = :s',
            ['s' => $position['quartier']]
        ) : null;
        $rive = (string) ($lieu['rive'] ?? 'nord');

        // RG-R5 : paliers 2, 5 puis 10 km tant qu'il y a moins de 3 résultats ; le rayon étendu seulement sur demande
        $paliers = $rayonDemande !== null ? [$rayonDemande] : Parametres::liste('recherche.rayons_km');
        $min = Parametres::int('recherche.min_resultats', 3);
        $resultats = [];
        $rayon = end($paliers) ?: 10;
        foreach ($paliers as $r) {
            $resultats = $this->mecanosEligibles($position['lat'], $position['lon'], $r, $vehicule, $panneId, $rive);
            $rayon = $r;
            if (count($resultats) >= $min) {
                break;
            }
        }

        $publicId = Ulid::generer();
        $this->db->transaction(function (Database $db) use ($publicId, $client, $position, $vehicule, $panneId, $precisions, $lieu, $rayon, $resultats): void {
            $id = $db->inserer('recherches', [
                'public_id' => $publicId, 'client_id' => $client, 'type_vehicule' => $vehicule, 'panne_id' => $panneId,
                'precisions' => $precisions, 'source_position' => $position['source'], 'quartier_id' => $lieu['id'] ?? null,
                'lat' => $position['lat'], 'lon' => $position['lon'], 'precision_m' => $position['precision_m'],
                'repere' => $position['repere'], 'rayon_km' => $rayon, 'nb_resultats' => count($resultats),
            ]);
            // RG-R7 : la liste montrée est enregistrée telle quelle (« le suivant » sans relancer la recherche)
            foreach ($resultats as $i => $m) {
                $db->inserer('resultats_recherche', [
                    'recherche_id' => $id, 'rang' => $i + 1, 'mecanicien_id' => (int) $m['id'],
                    'distance_m' => (int) $m['distance_m'], 'specialiste' => (int) $m['specialiste'], 'autre_rive' => (int) $m['autre_rive'],
                ]);
            }
            if ($resultats === []) {
                // RG-R8 : +1 au compteur anonyme de la zone (≈ 1 km), sans lien avec le client
                $db->executer(
                    'INSERT INTO zones_sans_resultat (zone_id, jour, type_vehicule, nb) VALUES (:z, UTC_DATE(), :v, 1)
                     ON DUPLICATE KEY UPDATE nb = nb + 1',
                    ['z' => GeoService::geohash($position['lat'], $position['lon']), 'v' => $vehicule]
                );
            }
        });
        return ['public_id' => $publicId, 'nb' => count($resultats), 'rayon_km' => $rayon];
    }

    /**
     * Mécanos éligibles dans un rayon, triés (RG-R2, RG-R3, RG-R4, RG-R6, RG-R9).
     *
     * @return list<array{id: int, distance_m: int, specialiste: int, en_appel: int, autre_rive: int}>
     */
    public function mecanosEligibles(float $lat, float $lon, int $rayonKm, string $vehicule, int $panneId, string $rive): array
    {
        $cadre = GeoService::rectangle($lat, $lon, $rayonKm);
        return array_map(static fn (array $m): array => [
            'id' => (int) $m['id'], 'distance_m' => (int) $m['distance_m'], 'specialiste' => (int) $m['specialiste'],
            'en_appel' => (int) $m['en_appel'], 'autre_rive' => (int) $m['autre_rive'],
        ], $this->db->tous(
            'SELECT m.id,
                    ROUND(6371000 * 2 * ASIN(SQRT(
                        POW(SIN(RADIANS(m.dispo_lat - :lat1) / 2), 2)
                      + COS(RADIANS(:lat2)) * COS(RADIANS(m.dispo_lat)) * POW(SIN(RADIANS(m.dispo_lon - :lon1) / 2), 2)
                    ))) AS distance_m,
                    EXISTS (SELECT 1 FROM mecanicien_specialites ms
                            JOIN panne_specialite ps ON ps.specialite_id = ms.specialite_id
                            WHERE ms.mecanicien_id = m.id AND ps.panne_id = :panne) AS specialiste,
                    EXISTS (SELECT 1 FROM contacts c
                            WHERE c.mecanicien_id = m.id AND c.canal = \'app\' AND c.statut IN (\'sonne\', \'decroche\')) AS en_appel,
                    (co.rive IS NOT NULL AND co.rive <> :rive) AS autre_rive,
                    m.rayon_km AS rayon_mecano, m.note_moyenne
               FROM mecaniciens m
               JOIN mecanicien_vehicules mv ON mv.mecanicien_id = m.id AND mv.type_vehicule = :vehicule
               LEFT JOIN quartiers q ON q.id = m.dispo_quartier_id
               LEFT JOIN communes co ON co.id = q.commune_id
              WHERE m.statut_dossier = \'valide\'
                AND m.etat = \'disponible\'
                AND m.supprime_le IS NULL
                AND m.position_maj_le >= UTC_TIMESTAMP() - INTERVAL :heures HOUR
                AND m.dispo_lat BETWEEN :latmin AND :latmax
                AND m.dispo_lon BETWEEN :lonmin AND :lonmax
             HAVING distance_m <= LEAST(:rayon, rayon_mecano) * 1000
              ORDER BY en_appel ASC,
                       distance_m * IF(specialiste, :bonus, 1) * IF(autre_rive, :malus, 1) ASC,
                       note_moyenne DESC
              LIMIT :max',
            [
                'lat1' => $lat, 'lat2' => $lat, 'lon1' => $lon, 'panne' => $panneId, 'rive' => $rive, 'vehicule' => $vehicule,
                'heures' => Parametres::int('dispo.duree_max_h', 12),
                'latmin' => $cadre['latMin'], 'latmax' => $cadre['latMax'], 'lonmin' => $cadre['lonMin'], 'lonmax' => $cadre['lonMax'],
                'rayon' => $rayonKm, 'bonus' => Parametres::decimal('recherche.bonus_specialiste', 0.75),
                'malus' => Parametres::decimal('recherche.malus_autre_rive', 1.30), 'max' => Parametres::int('recherche.max_resultats', 10),
            ]
        ));
    }

    /**
     * Une recherche et sa liste figée, pour son client seulement (RG-C7, RG-C8).
     *
     * @return ?array{recherche: array<string, mixed>, resultats: list<array<string, mixed>>}
     */
    public function lire(string $publicId, int $client): ?array
    {
        $r = $this->db->un(
            'SELECT r.id, r.public_id, r.type_vehicule, r.precisions, r.source_position, r.lat, r.lon, r.precision_m, r.repere,
                    r.rayon_km, r.nb_resultats, r.cree_le, r.position_effacee_le,
                    p.code AS panne, p.libelle AS panne_libelle, tv.libelle AS vehicule_libelle,
                    q.nom AS quartier, q.slug AS quartier_slug, q.lat AS quartier_lat, q.lon AS quartier_lon, co.nom AS commune
               FROM recherches r
               JOIN pannes p ON p.id = r.panne_id
               JOIN types_vehicule tv ON tv.code = r.type_vehicule
               LEFT JOIN quartiers q ON q.id = r.quartier_id
               LEFT JOIN communes co ON co.id = q.commune_id
              WHERE r.public_id = :p AND r.client_id = :c',
            ['p' => $publicId, 'c' => $client]
        );
        if ($r === null) {
            return null;
        }
        // Position exacte effacée après 24 h (RG-P1) : on repart du centre du quartier
        $r['lat'] = $r['lat'] !== null ? (float) $r['lat'] : (float) $r['quartier_lat'];
        $r['lon'] = $r['lon'] !== null ? (float) $r['lon'] : (float) $r['quartier_lon'];

        $lignes = $this->db->tous(
            'SELECT rr.rang, rr.distance_m, rr.specialiste, rr.autre_rive,
                    m.public_id, m.slug, m.prenom, m.nom, m.metier, m.note_moyenne, m.nb_avis, m.etat, m.dispo_lat, m.dispo_lon,
                    q.nom AS quartier, ph.dossier, ph.largeurs, ph.ratio, ph.couleur_dominante, ph.focal_x, ph.focal_y
               FROM resultats_recherche rr
               JOIN mecaniciens m ON m.id = rr.mecanicien_id
               LEFT JOIN quartiers q ON q.id = m.dispo_quartier_id
               LEFT JOIN photos ph ON ph.mecanicien_id = m.id AND ph.type = \'portrait\' AND ph.active = 1
              WHERE rr.recherche_id = :r
              ORDER BY rr.rang',
            ['r' => (int) $r['id']]
        );
        $specialites = $this->specialites(array_column($lignes, 'public_id'), (string) $r['panne']);
        $resultats = array_map(fn (array $l): array => [
            'rang' => (int) $l['rang'], 'distance_m' => (int) $l['distance_m'],
            'specialiste' => (bool) $l['specialiste'], 'autre_rive' => (bool) $l['autre_rive'],
            'public_id' => (string) $l['public_id'], 'slug' => (string) $l['slug'],
            'prenom' => (string) $l['prenom'], 'nom' => (string) $l['nom'], 'metier' => (string) $l['metier'],
            'note' => $l['note_moyenne'] !== null ? (float) $l['note_moyenne'] : null, 'avis' => (int) $l['nb_avis'],
            'disponible' => $l['etat'] === 'disponible',
            'lat' => $l['dispo_lat'] !== null ? (float) $l['dispo_lat'] : null, 'lon' => $l['dispo_lon'] !== null ? (float) $l['dispo_lon'] : null,
            'quartier' => $l['quartier'] !== null ? (string) $l['quartier'] : null,
            'portrait' => PhotoService::portrait($l),
            'specialites' => $specialites[$l['public_id']]['toutes'] ?? [],
            'specialites_panne' => $specialites[$l['public_id']]['panne'] ?? [],
        ], $lignes);
        unset($r['id'], $r['quartier_lat'], $r['quartier_lon']);
        return ['recherche' => $r, 'resultats' => $resultats];
    }

    /**
     * Spécialités de chaque mécano (ordre du référentiel) et celles qui traitent la panne (en gras, RG-R6).
     *
     * @param list<string> $publicIds
     * @return array<string, array{toutes: list<string>, panne: list<string>}>
     */
    private function specialites(array $publicIds, string $panne): array
    {
        if ($publicIds === []) {
            return [];
        }
        $marques = implode(',', array_fill(0, count($publicIds), '?'));
        $lignes = $this->db->tous(
            "SELECT m.public_id, s.libelle,
                    EXISTS (SELECT 1 FROM panne_specialite ps JOIN pannes p ON p.id = ps.panne_id
                             WHERE ps.specialite_id = s.id AND p.code = ?) AS liee
               FROM mecaniciens m
               JOIN mecanicien_specialites ms ON ms.mecanicien_id = m.id
               JOIN specialites s ON s.id = ms.specialite_id
              WHERE m.public_id IN ($marques)
              ORDER BY s.ordre",
            [$panne, ...$publicIds]
        );
        $parMecano = [];
        foreach ($lignes as $l) {
            $parMecano[$l['public_id']]['toutes'][] = (string) $l['libelle'];
            if ((bool) $l['liee']) {
                $parMecano[$l['public_id']]['panne'][] = (string) $l['libelle'];
            }
        }
        return $parMecano;
    }
}
