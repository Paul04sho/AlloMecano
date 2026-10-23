<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AccueilService;
use PHPUnit\Framework\TestCase;

final class AccueilServiceTest extends TestCase
{
    private static function ligne(string $nom, int $nombre, int $ordre, bool $ouverte = true): array
    {
        return ['nom' => $nom, 'slug' => strtolower($nom), 'ouverte' => $ouverte ? 1 : 0, 'ordre' => $ordre, 'nombre' => $nombre];
    }

    public function testLesCommunesFourniesPassentDAbordPuisAucunPuisBientot(): void
    {
        $communes = AccueilService::ordonner([
            self::ligne('Songon', 0, 11, false),
            self::ligne('Attécoubé', 0, 4),
            self::ligne('Marcory', 9, 8),
            self::ligne('Cocody', 18, 6),
            self::ligne('Abobo', 0, 1),
        ]);
        self::assertSame(['Cocody', 'Marcory', 'Abobo', 'Attécoubé', 'Songon'], array_column($communes, 'nom'));
        self::assertSame(['disponibles', 'disponibles', 'aucun', 'aucun', 'bientot'], array_column($communes, 'etat'));
    }

    public function testAEgaliteLOrdreDuReferentielDecide(): void
    {
        $communes = AccueilService::ordonner([self::ligne('Plateau', 2, 9), self::ligne('Adjamé', 2, 2)]);
        self::assertSame(['Adjamé', 'Plateau'], array_column($communes, 'nom'));
    }

    public function testUneCommuneFermeeNAfficheJamaisDeNombre(): void
    {
        $communes = AccueilService::ordonner([self::ligne('Songon', 3, 11, false)]);
        self::assertSame('bientot', $communes[0]['etat']);
        self::assertSame(0, $communes[0]['nombre']);
        self::assertArrayNotHasKey('ordre', $communes[0]);
    }

    public function testLeTrombinoscopeAQuatreMecanosAvecLeurPhoto(): void
    {
        $trombi = AccueilService::trombinoscope();
        self::assertCount(4, $trombi);
        foreach ($trombi as $m) {
            self::assertFileExists(RACINE . '/public/assets/img/accueil/' . $m['image'] . '-302.webp');
        }
    }
}
