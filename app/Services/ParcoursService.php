<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

/**
 * Parcours en cours (C2 → C3 → recherche), gardé en session. « Annuler » ne l'efface pas : la saisie reste
 * en mémoire (Barre de parcours 53:374). La position exacte n'est écrite en base qu'à la recherche, puis
 * effacée au bout de 24 h (RG-P1).
 *
 * @phpstan-type Position array{source: string, lat: float, lon: float, precision_m: ?int, quartier: ?string, repere: ?string}
 */
final class ParcoursService
{
    private const CLE = 'parcours';
    public const SOURCES = ['gps', 'quartier', 'carte']; // RG-R1, dans cet ordre de préférence
    private const REPERE_MAX = 160;

    /** @return array{position?: array<string, mixed>, gps?: array<string, mixed>, vehicule?: string, panne?: string} */
    public static function lire(): array
    {
        $p = Session::get(self::CLE, []);
        return is_array($p) ? $p : [];
    }

    /** Mesure GPS brute (C2a), avant que le client confirme le point sur la carte. */
    public static function enregistrerGps(float $lat, float $lon, ?int $precisionM): void
    {
        self::ecrire(['gps' => ['lat' => $lat, 'lon' => $lon, 'precision_m' => $precisionM]]);
    }

    /** @return array{lat: float, lon: float, precision_m: ?int}|null */
    public static function gps(): ?array
    {
        $g = self::lire()['gps'] ?? null;
        return is_array($g) ? $g : null;
    }

    /**
     * Position retenue pour la recherche.
     *
     * @param string $source gps | quartier | carte
     */
    public static function enregistrerPosition(string $source, float $lat, float $lon, ?int $precisionM, ?string $quartier, ?string $repere): void
    {
        if (!in_array($source, self::SOURCES, true)) {
            throw new \InvalidArgumentException("Source de position inconnue : $source");
        }
        $repere = self::nettoyerRepere($repere);
        self::ecrire(['position' => compact('source', 'lat', 'lon', 'quartier', 'repere') + ['precision_m' => $precisionM]]);
    }

    /** @return array{source: string, lat: float, lon: float, precision_m: ?int, quartier: ?string, repere: ?string}|null */
    public static function position(): ?array
    {
        $p = self::lire()['position'] ?? null;
        return is_array($p) ? $p : null;
    }

    /** Véhicule et panne déjà choisis sur l'accueil desktop (D0), repris en C3. Valeurs déjà validées. */
    public static function enregistrerChoix(?string $vehicule, ?string $panne): void
    {
        self::ecrire(array_filter(['vehicule' => $vehicule, 'panne' => $panne], static fn ($v) => $v !== null && $v !== ''));
    }

    public static function nettoyerRepere(?string $repere): ?string
    {
        $r = trim((string) preg_replace('/\s+/u', ' ', (string) $repere));
        return $r === '' ? null : mb_substr($r, 0, self::REPERE_MAX);
    }

    /** @param array<string, mixed> $valeurs */
    private static function ecrire(array $valeurs): void
    {
        Session::set(self::CLE, $valeurs + self::lire());
    }
}
