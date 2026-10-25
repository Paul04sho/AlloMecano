<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\ExigerAdmin;
use PHPUnit\Framework\TestCase;

final class ExigerAdminTest extends TestCase
{
    private Database $db;
    private int $admin;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT mot_de_passe_a_changer FROM admins LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible ou migration admin non appliquée : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->admin = $this->db->inserer('admins', [
            'prenom' => 'Test', 'nom' => 'Admin', 'email' => 'verrou-' . bin2hex(random_bytes(4)) . '@example.ci',
            'mot_de_passe_hash' => password_hash('Temporaire123!', PASSWORD_DEFAULT), 'mot_de_passe_a_changer' => 1,
        ]);
        $_SESSION = ['admin_id' => $this->admin];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    public function testLeMotDePasseTemporaireBloqueLesPagesAdminEtRedirigeVersSonRemplacement(): void
    {
        $suiteAppelee = false;
        $requete = new Request('GET', '/admin/comptes', [], [], [], []);

        $reponse = (new ExigerAdmin())->traiter($requete, static function () use (&$suiteAppelee): Response {
            $suiteAppelee = true;
            return Response::html('admin');
        });

        self::assertSame('/admin/mon-compte/mot-de-passe', $reponse->entetes['Location'] ?? null);
        self::assertFalse($suiteAppelee);
    }

    public function testSeulLeFormulaireDeChangementEstAccessiblePendantLeVerrouillage(): void
    {
        $suiteAppelee = false;
        $requete = new Request('GET', '/admin/mon-compte/mot-de-passe', [], [], [], []);

        $reponse = (new ExigerAdmin())->traiter($requete, static function () use (&$suiteAppelee): Response {
            $suiteAppelee = true;
            return Response::html('formulaire');
        });

        self::assertTrue($suiteAppelee);
        self::assertSame(200, $reponse->statut);
    }
}
