<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClientService;
use App\Services\ParcoursService;
use App\Services\PositionService;
use PHPUnit\Framework\TestCase;

final class PositionEtParcoursTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testUnePositionDAbidjanEstAcceptee(): void
    {
        $p = PositionService::valider(['lat' => '5.3571', 'lon' => '-3.9672', 'precision' => '18']);
        self::assertSame(['lat' => 5.3571, 'lon' => -3.9672, 'precision_m' => 18], $p);
    }

    public function testHorsDAbidjanOnLeDit(): void
    {
        self::assertSame('hors_abidjan', PositionService::valider(['lat' => '7.69', 'lon' => '-5.03'])); // Bouaké
    }

    public function testUnePositionIllisibleEstRefusee(): void
    {
        self::assertIsString(PositionService::valider(['lat' => 'abc', 'lon' => '-3.9']));
        self::assertNotSame('hors_abidjan', PositionService::valider(['lon' => '-3.9']));
    }

    public function testLaPrecisionEstPlafonneeEtFacultative(): void
    {
        self::assertSame(5000, PositionService::valider(['lat' => 5.3, 'lon' => -4.0, 'precision' => 90000])['precision_m']);
        self::assertNull(PositionService::valider(['lat' => 5.3, 'lon' => -4.0, 'precision' => '-3'])['precision_m']);
    }

    public function testCoordonneesAuFormatFrancais(): void
    {
        self::assertSame("5,3571°\u{00A0}N · 3,9672°\u{00A0}O · ±\u{00A0}18\u{00A0}m", PositionService::coordonnees(5.3571, -3.9672, 18));
        self::assertSame("5,3571°\u{00A0}N · 3,9672°\u{00A0}O", PositionService::coordonnees(5.3571, -3.9672, null));
    }

    public function testNormaliserIgnoreAccentsCasseEtTirets(): void
    {
        self::assertSame('angre 8e tranche', PositionService::normaliser('  Angré   8e-Tranche '));
    }

    public function testLeParcoursGardeLaPositionEtLeRepere(): void
    {
        ParcoursService::enregistrerChoix('voiture', '');
        ParcoursService::enregistrerPosition('quartier', 5.36, -3.96, null, 'cocody-riviera-2', "  devant   la pharmacie\n des Jardins ");
        $p = ParcoursService::position();
        self::assertSame('quartier', $p['source']);
        self::assertSame('devant la pharmacie des Jardins', $p['repere']);
        self::assertSame('voiture', ParcoursService::lire()['vehicule']);
        self::assertArrayNotHasKey('panne', ParcoursService::lire());
    }

    public function testUneSourceInconnueEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ParcoursService::enregistrerPosition('ip', 5.36, -3.96, null, null, null);
    }

    public function testLeJetonClientFait32OctetsEnBase64url(): void
    {
        $j = ClientService::genererJeton();
        self::assertTrue(ClientService::jetonValide($j));
        self::assertSame(32, strlen((string) base64_decode(strtr($j, '-_', '+/'), true)));
        self::assertNotSame($j, ClientService::genererJeton());
        self::assertFalse(ClientService::jetonValide('trop-court'));
        self::assertSame(64, strlen(ClientService::empreinte($j)));
    }
}
