<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\MecanoService;
use PHPUnit\Framework\TestCase;

/** Espace pro : le compte du mécano connecté (RG-C1), jamais un compte supprimé (RG-C6). Tout est annulé à la fin. */
final class MecanoServiceTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT date_naissance FROM mecaniciens LIMIT 1');
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

    /** @param array<string, mixed> $champs */
    private function mecano(array $champs = []): int
    {
        return $this->db->inserer('mecaniciens', $champs + ['public_id' => Ulid::generer(), 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Test', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'cree_le' => '2026-02-01 08:47:00']);
    }

    public function testLeCompteDuMecanoConnecteSeLitParSonIdentifiantInterne(): void
    {
        $id = $this->mecano(['etat' => 'disponible']);
        $c = (new MecanoService($this->db))->compte($id);
        self::assertSame(['id' => $id, 'prenom' => 'Koffi', 'nom' => 'Yao', 'statut_dossier' => 'valide', 'etat' => 'disponible'], array_diff_key($c, ['public_id' => 1]));
        self::assertSame(26, strlen($c['public_id']));
    }

    public function testUnCompteSupprimeOuInconnuNOuvrePlusLEspacePro(): void
    {
        $service = new MecanoService($this->db);
        $supprime = $this->mecano(['supprime_le' => '2026-10-01 00:00:00']);
        self::assertNull($service->compte($supprime));
        self::assertNull($service->compte(99999999));
    }
}
