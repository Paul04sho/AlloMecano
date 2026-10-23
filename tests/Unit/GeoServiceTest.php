<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GeoService;
use PHPUnit\Framework\TestCase;

final class GeoServiceTest extends TestCase
{
    public function testDistanceHaversineEntreRiviera2EtRiviera3(): void
    {
        // Même valeur que la requête SQL testée sur MariaDB (docs/modele-donnees.html, section 7)
        self::assertSame(1359, GeoService::distanceM(5.3571, -3.9672, 5.3670, -3.9600));
    }

    public function testRectangleContientLeCercle(): void
    {
        $r = GeoService::rectangle(5.3571, -3.9672, 2);
        self::assertGreaterThan(GeoService::distanceM(5.3571, -3.9672, $r['latMax'], -3.9672), 1999);
        self::assertLessThan($r['lonMin'], -3.99);
    }

    public function testGeohashPrecision6(): void
    {
        $h = GeoService::geohash(5.3571, -3.9672);
        self::assertSame(6, strlen($h));
        self::assertSame($h, GeoService::geohash(5.3572, -3.9671), 'deux points à 15 m tombent dans la même zone');
    }
}
