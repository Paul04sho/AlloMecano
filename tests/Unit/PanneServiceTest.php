<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PanneService;
use PHPUnit\Framework\TestCase;

final class PanneServiceTest extends TestCase
{
    private const VEHICULES = ['voiture', 'moto', 'camion'];
    private const PANNES = ['batterie', 'choc_accident', 'je_ne_sais_pas'];

    public function testUnePanneCompleteEstPropre(): void
    {
        $r = PanneService::valider(['vehicule' => 'moto', 'panne' => 'batterie', 'precisions' => "  ça fait\n clic ", 'prenom' => ' Aminata ', 'telephone' => '07 07 12 34 56'], self::VEHICULES, self::PANNES);
        self::assertSame([], $r['erreurs']);
        self::assertSame(['vehicule' => 'moto', 'panne' => 'batterie', 'precisions' => 'ça fait clic', 'prenom' => 'Aminata', 'telephone' => '+2250707123456'], $r['propre']);
    }

    public function testVehiculeEtPanneSontObligatoiresEtConnus(): void
    {
        $r = PanneService::valider(['vehicule' => 'avion', 'panne' => ''], self::VEHICULES, self::PANNES);
        self::assertSame('Choisissez votre véhicule.', $r['erreurs']['vehicule']); // RG-R2 : filtre strict
        self::assertSame('Choisissez ce qui ne va pas.', $r['erreurs']['panne']);
        self::assertNull($r['propre']['vehicule']);
    }

    public function testLeTelephoneEstFacultatifMaisValideSIlEstDonne(): void
    {
        self::assertArrayNotHasKey('telephone', PanneService::valider(['vehicule' => 'voiture', 'panne' => 'batterie', 'telephone' => ''], self::VEHICULES, self::PANNES)['erreurs']);
        $r = PanneService::valider(['vehicule' => 'voiture', 'panne' => 'batterie', 'telephone' => '07 07 12'], self::VEHICULES, self::PANNES);
        self::assertStringContainsString('Il manque', $r['erreurs']['telephone']);
    }

    public function testLesPrecisionsSontLimitees(): void
    {
        $r = PanneService::valider(['vehicule' => 'voiture', 'panne' => 'batterie', 'precisions' => str_repeat('a', 301)], self::VEHICULES, self::PANNES);
        self::assertArrayHasKey('precisions', $r['erreurs']);
    }

    public function testChaquePanneASonExemple(): void
    {
        self::assertSame('ex. choc à l’arrière, le moteur tourne encore', PanneService::exemple('choc_accident'));
        self::assertSame(PanneService::EXEMPLES['batterie'], PanneService::exemple('inconnue'));
        self::assertCount(9, PanneService::EXEMPLES);
    }
}
