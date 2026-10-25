<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;
use App\Services\SignalementAdminService;
use PHPUnit\Framework\TestCase;

/** A4 · Signalements : gravité, tri, délai cible d'un critique, détail, lecture, classement sans suite (note obligatoire), sanction (RG-S1, RG-S2, RG-S3, RG-C5). Tout est annulé à la fin. */
final class SignalementAdminServiceTest extends TestCase
{
    private Database $db;
    private int $admin;
    private \DateTimeImmutable $t0;
    private SignalementAdminService $service;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT date_naissance FROM mecaniciens LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'recherches', 'photos', 'pieces_dossier', 'mecanicien_specialites', 'mecaniciens', 'clients', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->admin = $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->t0 = new \DateTimeImmutable('2026-10-23 14:34:00', new \DateTimeZone('UTC'));
        $this->service = new SignalementAdminService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    private function mecano(string $prenom = 'Koffi', string $nom = 'Yao', string $statut = 'valide'): int
    {
        return $this->db->inserer('mecaniciens', ['public_id' => Ulid::generer(), 'prenom' => $prenom, 'nom' => $nom, 'metier' => 'Test', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => $statut, 'cree_le' => '2026-02-01 08:47:00', 'dispo_lat' => 5.3655, 'dispo_lon' => -3.949]);
    }

    /** @param array<string, mixed> $champs */
    private function signalement(int $mecano, string $motif, string $statut, string $creeLe, array $champs = []): string
    {
        $public = Ulid::generer();
        $this->db->inserer('signalements', $champs + ['public_id' => $public, 'mecanicien_id' => $mecano, 'motif' => $motif, 'statut' => $statut, 'cree_le' => $creeLe]);
        return $public;
    }

    private function il(string $duree): string
    {
        return $this->t0->modify("-$duree")->format('Y-m-d H:i:s');
    }

    public function testChaqueMotifADeLaGraviteDeFigma(): void
    {
        self::assertSame('critique', SignalementAdminService::gravite('comportement'));
        self::assertSame('critique', SignalementAdminService::gravite('securite'));
        self::assertSame('critique', SignalementAdminService::gravite('identite'));
        self::assertSame('important', SignalementAdminService::gravite('prix_abusif'));
        self::assertSame('important', SignalementAdminService::gravite('pas_venu'));
        self::assertSame('normal', SignalementAdminService::gravite('autre'));
        self::assertSame('SIG-2026-0187', SignalementAdminService::numero(187, '2026-10-23 14:34:00'));
    }

    public function testLaListeVaDuPlusGraveAuPlusRecentEtLeDecompteSuitLeDelaiCible(): void
    {
        $m = $this->mecano();
        $this->signalement($m, 'autre', 'nouveau', $this->il('20 hours'));
        $this->signalement($m, 'pas_venu', 'en_cours', $this->il('5 hours'));
        $this->signalement($m, 'prix_abusif', 'nouveau', $this->il('30 hours'));
        $this->signalement($m, 'securite', 'nouveau', $this->il('1 hours'));
        $this->signalement($m, 'comportement', 'nouveau', $this->il('160 minutes'));
        $this->signalement($m, 'autre', 'sans_suite', $this->il('50 hours'), ['traite_le' => $this->il('40 hours')]);
        $liste = $this->service->liste('ouverts', $this->t0);
        self::assertSame(['critique', 'critique', 'important', 'important', 'normal'], array_column($liste, 'gravite'));
        self::assertSame(['Comportement agressif ou menaçant', 'Il a demandé de l’argent avant de venir', 'Prix très différent de ce qui était annoncé', 'Il n’est jamais venu', 'Autre'], array_column($liste, 'titre'));
        self::assertSame("il y a 2\u{00A0}h\u{00A0}40", $liste[0]['quand']); // le critique le plus ancien d'abord
        $d = $this->service->decompte($this->t0);
        self::assertSame(['ouverts' => 5, 'critiques' => 2, 'en_retard' => 1, 'classes_mois' => 1, 'cible_h' => 2], $d); // seul celui de 2 h 40 dépasse les 2 h
    }

    public function testLOngletClassesNeMontreQueLesTrenteDerniersJours(): void
    {
        $m = $this->mecano();
        $this->signalement($m, 'autre', 'sans_suite', $this->il('10 days'), ['traite_le' => $this->il('9 days')]);
        $this->signalement($m, 'autre', 'sanction', $this->il('50 days'), ['traite_le' => $this->il('45 days')]); // plus de 30 jours : hors liste
        $this->signalement($m, 'prix_abusif', 'sanction', $this->il('3 days'), ['traite_le' => $this->il('2 days')]);
        $liste = $this->service->liste('classes', $this->t0);
        self::assertCount(2, $liste);
        self::assertSame('sanction', $liste[0]['statut']); // le dernier classé d'abord
        self::assertSame('Sanction le 21 octobre · 14:34', $liste[0]['quand']);
    }

