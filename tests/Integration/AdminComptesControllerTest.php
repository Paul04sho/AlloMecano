<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\Admin\AdminComptesController;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class AdminComptesControllerTest extends TestCase
{
    private Database $db;
    private int $principal;
    private int $ordinaire;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT est_principal FROM admins LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible ou migration admin non appliquée : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->principal = $this->ajouterAdmin(true);
        $this->ordinaire = $this->ajouterAdmin(false);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    public function testLaPageDeGestionEtSonEntreeSontReserveesAuPrincipal(): void
    {
        $_SESSION = ['admin_id' => $this->principal];
        $reponse = (new AdminComptesController())->liste($this->requete('/admin/administrateurs'));

        self::assertSame(200, $reponse->statut);
        self::assertStringContainsString('Ajouter un administrateur', $reponse->corps);
        self::assertStringContainsString('/admin/administrateurs', $reponse->corps);

        $_SESSION = ['admin_id' => $this->ordinaire];
        try {
            (new AdminComptesController())->liste($this->requete('/admin/administrateurs'));
            self::fail('Un admin ordinaire ne doit pas pouvoir ouvrir la gestion des comptes.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->statut);
        }
    }

    public function testLeComptePersonnelEstAccessibleAUnAdminOrdinaire(): void
    {
        $_SESSION = ['admin_id' => $this->ordinaire];

        $reponse = (new AdminComptesController())->monCompte($this->requete('/admin/mon-compte'));

        self::assertSame(200, $reponse->statut);
        self::assertStringContainsString('Mon compte', $reponse->corps);
        self::assertStringContainsString('/admin/mon-compte/mot-de-passe', $reponse->corps);
        self::assertStringNotContainsString('href="/admin/administrateurs"', $reponse->corps);
    }

    public function testLeChangementInitialNeDemandePasLeMotDePasseTemporaireDansLeFormulaire(): void
    {
        $_SESSION = ['admin_id' => $this->ordinaire];
        $this->db->modifier('admins', ['mot_de_passe_a_changer' => 1], ['id' => $this->ordinaire]);

        $reponse = (new AdminComptesController())->motDePasse($this->requete('/admin/mon-compte/mot-de-passe'));

        self::assertStringContainsString('remplacez le mot de passe temporaire', $reponse->corps);
        self::assertStringNotContainsString('name="mot_de_passe_actuel"', $reponse->corps);
        self::assertStringContainsString('name="mot_de_passe"', $reponse->corps);
    }

    public function testLeMotDePasseTemporaireNestAfficheQuuneFoisEtLaPageNestPasMiseEnCache(): void
    {
        $_SESSION = ['admin_id' => $this->principal];
        Session::flash('mot_de_passe_temporaire', 'Temporaire-a-copier-123!');

        $controleur = new AdminComptesController();
        $premiere = $controleur->liste($this->requete('/admin/administrateurs'));
        $seconde = $controleur->liste($this->requete('/admin/administrateurs'));

        self::assertStringContainsString('Temporaire-a-copier-123!', $premiere->corps);
        self::assertSame('no-store', $premiere->entetes['Cache-Control'] ?? null);
        self::assertStringNotContainsString('Temporaire-a-copier-123!', $seconde->corps);
    }

    private function ajouterAdmin(bool $principal): int
    {
        return $this->db->inserer('admins', [
            'prenom' => 'Esmel', 'nom' => 'Rilay', 'email' => 'vue-' . bin2hex(random_bytes(4)) . '@example.ci',
            'mot_de_passe_hash' => password_hash('Temporaire123!', PASSWORD_DEFAULT), 'est_principal' => $principal ? 1 : 0,
        ]);
    }

    private function requete(string $chemin): Request
    {
        return new Request('GET', $chemin, [], [], [], []);
    }
}
