<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\MessageContactService;
use App\Services\ReinitialisationService;
use PHPUnit\Framework\TestCase;

/** Connexion (RG-C1, RG-C2), mot de passe oublié (RG-C3) et formulaire Contact (RG-CT1). Tout est annulé à la fin. */
final class ConnexionEtMotDePasseTest extends TestCase
{
    private Database $db;
    private string $journal;
    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM tentatives_connexion LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->db->executer('DELETE FROM tentatives_connexion');
        $this->db->executer('DELETE FROM jetons_reinitialisation');
        $this->db->executer('DELETE FROM messages_contact');
        $this->db->executer('DELETE FROM admins');
        $this->db->executer('DELETE FROM mecaniciens WHERE telephone = :t', ['t' => '+2250799990001']);
        $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'admin@allomecano.test', 'mot_de_passe_hash' => password_hash('Demo2026!', PASSWORD_DEFAULT)]);
        $this->db->inserer('mecaniciens', [
            'public_id' => \App\Core\Ulid::generer(), 'slug' => 'auth-koffi', 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Test', 'telephone' => '+2250799990001', 'email' => 'koffi.yao@gmail.com',
            'mot_de_passe_hash' => password_hash('Demo2026!', PASSWORD_DEFAULT), 'statut_dossier' => 'valide', 'etat' => 'indisponible',
        ]);
        $this->journal = sys_get_temp_dir() . '/mail-' . bin2hex(random_bytes(4)) . '.log';
        $this->t0 = new \DateTimeImmutable('2026-10-23 10:00:00', new \DateTimeZone('UTC'));
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        if (isset($this->journal) && is_file($this->journal)) {
            unlink($this->journal);
        }
    }

    private function reinit(): ReinitialisationService
    {
        return new ReinitialisationService($this->db, new MailService($this->journal));
    }

    private function jetonEnvoye(): string
    {
        preg_match('#/mot-de-passe/nouveau/([0-9a-f]{64})#', (string) file_get_contents($this->journal), $m);
        return $m[1];
    }

    public function testNormaliserTelephoneEtEmail(): void
    {
        self::assertSame(['type' => 'telephone', 'valeur' => '+2250707123456'], AuthService::normaliser('07 07 12 34 56'));
        self::assertSame(['type' => 'telephone', 'valeur' => '+2250707123456'], AuthService::normaliser('+225 07 07 12 34 56'));
        self::assertSame(['type' => 'email', 'valeur' => 'koffi.yao@gmail.com'], AuthService::normaliser(' Koffi.Yao@Gmail.com '));
        self::assertNull(AuthService::normaliser('0707'));
        self::assertNull(AuthService::normaliser('pas un e-mail@'));
        self::assertNull(AuthService::normaliser(''));
    }

    public function testConnexionAdminEtMecano(): void
    {
        $auth = new AuthService($this->db);
        $a = $auth->connecter('admin@allomecano.test', 'Demo2026!', '127.0.0.1', $this->t0);
        self::assertTrue($a['ok']);
        self::assertSame('admin', $a['profil']);
        $m = $auth->connecter('07 99 99 00 01', 'Demo2026!', '127.0.0.1', $this->t0);
        self::assertTrue($m['ok']);
        self::assertSame('mecano', $m['profil']);
        $m2 = $auth->connecter('koffi.yao@gmail.com', 'Demo2026!', '127.0.0.1', $this->t0); // le mécano peut aussi se connecter par e-mail
        self::assertSame('mecano', $m2['profil']);
    }

    public function testCinqEchecsBloquentQuinzeMinutes(): void
    {
        $auth = new AuthService($this->db);
        $r = $auth->connecter('admin@allomecano.test', 'faux1', '127.0.0.1', $this->t0);
        self::assertSame(['ok' => false, 'erreur' => 'mot_de_passe', 'restant' => 4], $r); // « Encore 4 essais » (Figma 128:104)
        for ($i = 2; $i <= 4; $i++) {
            $auth->connecter('admin@allomecano.test', "faux$i", '127.0.0.1', $this->t0);
        }
        $cinq = $auth->connecter('admin@allomecano.test', 'faux5', '127.0.0.1', $this->t0);
        self::assertSame('bloque', $cinq['erreur']);
        // même le bon mot de passe ne passe plus
        $bloque = $auth->connecter('admin@allomecano.test', 'Demo2026!', '127.0.0.1', $this->t0->modify('+5 minutes'));
        self::assertSame('bloque', $bloque['erreur']);
        self::assertSame('2026-10-23 10:15:00', $bloque['jusqua']->format('Y-m-d H:i:s'));
        // 15 minutes après, c'est levé
        self::assertTrue($auth->connecter('admin@allomecano.test', 'Demo2026!', '127.0.0.1', $this->t0->modify('+16 minutes'))['ok']);
        // un autre identifiant n'est pas touché
        self::assertTrue($auth->connecter('07 99 99 00 01', 'Demo2026!', '127.0.0.1', $this->t0)['ok']);
    }

    public function testUnIdentifiantInconnuNeSeDevoilePas(): void
    {
        $auth = new AuthService($this->db);
        self::assertSame('mot_de_passe', $auth->connecter('inconnu@exemple.ci', 'Demo2026!', '127.0.0.1', $this->t0)['erreur']);
        self::assertSame('identifiant', $auth->connecter('n’importe quoi', 'x', '127.0.0.1', $this->t0)['erreur']);
    }

    public function testMasquerEmail(): void
    {
        self::assertSame('k•••@gmail.com', ReinitialisationService::masquer('koffi.yao@gmail.com'));
        self::assertSame('a•••@allomecano.ci', ReinitialisationService::masquer('admin@allomecano.ci'));
    }

    public function testLeLienArriveParEmailEtServiceUneFois(): void
    {
        $r = $this->reinit()->demander('Koffi.Yao@gmail.com', $this->t0);
        self::assertSame(['email_masque' => 'k•••@gmail.com', 'envoye' => true], $r);
        $jeton = $this->jetonEnvoye();
        self::assertSame('valide', $this->reinit()->verifier($jeton, $this->t0->modify('+29 minutes'))['etat']);
        self::assertSame('expire', $this->reinit()->verifier($jeton, $this->t0->modify('+31 minutes'))['etat']); // RG-C3 : 30 minutes
        self::assertSame('inconnu', $this->reinit()->verifier(str_repeat('a', 64))['etat']);
        self::assertSame('inconnu', $this->reinit()->verifier('pas-un-jeton')['etat']);
        self::assertTrue($this->reinit()->changer($jeton, 'Nouveau2026', $this->t0->modify('+10 minutes')));
        self::assertSame('inconnu', $this->reinit()->verifier($jeton, $this->t0->modify('+11 minutes'))['etat']); // à usage unique
        self::assertFalse($this->reinit()->changer($jeton, 'Autre2026x', $this->t0->modify('+12 minutes')));
        $auth = new AuthService($this->db);
        self::assertTrue($auth->connecter('koffi.yao@gmail.com', 'Nouveau2026', '127.0.0.1', $this->t0->modify('+13 minutes'))['ok']);
        self::assertSame('mot_de_passe', $auth->connecter('koffi.yao@gmail.com', 'Demo2026!', '127.0.0.1', $this->t0->modify('+13 minutes'))['erreur']);
    }

    public function testUneAdresseSansCompteReçoitLaMêmeReponseMaisRienPart(): void
    {
        $r = $this->reinit()->demander('personne@exemple.ci', $this->t0);
        self::assertSame(['email_masque' => 'p•••@exemple.ci', 'envoye' => false], $r);
        self::assertFileDoesNotExist($this->journal);
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM jetons_reinitialisation'));
    }

    public function testUnLienExpireSeRenvoieVersLaMemeAdresse(): void
    {
        $this->reinit()->demander('admin@allomecano.test', $this->t0);
        $ancien = $this->jetonEnvoye();
        $r = $this->reinit()->renvoyerDepuis($ancien);
        self::assertSame('a•••@allomecano.test', $r['email_masque']);
        self::assertSame(2, (int) $this->db->valeur('SELECT COUNT(*) FROM jetons_reinitialisation'));
    }

    public function testValidationDuNouveauMotDePasse(): void
    {
        $s = $this->reinit();
        self::assertSame([], $s->valider('Abcdef12', 'Abcdef12'));
        self::assertArrayHasKey('mot_de_passe', $s->valider('abcdefgh', 'abcdefgh')); // pas de chiffre
        self::assertArrayHasKey('mot_de_passe', $s->valider('abc1', 'abc1')); // trop court
        self::assertArrayHasKey('confirmation', $s->valider('Abcdef12', 'Abcdef13'));
    }

    public function testChangerRefuseUnMotDePasseFaible(): void
    {
        $this->reinit()->demander('koffi.yao@gmail.com', $this->t0);
        $jeton = $this->jetonEnvoye();
        self::assertFalse($this->reinit()->changer($jeton, 'court', $this->t0));
        self::assertSame('valide', $this->reinit()->verifier($jeton, $this->t0)['etat']); // le lien n'est pas brûlé
    }

    public function testContactMessageValidationEtLimiteParHeure(): void
    {
        $s = new MessageContactService($this->db);
        $v = $s->valider(['profil' => 'client', 'prenom' => 'Aminata', 'contact' => '07 07 12 34 56', 'message' => 'Bonjour, est-ce que vous couvrez Bingerville ?']);
        self::assertSame([], $v['erreurs']);
        self::assertSame('+2250707123456', $v['propre']['telephone']);
        self::assertNull($v['propre']['email']);
        $e = $s->valider(['profil' => 'client', 'prenom' => 'Aminata', 'contact' => 'aminata@exemple.ci', 'message' => 'Bonjour'])['propre'];
        self::assertSame('aminata@exemple.ci', $e['email']);
        self::assertNull($e['telephone']);
        $mauvais = $s->valider(['profil' => 'inconnu', 'prenom' => '', 'contact' => '07', 'message' => '']);
        self::assertSame(['profil', 'prenom', 'contact', 'message'], array_keys($mauvais['erreurs']));
        self::assertArrayHasKey('message', $s->valider(['profil' => 'client', 'prenom' => 'A', 'contact' => 'a@b.ci', 'message' => str_repeat('a', 1001)])['erreurs']);

        for ($i = 0; $i < 3; $i++) {
            self::assertTrue($s->enregistrer($v['propre'], '10.0.0.7', $this->t0)['ok']);
        }
        self::assertSame(['ok' => false, 'limite' => true], $s->enregistrer($v['propre'], '10.0.0.7', $this->t0->modify('+30 minutes'))); // RG-CT1 : 3 par heure
        self::assertTrue($s->enregistrer($v['propre'], '10.0.0.7', $this->t0->modify('+61 minutes'))['ok']);
        self::assertTrue($s->enregistrer($v['propre'], '10.0.0.8', $this->t0)['ok']); // une autre adresse IP
    }
}
