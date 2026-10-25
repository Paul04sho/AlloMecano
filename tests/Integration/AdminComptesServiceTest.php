<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\AdminComptesService;
use PHPUnit\Framework\TestCase;

final class AdminComptesServiceTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT est_principal FROM admins LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible ou migration admin non appliquée : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    public function testLePrincipalCreeUnCompteAvecUnMotDePasseTemporaireEtUnJournalSansSecret(): void
    {
        $principal = $this->ajouterAdmin('principal-' . bin2hex(random_bytes(4)) . '@example.ci', true);

        $resultat = (new AdminComptesService($this->db))->creer($principal, [
            'prenom' => 'Nadia', 'nom' => 'Koné', 'email' => 'nadia.' . bin2hex(random_bytes(3)) . '@example.ci',
        ], '127.0.0.1');

        self::assertTrue($resultat['ok']);
        self::assertNotSame('', $resultat['mot_de_passe_temporaire']);
        $compte = $this->db->un('SELECT mot_de_passe_hash, mot_de_passe_a_changer, est_principal FROM admins WHERE id = :id', ['id' => $resultat['id']]);
        self::assertTrue(password_verify($resultat['mot_de_passe_temporaire'], (string) $compte['mot_de_passe_hash']));
        self::assertSame(1, (int) $compte['mot_de_passe_a_changer']);
        self::assertSame(0, (int) $compte['est_principal']);

        $journal = $this->db->un('SELECT action, details FROM journal_admin WHERE admin_id = :acteur AND cible_type = \'admin\' AND cible_id = :cible', [
            'acteur' => $principal, 'cible' => $resultat['id'],
        ]);
        self::assertSame('creer_admin', $journal['action']);
        self::assertStringNotContainsString($resultat['mot_de_passe_temporaire'], (string) $journal['details']);
    }

    public function testUnAdminOrdinaireNePeutPasCreerUnAutreCompte(): void
    {
        $admin = $this->ajouterAdmin('admin-' . bin2hex(random_bytes(4)) . '@example.ci', false);
        $avant = (int) $this->db->valeur('SELECT COUNT(*) FROM admins');

        $resultat = (new AdminComptesService($this->db))->creer($admin, [
            'prenom' => 'Nadia', 'nom' => 'Koné', 'email' => 'nadia-' . bin2hex(random_bytes(3)) . '@example.ci',
        ], '127.0.0.1');

        self::assertFalse($resultat['ok']);
        self::assertSame('permission', $resultat['erreur']);
        self::assertSame($avant, (int) $this->db->valeur('SELECT COUNT(*) FROM admins'));
    }

    public function testLeMotDePasseTemporaireEstRemplaceSansAncienMotDePasseEtSansJournaliserLeNouveau(): void
    {
        $admin = $this->ajouterAdmin('temp-' . bin2hex(random_bytes(4)) . '@example.ci', false, true, 'Temporaire123!');
        $nouveau = 'NouveauMot123!';

        $resultat = (new AdminComptesService($this->db))->changerMotDePasse($admin, '', $nouveau, $nouveau, '127.0.0.1');

        self::assertTrue($resultat['ok']);
        $compte = $this->db->un('SELECT mot_de_passe_hash, mot_de_passe_a_changer FROM admins WHERE id = :id', ['id' => $admin]);
        self::assertTrue(password_verify($nouveau, (string) $compte['mot_de_passe_hash']));
        self::assertSame(0, (int) $compte['mot_de_passe_a_changer']);
        $journal = (string) $this->db->valeur('SELECT details FROM journal_admin WHERE admin_id = :id AND action = \'changer_mot_de_passe_admin\'', ['id' => $admin]);
        self::assertStringNotContainsString($nouveau, $journal);
    }

    public function testUnAdminNePeutPasDesactiverSonPropreCompte(): void
    {
        $principal = $this->ajouterAdmin('principal-' . bin2hex(random_bytes(4)) . '@example.ci', true);

        $resultat = (new AdminComptesService($this->db))->changerActif($principal, $principal, false, '127.0.0.1');

        self::assertFalse($resultat['ok']);
        self::assertSame(1, (int) $this->db->valeur('SELECT actif FROM admins WHERE id = :id', ['id' => $principal]));
    }

    public function testUnAdminPeutModifierSonProfilSansChangerSonRole(): void
    {
        $principal = $this->ajouterAdmin('principal-' . bin2hex(random_bytes(4)) . '@example.ci', true);

        $resultat = (new AdminComptesService($this->db))->modifierMonCompte($principal, [
            'prenom' => 'Esmel', 'nom' => 'Rilay', 'email' => 'esmel-' . bin2hex(random_bytes(3)) . '@example.ci',
        ], '127.0.0.1');

        self::assertTrue($resultat['ok']);
        self::assertTrue((bool) $this->db->valeur('SELECT est_principal FROM admins WHERE id = :id', ['id' => $principal]));
        self::assertStringStartsWith('esmel-', (string) $this->db->valeur('SELECT email FROM admins WHERE id = :id', ['id' => $principal]));
    }

    public function testLePrincipalSeulPeutModifierEtDesactiverUnAutreCompte(): void
    {
        $principal = $this->ajouterAdmin('principal-' . bin2hex(random_bytes(4)) . '@example.ci', true);
        $ordinaire = $this->ajouterAdmin('admin-' . bin2hex(random_bytes(4)) . '@example.ci', false);
        $cible = $this->ajouterAdmin('cible-' . bin2hex(random_bytes(4)) . '@example.ci', false);
        $service = new AdminComptesService($this->db);

        self::assertSame('permission', $service->modifier($ordinaire, [
            'id' => $cible, 'prenom' => 'Nadia', 'nom' => 'Koné', 'email' => 'nouveau-' . bin2hex(random_bytes(3)) . '@example.ci',
        ])['erreur']);
        self::assertTrue($service->modifier($principal, [
            'id' => $cible, 'prenom' => 'Nadia', 'nom' => 'Koné', 'email' => 'nadia-' . bin2hex(random_bytes(3)) . '@example.ci',
        ])['ok']);
        self::assertTrue($service->changerActif($principal, $cible, false)['ok']);
        self::assertSame(0, (int) $this->db->valeur('SELECT actif FROM admins WHERE id = :id', ['id' => $cible]));
    }

    public function testLinitialisationCreeUnSeulPrincipalEtNeRegenereraPasLeMotDePasseAuSecondPassage(): void
    {
        $this->db->executer('UPDATE admins SET est_principal = 0 WHERE est_principal = 1');
        $service = new AdminComptesService($this->db);
        $email = 'proprietaire-' . bin2hex(random_bytes(4)) . '@example.ci';

        $premier = $service->initialiserPrincipal('Esmel', 'Rilay', $email);
        $second = $service->initialiserPrincipal('Esmel', 'Rilay', $email);

        self::assertTrue($premier['ok']);
        self::assertArrayHasKey('mot_de_passe_temporaire', $premier);
        self::assertTrue($second['ok']);
        self::assertTrue($second['deja_initialise']);
        self::assertArrayNotHasKey('mot_de_passe_temporaire', $second);
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM admins WHERE est_principal = 1'));
    }

    public function testSeulLePrincipalPeutReinitialiserLeMotDePasseDunAutreCompte(): void
    {
        $principal = $this->ajouterAdmin('principal-' . bin2hex(random_bytes(4)) . '@example.ci', true);
        $admin = $this->ajouterAdmin('admin-' . bin2hex(random_bytes(4)) . '@example.ci', false, false, 'AncienMot123!');
        $service = new AdminComptesService($this->db);

        $refus = $service->reinitialiserMotDePasse($admin, $admin, '127.0.0.1');
        $resultat = $service->reinitialiserMotDePasse($principal, $admin, '127.0.0.1');

        self::assertSame('permission', $refus['erreur']);
        self::assertTrue($resultat['ok']);
        self::assertNotSame('', $resultat['mot_de_passe_temporaire']);
        $compte = $this->db->un('SELECT mot_de_passe_hash, mot_de_passe_a_changer FROM admins WHERE id = :id', ['id' => $admin]);
        self::assertTrue(password_verify($resultat['mot_de_passe_temporaire'], (string) $compte['mot_de_passe_hash']));
        self::assertSame(1, (int) $compte['mot_de_passe_a_changer']);
    }

    public function testUnEmailDeMecanoNePeutPasDevenirUneAdresseDeConnexionAdmin(): void
    {
        $principal = $this->ajouterAdmin('principal-' . bin2hex(random_bytes(4)) . '@example.ci', true);
        $emailMecano = 'mecano-' . bin2hex(random_bytes(4)) . '@example.ci';
        $this->db->inserer('mecaniciens', [
            'public_id' => Ulid::generer(), 'prenom' => 'Test', 'nom' => 'Mécano', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'email' => $emailMecano, 'mot_de_passe_hash' => password_hash('AncienMot123!', PASSWORD_DEFAULT), 'metier' => 'Mécanique',
        ]);

        $resultat = (new AdminComptesService($this->db))->creer($principal, [
            'prenom' => 'Autre', 'nom' => 'Admin', 'email' => $emailMecano,
        ]);

        self::assertFalse($resultat['ok']);
        self::assertSame('email_utilise', $resultat['erreur']);
    }

    public function testUnAdminConnecteParGoogleEtSansAncienMotDePassePeutEnDefinirUn(): void
    {
        $admin = $this->db->inserer('admins', [
            'prenom' => 'Google', 'nom' => 'Admin', 'email' => 'google-' . bin2hex(random_bytes(4)) . '@example.ci',
            'mot_de_passe_hash' => null, 'google_sub' => 'google-' . bin2hex(random_bytes(12)),
        ]);

        $resultat = (new AdminComptesService($this->db))->changerMotDePasse($admin, '', 'NouveauMot123!', 'NouveauMot123!');

        self::assertTrue($resultat['ok']);
        self::assertTrue(password_verify('NouveauMot123!', (string) $this->db->valeur('SELECT mot_de_passe_hash FROM admins WHERE id = :id', ['id' => $admin])));
    }

    private function ajouterAdmin(string $email, bool $principal, bool $changementForce = false, string $motDePasse = 'AncienMot123!'): int
    {
        return $this->db->inserer('admins', [
            'prenom' => 'Esmel', 'nom' => 'Rilay', 'email' => $email,
            'mot_de_passe_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
            'est_principal' => $principal ? 1 : 0, 'mot_de_passe_a_changer' => $changementForce ? 1 : 0,
        ]);
    }
}
