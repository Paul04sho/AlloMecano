<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Fiche d'un mécano (C5, D1) : identité vérifiée (RG-DO8), photos publiques (RG-DO9), note et avis (RG-AV5, RG-AV6),
 * zone d'intervention et tarifs indicatifs, message prêt pour le premier contact.
 * Le numéro de téléphone n'est jamais lu ici : il n'est révélé qu'après la création du contact (RG-A1).
 */
final class FicheService
{
    /** « Nouveau » à la place de la note en dessous de ce nombre d'avis (RG-AV6) */
    public const AVIS_MINI_POUR_NOTE = 3;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** Une consultation de la fiche (M2 · « ont vu votre fiche ») : un client compte une fois par jour, sans rien garder de plus. */
    public function compterVue(string $mecanoPublicId, ?int $clientId): void
    {
        $this->db->executer('INSERT IGNORE INTO vues_fiche (mecanicien_id, client_id, jour) SELECT id, :c, UTC_DATE() FROM mecaniciens WHERE public_id = :p', ['c' => $clientId, 'p' => $mecanoPublicId]);
    }

    /**
     * La fiche, ou null si le dossier n'est pas validé ou si le compte est suspendu (RG-DO8).
     *
     * @param ?array{lat: float, lon: float} $depuis position du client, pour la distance si la recherche n'est plus là
     * @return ?array<string, mixed>
     */
    public function fiche(string $slug, ?int $distanceRechercheM = null, ?array $depuis = null): ?array
    {
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.slug, m.prenom, m.nom, m.metier, m.annees_experience, m.presentation, m.atelier_adresse,
                    m.rayon_km, m.tarif_diagnostic_fcfa, m.tarif_deplacement_fcfa, m.identite_verifiee_le, m.etat, m.position_maj_le,
                    m.note_moyenne, m.nb_avis, m.dispo_lat, m.dispo_lon, m.atelier_lat, m.atelier_lon,
                    qd.nom AS quartier, qa.nom AS atelier_quartier
               FROM mecaniciens m
               LEFT JOIN quartiers qd ON qd.id = m.dispo_quartier_id
               LEFT JOIN quartiers qa ON qa.id = m.atelier_quartier_id
              WHERE m.slug = :s AND m.statut_dossier = \'valide\' AND m.supprime_le IS NULL',
            ['s' => $slug]
        );
        if ($m === null) {
            return null;
        }
        $id = (int) $m['id'];
        $lat = $m['dispo_lat'] ?? $m['atelier_lat'];
        $lon = $m['dispo_lon'] ?? $m['atelier_lon'];
        $nbAvis = (int) $m['nb_avis'];
        $distance = $distanceRechercheM
            ?? ($depuis !== null && $lat !== null ? GeoService::distanceM($depuis['lat'], $depuis['lon'], (float) $lat, (float) $lon) : null);