    public function testLesDelaisSeLisentCommeDansFigma(): void
    {
        $e = static fn (string $d): string => SignalementAdminService::ecoule(new \DateTimeImmutable($d, new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-10-23 14:34:00', new \DateTimeZone('UTC')));
        $i = Format::INSECABLE;
        self::assertSame("il y a 2{$i}h{$i}40", $e('2026-10-23 11:54:00'));
        self::assertSame("il y a 5{$i}h", $e('2026-10-23 09:34:00'));
        self::assertSame("il y a 12{$i}min", $e('2026-10-23 14:22:00'));
        self::assertSame('à l’instant', $e('2026-10-23 14:33:40'));
        self::assertSame('hier · 19:12', $e('2026-10-22 19:12:00'));
        self::assertSame('le 18 octobre · 09:00', $e('2026-10-18 09:00:00'));
        self::assertSame('aujourd’hui à 14:34', SignalementAdminService::quand(new \DateTimeImmutable('2026-10-23 14:34:00'), $this->t0));
    }

    public function testLeDetailRassembleLeRecitLeClientEtLeContexte(): void
    {
        $m = $this->mecano('Koffi', 'Yao');
        $autre = $this->mecano('Koffi', 'Yao', 'valide');
        $client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'aminata'), 'prenom' => 'Aminata', 'telephone' => '+2250707123456']);
        $contact = $this->db->inserer('contacts', ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $m, 'canal' => 'app', 'statut' => 'termine', 'duree_s' => 240,
            'resume' => 'Voiture · Batterie', 'quartier_libelle' => 'Riviera 2', 'lat' => 5.3763, 'lon' => -3.949, 'cree_le' => '2026-10-23 12:08:00']);
        $this->signalement($autre, 'autre', 'sans_suite', $this->il('30 days'));
        $p = $this->signalement($m, 'comportement', 'nouveau', '2026-10-23 11:54:00', ['contact_id' => $contact, 'client_id' => $client, 'description' => 'Il a demandé 45 000 FCFA.']);
        $d = $this->service->detail($p, $this->t0);
        self::assertSame('critique', $d['gravite']);
        self::assertSame('reçu aujourd’hui à 11:54', $d['recu']);
        self::assertSame('Il a demandé 45 000 FCFA.', $d['description']);
        self::assertSame('Koffi Yao', $d['mecano']['nom_complet']);
        self::assertSame(0, $d['mecano']['passes']); // « signalements passés : aucun » : l'autre signalement vise un autre compte
        self::assertTrue($d['mecano']['suspendable']);
        self::assertSame('Aminata', $d['client']['prenom']);
        self::assertSame("+225\u{00A0}07\u{00A0}••\u{00A0}••\u{00A0}••\u{00A0}56", $d['client']['masque']);
        self::assertSame(16, strlen((string) $d['client']['ref']));
        self::assertSame("Aujourd’hui à 12:08, 4\u{00A0}min", $d['contexte']['appel']);
        self::assertSame('Voiture · batterie', $d['contexte']['panne']);
        self::assertStringStartsWith('Riviera 2 · 1,2', (string) $d['contexte']['lieu']);
        self::assertFalse($d['contexte']['photo']);
        self::assertNull($this->service->detail(Ulid::generer()));
    }

    public function testSansContactLeContexteResteVideEtSansTelephoneLeNumeroEstAbsent(): void
    {
        $m = $this->mecano();
        $client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'x'), 'prenom' => 'Yves']);
        $p = $this->signalement($m, 'autre', 'nouveau', $this->il('1 hours'), ['client_id' => $client]);
        $d = $this->service->detail($p, $this->t0);
        self::assertNull($d['client']['masque']);
        self::assertSame(['appel' => null, 'panne' => null, 'lieu' => null, 'photo' => false], $d['contexte']);
        self::assertNull($d['description']);
    }

    public function testLePremierRegardPasseEnCoursUneSeuleFoisEtEstJournalise(): void
    {
        $p = $this->signalement($this->mecano(), 'autre', 'nouveau', $this->il('1 hours'));
        $this->service->marquerLu($this->admin, $p, '127.0.0.1');
        $this->service->marquerLu($this->admin, $p, '127.0.0.1');
        self::assertSame('en_cours', $this->db->valeur('SELECT statut FROM signalements WHERE public_id = :p', ['p' => $p]));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin WHERE action = \'lire_signalement\''));
    }

    public function testClasserSansSuiteExigeUneNote(): void
    {
        $p = $this->signalement($this->mecano(), 'autre', 'en_cours', $this->il('1 hours'));
        self::assertFalse($this->service->classer($this->admin, $p, '   ', '127.0.0.1')['ok']);
        self::assertFalse($this->service->classer($this->admin, $p, str_repeat('a', 501), '127.0.0.1')['ok']);
        self::assertSame('en_cours', $this->db->valeur('SELECT statut FROM signalements WHERE public_id = :p', ['p' => $p]));
        self::assertSame(['ok' => true], $this->service->classer($this->admin, $p, 'Position vérifiée : elle était à jour.', '127.0.0.1', $this->t0));
        $l = $this->db->un('SELECT statut, decision, traite_le, traite_par_admin_id FROM signalements WHERE public_id = :p', ['p' => $p]);
        self::assertSame(['sans_suite', 'Position vérifiée : elle était à jour.', '2026-10-23 14:34:00', $this->admin], array_values($l));
        self::assertSame('classer_signalement', $this->db->valeur('SELECT action FROM journal_admin'));
        self::assertSame('Ce signalement est déjà classé.', $this->service->classer($this->admin, $p, 'Encore', '127.0.0.1')['erreur']);
        $d = $this->service->detail($p, $this->t0);
        self::assertFalse($d['ouvert']);
        self::assertSame('A. Bamba', $d['traite_par']);
    }

    public function testLaSanctionClotLeSignalementAvecLeMotifDeLaSuspension(): void
    {
        $p = $this->signalement($this->mecano(), 'comportement', 'nouveau', $this->il('1 hours'));
        self::assertTrue($this->service->sanctionner($this->admin, $p, 'Menaces au téléphone', '127.0.0.1', $this->t0));
        self::assertFalse($this->service->sanctionner($this->admin, $p, 'Encore', '127.0.0.1')); // déjà traité
        $l = $this->db->un('SELECT statut, decision FROM signalements WHERE public_id = :p', ['p' => $p]);
        self::assertSame(['statut' => 'sanction', 'decision' => 'Menaces au téléphone'], $l);
        self::assertSame('sanctionner_signalement', $this->db->valeur('SELECT action FROM journal_admin'));
        self::assertTrue($this->service->detail($p)['mecano']['suspendable']); // le compte lui-même n'a pas bougé : c'est CompteService qui suspend
    }

    public function testUnCompteSuspenduNeSePeutPasSuspendreDeNouveau(): void
    {
        $p = $this->signalement($this->mecano('Koffi', 'Yao', 'suspendu'), 'securite', 'nouveau', $this->il('1 hours'));
        self::assertFalse($this->service->detail($p)['mecano']['suspendable']);
    }

    public function testLaPhotoJointeNEstServieQuAuxAdminsEtChaqueVueEstJournalisee(): void
    {
        $dossier = sys_get_temp_dir() . '/sig-' . bin2hex(random_bytes(3));
        mkdir($dossier);
        file_put_contents($dossier . '/a.jpg', 'x');
        file_put_contents(dirname($dossier) . '/secret-' . basename($dossier) . '.txt', 'secret');
        try {
            $p = $this->signalement($this->mecano(), 'autre', 'nouveau', $this->il('1 hours'), ['photo_fichier' => 'a.jpg']);
            $f = $this->service->photo($this->admin, $p, '127.0.0.1', $dossier);
            self::assertSame(realpath($dossier . '/a.jpg'), $f['chemin']);
            self::assertSame('voir_photo_signalement', $this->db->valeur('SELECT action FROM journal_admin'));
            $evil = $this->signalement($this->mecano(), 'autre', 'nouveau', $this->il('1 hours'), ['photo_fichier' => '../secret-' . basename($dossier) . '.txt']);
            self::assertNull($this->service->photo($this->admin, $evil, '127.0.0.1', $dossier)); // pas de sortie du dossier
            $sans = $this->signalement($this->mecano(), 'autre', 'nouveau', $this->il('1 hours'));
            self::assertNull($this->service->photo($this->admin, $sans, '127.0.0.1', $dossier));
            self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin'));
        } finally {
            @unlink($dossier . '/a.jpg');
            @rmdir($dossier);
            @unlink(dirname($dossier) . '/secret-' . basename($dossier) . '.txt');
        }
    }
}
