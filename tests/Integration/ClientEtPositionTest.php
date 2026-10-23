<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\ClientService;
use App\Services\PositionService;
use PHPUnit\Framework\TestCase;

/** Client anonyme (RG-C7) et quartiers (RG-R1), dans une transaction annulée à la fin. */
final class ClientEtPositionTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM quartiers LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    public function testUnClientNeufRecoitUnJetonStockeEnEmpreinte(): void
    {
        $service = new ClientService($this->db);
        $neuf = $service->identifier(null);
        self::assertNotNull($neuf['nouveau']);
        self::assertNull($this->db->valeur('SELECT id FROM clients WHERE jeton_hash = :j', ['j' => $neuf['nouveau']]), 'le jeton en clair n’est jamais stocké');

        $retour = $service->identifier($neuf['nouveau']);
        self::assertSame($neuf['id'], $retour['id']);
        self::assertNull($retour['nouveau']);
    }

    public function testRestaurerReprendLAncienClientEtSupprimeLeProvisoire(): void
    {
        $service = new ClientService($this->db);
        $ancien = $service->identifier(null);
        $provisoire = $service->identifier(null); // cookie perdu : un client neuf a été créé
        self::assertSame($ancien['id'], $service->restaurer($ancien['nouveau'], $provisoire['id']));
        self::assertNull($this->db->valeur('SELECT id FROM clients WHERE id = :id', ['id' => $provisoire['id']]));
        self::assertNull($service->restaurer('invalide', null));
    }

    public function testUnJetonInconnuEstReenregistreTelQuel(): void
    {
        $service = new ClientService($this->db);
        $jeton = ClientService::genererJeton();
        $id = $service->restaurer($jeton, null);
        self::assertSame($id, $service->identifier($jeton)['id']);
    }

    public function testLesTreizeCommunesOuvertesOntDesQuartiers(): void
    {
        $service = new PositionService($this->db);
        $parCommune = array_count_values(array_column($service->quartiers(), 'commune'));
        foreach ($service->communes() as $c) {
            self::assertGreaterThan(0, $parCommune[$c['slug']] ?? 0, $c['nom']);
        }
    }

    public function testLeQuartierLePlusProcheNommeLaPosition(): void
    {
        $q = (new PositionService($this->db))->quartierLePlusProche(5.3571, -3.9672);
        self::assertSame('cocody-riviera-2', $q['slug']);
        self::assertSame('Cocody', $q['commune_nom']);
    }

    public function testChercherParNomEtParAlias(): void
    {
        $service = new PositionService($this->db);
        self::assertSame('cocody-riviera-2', $service->chercher('riv 2')[0]['slug'] ?? null);
        self::assertSame('marcory-zone-4', $service->chercher('Zone IV')[0]['slug'] ?? null);
        self::assertSame('yopougon-sideci', $service->chercher('sidéci')[0]['slug'] ?? null);
        self::assertSame([], $service->chercher('   '));
    }
}