        $photos = $this->photos($id);
        return [
            'public_id' => (string) $m['public_id'],
            'slug' => (string) $m['slug'],
            'prenom' => (string) $m['prenom'],
            'nom_complet' => $m['prenom'] . ' ' . $m['nom'],
            'metier' => (string) $m['metier'],
            'annees' => (int) $m['annees_experience'],
            'presentation' => $m['presentation'],
            // Plus disponible : sorti du service depuis la recherche, ou position trop ancienne (RG-D2, RG-D3)
            'disponible' => $this->estDisponible($m),
            'verifie_le' => $m['identite_verifiee_le'] !== null ? Format::date((string) $m['identite_verifiee_le']) : null,
            'quartier' => $m['quartier'] !== null ? (string) $m['quartier'] : null,
            'distance_m' => $distance,
            'note' => $nbAvis >= self::AVIS_MINI_POUR_NOTE && $m['note_moyenne'] !== null ? (float) $m['note_moyenne'] : null,
            'nb_avis' => $nbAvis,
            'rayon_km' => (int) $m['rayon_km'],
            'zone' => $lat !== null ? $this->zone((float) $lat, (float) $lon, (int) $m['rayon_km']) : [],
            'tarif_diagnostic' => $m['tarif_diagnostic_fcfa'] !== null ? (int) $m['tarif_diagnostic_fcfa'] : null,
            'tarif_deplacement' => $m['tarif_deplacement_fcfa'] !== null ? (int) $m['tarif_deplacement_fcfa'] : null,
            'specialites' => array_column($this->db->tous(
                'SELECT s.libelle FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id
                  WHERE ms.mecanicien_id = :m ORDER BY s.ordre',
                ['m' => $id]
            ), 'libelle'),
            'portrait' => $photos['portrait'] ?? null,
            'atelier' => $photos['atelier'] ?? null,
            'atelier_adresse' => $m['atelier_adresse'],
            'atelier_quartier' => $m['atelier_quartier'],
        ];
    }

    /** @param array<string, mixed> $m */
    private function estDisponible(array $m): bool
    {
        if ($m['etat'] !== 'disponible' || $m['position_maj_le'] === null) {
            return false;
        }
        $limite = new \DateTimeImmutable('-' . Parametres::int('dispo.duree_max_h', 12) . ' hours', new \DateTimeZone('UTC'));
        return new \DateTimeImmutable((string) $m['position_maj_le'], new \DateTimeZone('UTC')) >= $limite;
    }

    /**
     * Distance mémorisée dans la liste figée de la recherche (RG-R7), pour ce mécano.
     */
    public function distanceDansRecherche(string $recherchePublicId, int $client, string $mecanoPublicId): ?int
    {
        $d = $this->db->valeur(
            'SELECT rr.distance_m FROM resultats_recherche rr
               JOIN recherches r ON r.id = rr.recherche_id
               JOIN mecaniciens m ON m.id = rr.mecanicien_id
              WHERE r.public_id = :r AND r.client_id = :c AND m.public_id = :m',
            ['r' => $recherchePublicId, 'c' => $client, 'm' => $mecanoPublicId]
        );
        return $d === null || $d === false ? null : (int) $d;
    }

    /**
     * « Cocody, Riviera et Bingerville » : communes dont un quartier est dans le rayon d'intervention, les plus proches d'abord, trois au plus.
     *
     * @return list<string>
     */
    public function zone(float $lat, float $lon, int $rayonKm): array
    {
        $r = GeoService::rectangle($lat, $lon, (float) $rayonKm);
        $lignes = $this->db->tous(
            'SELECT co.nom AS commune, q.lat, q.lon
               FROM quartiers q JOIN communes co ON co.id = q.commune_id
              WHERE q.lat BETWEEN :latmin AND :latmax AND q.lon BETWEEN :lonmin AND :lonmax',
            ['latmin' => $r['latMin'], 'latmax' => $r['latMax'], 'lonmin' => $r['lonMin'], 'lonmax' => $r['lonMax']]
        );
        $communes = [];
        foreach ($lignes as $l) {
            $d = GeoService::distanceM($lat, $lon, (float) $l['lat'], (float) $l['lon']);
            if ($d <= $rayonKm * 1000) {
                $communes[$l['commune']] = min($communes[$l['commune']] ?? PHP_INT_MAX, $d);
            }
        }
        asort($communes);
        return array_slice(array_keys($communes), 0, 3);
    }

    /**
     * Avis publiés, les plus récents d'abord (RG-AV4, RG-AV5) : prénom (ou « Un client »), panne, date relative, jamais de numéro.
     *
     * @return array{avis: list<array<string, mixed>>, total: int}
     */
    public function avis(string $mecanoPublicId, int $limite = 2, int $decalage = 0): array
    {
        $id = (int) $this->db->valeur('SELECT id FROM mecaniciens WHERE public_id = :p', ['p' => $mecanoPublicId]);
        $total = (int) $this->db->valeur('SELECT COUNT(*) FROM avis WHERE mecanicien_id = :m AND statut = \'publie\'', ['m' => $id]);
        $lignes = $this->db->tous(
            'SELECT a.note, a.commentaire, a.prenom_affiche, a.panne_libelle, a.cree_le, a.reponse_mecano
               FROM avis a WHERE a.mecanicien_id = :m AND a.statut = \'publie\'
              ORDER BY a.cree_le DESC, a.id DESC LIMIT ' . max(1, $limite) . ' OFFSET ' . max(0, $decalage),
            ['m' => $id]
        );
        return [
            'avis' => array_map(static fn (array $a): array => [
                'note' => (int) $a['note'],
                'commentaire' => $a['commentaire'],
                'prenom' => $a['prenom_affiche'] !== null && $a['prenom_affiche'] !== '' ? (string) $a['prenom_affiche'] : 'Un client',
                'panne' => (string) $a['panne_libelle'],
                'depuis' => Format::depuis((string) $a['cree_le']),
                'reponse' => $a['reponse_mecano'],
            ], $lignes),
            'total' => $total,
        ];
    }

    /**
     * Message prêt pour le premier contact (« Ce qu'il faut lui dire », 54:675). Reprend mot pour mot le repère saisi en C2b.
     * Le lien de position est facultatif (interrupteur « Lien de ma position », RG-P1 : position effacée après 24 h).
     *
     * @param array{lieu: ?string, repere: ?string, vehicule: ?string, panne: ?string} $contexte
     * @return array{sans: string, position: ?string}
     */
    public static function message(string $prenomMecano, array $contexte, ?float $lat, ?float $lon): array
    {
        $texte = 'Bonjour ' . $prenomMecano . ', je vous contacte via allomécano.';
        if (!empty($contexte['lieu'])) {
            $lieu = $contexte['lieu'] . (!empty($contexte['repere']) ? ', ' . $contexte['repere'] : '');
            $texte .= ' Je suis en panne à ' . $lieu . '.';
        }
        $nature = array_filter([
            $contexte['vehicule'] ?? null,
            !empty($contexte['panne']) ? mb_strtolower(mb_substr($contexte['panne'], 0, 1)) . mb_substr($contexte['panne'], 1) : null,
        ]);
        if ($nature !== []) {
            $texte .= ' ' . implode(', ', $nature) . '.';
        }
        $position = $lat !== null && $lon !== null
            ? 'Ma position' . Format::FINE . ': https://osm.org/?mlat=' . number_format($lat, 4, '.', '') . '&mlon=' . number_format($lon, 4, '.', '')
            : null;
        return ['sans' => $texte, 'position' => $position];
    }

    /** @return array<string, array<string, mixed>> portrait et atelier actifs, indexés par type */
    private function photos(int $mecanoId): array
    {
        $out = [];
        foreach ($this->db->tous(
            'SELECT type, dossier, largeurs, ratio, couleur_dominante, focal_x, focal_y FROM photos WHERE mecanicien_id = :m AND active = 1',
            ['m' => $mecanoId]
        ) as $p) {
            $out[$p['type']] = PhotoService::portrait($p);
        }
        return $out;
    }
}
