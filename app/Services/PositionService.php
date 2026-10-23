<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Position du client (RG-R1) : GPS, quartier choisi (centre du quartier), ou point déplacé sur la carte.
 * La position exacte ne passe jamais dans une URL ; elle est effacée 24 h après la recherche (RG-P1).
 */
final class PositionService
{
    /** District d'Abidjan, avec une marge : au-delà, on propose de choisir sa commune. */
    public const CADRE = ['latMin' => 5.15, 'latMax' => 5.65, 'lonMin' => -4.45, 'lonMax' => -3.70];
    /** RG-R1 : au-delà de cette précision, on invite à ajuster le point sur la carte. */
    public const PRECISION_AJUSTER_M = 300;
    private const PRECISION_MAX_M = 5000;

    /** @var list<array{slug: string, nom: string, alias: ?string, lat: float, lon: float, commune: string, commune_nom: string}>|null */
    private ?array $quartiers = null;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    public static function dansAbidjan(float $lat, float $lon): bool
    {
        $c = self::CADRE;
        return $lat >= $c['latMin'] && $lat <= $c['latMax'] && $lon >= $c['lonMin'] && $lon <= $c['lonMax'];
    }

    /**
     * Valide une position envoyée par le navigateur.
     *
     * @param array<string, mixed> $donnees lat, lon, precision (mètres)
     * @return array{lat: float, lon: float, precision_m: ?int}|string position propre, ou le message d'erreur
     */
    public static function valider(array $donnees): array|string
    {
        $lat = filter_var($donnees['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lon = filter_var($donnees['lon'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lon === false) {
            return 'Position illisible. Réessayez, ou choisissez votre commune.';
        }
        if (!self::dansAbidjan($lat, $lon)) {
            return 'hors_abidjan';
        }
        $precision = filter_var($donnees['precision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return [
            'lat' => round($lat, 6),
            'lon' => round($lon, 6),
            'precision_m' => $precision === false ? null : min($precision, self::PRECISION_MAX_M),
        ];
    }

    /**
     * Tous les quartiers proposés, avec leur commune (liste du C2b, gardée hors connexion).
     *
     * @return list<array{slug: string, nom: string, alias: ?string, lat: float, lon: float, commune: string, commune_nom: string}>
     */
    public function quartiers(): array
    {
        return $this->quartiers ??= array_map(static fn (array $q): array => [
            'slug' => (string) $q['slug'], 'nom' => (string) $q['nom'], 'alias' => $q['alias'] !== null ? (string) $q['alias'] : null,
            'lat' => (float) $q['lat'], 'lon' => (float) $q['lon'],
            'commune' => (string) $q['commune'], 'commune_nom' => (string) $q['commune_nom'],
        ], $this->db->tous(
            'SELECT q.slug, q.nom, q.alias, q.lat, q.lon, co.slug AS commune, co.nom AS commune_nom
               FROM quartiers q JOIN communes co ON co.id = q.commune_id
              WHERE q.actif = 1 AND co.ouverte = 1
              ORDER BY co.ordre, q.id' // ordre du référentiel : les plus connus d'abord
        ));
    }

    /** @return list<array{slug: string, nom: string, lat: float, lon: float}> */
    public function communes(): array
    {
        return array_map(static fn (array $c): array => ['slug' => (string) $c['slug'], 'nom' => (string) $c['nom'], 'lat' => (float) $c['lat'], 'lon' => (float) $c['lon']],
            $this->db->tous('SELECT slug, nom, lat, lon FROM communes WHERE ouverte = 1 ORDER BY ordre'));
    }

    /** @return array{slug: string, nom: string, alias: ?string, lat: float, lon: float, commune: string, commune_nom: string}|null */
    public function quartier(string $slug): ?array
    {
        foreach ($this->quartiers() as $q) {
            if ($q['slug'] === $slug) {
                return $q;
            }
        }
        return null;
    }

    /** @return array{slug: string, nom: string, lat: float, lon: float}|null */
    public function commune(string $slug): ?array
    {
        foreach ($this->communes() as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Quartier le plus proche d'un point (pour nommer la position : « Riviera 2, Cocody »). Haversine (RG-R4).
     *
     * @return array{slug: string, nom: string, alias: ?string, lat: float, lon: float, commune: string, commune_nom: string, distance_m: int}|null
     */
    public function quartierLePlusProche(float $lat, float $lon): ?array
    {
        $meilleur = null;
        foreach ($this->quartiers() as $q) {
            $d = GeoService::distanceM($lat, $lon, $q['lat'], $q['lon']);
            if ($meilleur === null || $d < $meilleur['distance_m']) {
                $meilleur = $q + ['distance_m' => $d];
            }
        }
        return $meilleur;
    }

    /**
     * Recherche par nom, alias ou commune, sans accents ni casse (« riv 2 » → Riviera 2). Sert sans JS ;
     * avec JS, la même recherche se fait dans la page.
     *
     * @return list<array{slug: string, nom: string, alias: ?string, lat: float, lon: float, commune: string, commune_nom: string}>
     */
    public function chercher(string $texte): array
    {
        $cle = self::normaliser($texte);
        if ($cle === '') {
            return [];
        }
        $resultats = [];
        foreach ($this->quartiers() as $q) {
            $champs = array_map([self::class, 'normaliser'], [$q['nom'], (string) $q['alias'], $q['commune_nom']]);
            foreach ($champs as $i => $champ) {
                $pos = mb_strpos($champ, $cle);
                if ($pos !== false) {
                    $resultats[] = ['q' => $q, 'rang' => [$i === 2 ? 1 : 0, $pos === 0 ? 0 : 1, mb_strlen($q['nom'])]];
                    break;
                }
            }
        }
        usort($resultats, static fn ($a, $b) => $a['rang'] <=> $b['rang']);
        return array_column($resultats, 'q');
    }

    public static function normaliser(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        $t = strtr($t, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '’' => "'", '-' => ' ']);
        return (string) preg_replace('/\s+/', ' ', $t);
    }

    /** « 5,3571° N · 3,9672° O · ± 18 m » (Mono/Donnée, en capitales à l'affichage). */
    public static function coordonnees(float $lat, float $lon, ?int $precisionM): string
    {
        $deg = fn (float $v) => number_format(abs($v), 4, ',', '') . '°' . Format::INSECABLE;
        $texte = $deg($lat) . ($lat >= 0 ? 'N' : 'S') . ' · ' . $deg($lon) . ($lon >= 0 ? 'E' : 'O');
        return $precisionM === null ? $texte : $texte . ' · ±' . Format::INSECABLE . $precisionM . Format::INSECABLE . 'm';
    }
}
