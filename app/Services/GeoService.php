<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Calculs géographiques (RG-R4, RG-R8). Distance « à vol d'oiseau » : formule de Haversine.
 */
final class GeoService
{
    private const RAYON_TERRE_M = 6371000;
    private const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    public static function distanceM(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return (int) round(self::RAYON_TERRE_M * 2 * asin(min(1.0, sqrt($a))));
    }

    /**
     * Rectangle qui contient le cercle de rayon $km : pré-filtre SQL avant le calcul exact.
     * @return array{latMin: float, latMax: float, lonMin: float, lonMax: float}
     */
    public static function rectangle(float $lat, float $lon, float $km): array
    {
        $dLat = $km / 111.32;
        $dLon = $km / (111.32 * cos(deg2rad($lat)));
        return ['latMin' => $lat - $dLat, 'latMax' => $lat + $dLat, 'lonMin' => $lon - $dLon, 'lonMax' => $lon + $dLon];
    }

    /** Geohash (précision 6 ≈ 1,2 × 0,6 km) pour le comptage anonyme des zones sans résultat. */
    public static function geohash(float $lat, float $lon, int $precision = 6): string
    {
        $latInt = [-90.0, 90.0];
        $lonInt = [-180.0, 180.0];
        $hash = '';
        $bit = 0;
        $ch = 0;
        $pair = true;
        while (strlen($hash) < $precision) {
            if ($pair) {
                $mid = ($lonInt[0] + $lonInt[1]) / 2;
                if ($lon >= $mid) { $ch |= 1 << (4 - $bit); $lonInt[0] = $mid; } else { $lonInt[1] = $mid; }
            } else {
                $mid = ($latInt[0] + $latInt[1]) / 2;
                if ($lat >= $mid) { $ch |= 1 << (4 - $bit); $latInt[0] = $mid; } else { $latInt[1] = $mid; }
            }
            $pair = !$pair;
            if ($bit < 4) {
                $bit++;
            } else {
                $hash .= self::BASE32[$ch];
                $bit = 0;
                $ch = 0;
            }
        }
        return $hash;
    }
}
