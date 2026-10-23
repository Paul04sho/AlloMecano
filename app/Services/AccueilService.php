<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Données de l'accueil (C1 · D0) : tableau des communes, pannes du formulaire desktop, trombinoscope.
 */
final class AccueilService
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Tableau des communes (Ligne commune 56:734) : nombre RÉEL de mécanos disponibles maintenant.
     * Mêmes critères d'éligibilité que la recherche (RG-R3) ; le véhicule ne filtre pas ici (RG-R2 ne s'applique
     * qu'à une recherche). Un mécano compte dans la commune du quartier où il s'est rendu disponible.
     *
     * @return list<array{nom: string, slug: string, etat: string, nombre: int}>
     */
    public function communes(): array
    {
        $lignes = $this->db->tous(
            'SELECT co.nom, co.slug, co.ouverte, co.ordre, COUNT(m.id) AS nombre
               FROM communes co
               LEFT JOIN quartiers q ON q.commune_id = co.id
               LEFT JOIN mecaniciens m ON m.dispo_quartier_id = q.id
                    AND m.statut_dossier = \'valide\'
                    AND m.etat = \'disponible\'
                    AND m.supprime_le IS NULL
                    AND m.position_maj_le >= UTC_TIMESTAMP() - INTERVAL :heures HOUR
              GROUP BY co.id, co.nom, co.slug, co.ouverte, co.ordre',
            ['heures' => Parametres::int('dispo.duree_max_h', 12)]
        );
        return self::ordonner($lignes);
    }

    /**
     * Ordre d'affichage : communes avec des mécanos (les plus fournies d'abord), puis « aucun pour l'instant »,
     * puis les communes pas encore ouvertes (« Bientôt »). À égalité : l'ordre du référentiel.
     *
     * @param list<array<string, mixed>> $lignes nom, slug, ouverte, ordre, nombre
     * @return list<array{nom: string, slug: string, etat: string, nombre: int}>
     */
    public static function ordonner(array $lignes): array
    {
        $rang = ['disponibles' => 0, 'aucun' => 1, 'bientot' => 2];
        $communes = array_map(static function (array $l): array {
            $nombre = (int) $l['nombre'];
            $etat = !(bool) $l['ouverte'] ? 'bientot' : ($nombre > 0 ? 'disponibles' : 'aucun');
            return ['nom' => (string) $l['nom'], 'slug' => (string) $l['slug'], 'etat' => $etat,
                'nombre' => $etat === 'bientot' ? 0 : $nombre, 'ordre' => (int) $l['ordre']];
        }, $lignes);
        usort($communes, static fn (array $a, array $b): int =>
            [$rang[$a['etat']], -$a['nombre'], $a['ordre']] <=> [$rang[$b['etat']], -$b['nombre'], $b['ordre']]);
        return array_map(static function (array $c): array {
            unset($c['ordre']);
            return $c;
        }, $communes);
    }

    /**
     * Véhicules du segment du formulaire desktop (le véhicule filtre strictement la recherche, RG-R2).
     *
     * @return array<string, string> code => libellé
     */
    public function vehicules(): array
    {
        return array_column($this->db->tous('SELECT code, libelle FROM types_vehicule ORDER BY ordre'), 'libelle', 'code');
    }

    /**
     * Pannes proposées dans le menu du formulaire desktop (même liste que les tuiles de C3).
     *
     * @return list<array{code: string, libelle: string, icone: string, danger: bool}>
     */
    public function pannes(): array
    {
        $lignes = $this->db->tous('SELECT code, libelle, est_danger FROM pannes WHERE actif = 1 ORDER BY ordre');
        return array_map(static fn (array $p): array => [
            'code' => (string) $p['code'],
            'libelle' => (string) $p['libelle'],
            'icone' => 'voyant-' . str_replace('_', '-', (string) $p['code']),
            'danger' => (bool) $p['est_danger'],
        ], $lignes);
    }

    /**
     * Trombinoscope « Ils réparent près de vous » : contenu éditorial (photos IMG-02 à IMG-05 du Figma),
     * pas tiré de la base. En production : seulement des mécanos vérifiés qui ont donné leur accord.
     *
     * @return list<array{nom: string, image: string, court: string, metiers: string, quartier: string}>
     */
    public static function trombinoscope(): array
    {
        return [
            ['nom' => 'Koffi Yao', 'image' => 'img-02', 'court' => 'Électricité auto', 'metiers' => 'Électricité auto', 'quartier' => 'Riviera 3'],
            ['nom' => 'Adama Traoré', 'image' => 'img-03', 'court' => 'Moto', 'metiers' => 'Mécanique générale · Moto', 'quartier' => 'Abobo'],
            ['nom' => 'Awa Koné', 'image' => 'img-04', 'court' => 'Pneus', 'metiers' => 'Pneus · Batteries', 'quartier' => 'Koumassi'],
            ['nom' => 'Serge Kouassi', 'image' => 'img-05', 'court' => 'Diesel', 'metiers' => 'Diesel · Utilitaires', 'quartier' => 'Marcory'],
        ];
    }
}
