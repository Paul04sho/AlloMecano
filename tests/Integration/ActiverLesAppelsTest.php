<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\MecanoService;
use App\Services\PushService;
use PHPUnit\Framework\TestCase;

/** M1f · Activer les appels : le téléphone s'abonne aux notifications (RG-A2), fait un essai de sonnerie, puis « C'est prêt ». Tout est annulé à la fin. */
final class ActiverLesAppelsTest extends TestCase
{
    private const SOUSCRIPTION = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg']];

    private Database $db;
    /** @var list<array{abonnements: list<array<string, mixed>>, charge: array<string, mixed>}> */
    private array $envois = [];
    private PushService $push;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT appels_actives_le FROM mecaniciens LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'abonnements_push', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'photos', 'pieces_dossier', 'mecanicien_vehicules', 'mecanicien_specialites', 'mecaniciens'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->push = new PushService($this->db, function (array $abonnements, array $charge): int {
            $this->envois[] = ['abonnements' => $abonnements, 'charge' => $charge];
            return count($abonnements);
        });
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

    public function testUnTelephoneSAbonneUneFoisEtSeReabonneSansDoublon(): void
    {
        $id = $this->mecano();
        self::assertTrue($this->push->abonner($id, self::SOUSCRIPTION, 'Chrome · Android'));
        self::assertTrue($this->push->abonner($id, self::SOUSCRIPTION, 'Chrome · Android'));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM abonnements_push WHERE mecanicien_id = :m', ['m' => $id]));
        self::assertTrue($this->push->abonne($id, self::SOUSCRIPTION['endpoint']));
        self::assertFalse($this->push->abonne($id, 'https://fcm.googleapis.com/fcm/send/autre'));
    }

    public function testUnAbonnementRevoqueRedevientActifQuandLeMemeNavigateurSeReabonne(): void
    {
        $id = $this->mecano();
        $this->push->abonner($id, self::SOUSCRIPTION);
        $this->db->executer('UPDATE abonnements_push SET revoque_le = UTC_TIMESTAMP(), echecs_suite = 3');
        self::assertFalse($this->push->abonne($id, self::SOUSCRIPTION['endpoint']));
        $this->push->abonner($id, self::SOUSCRIPTION);
        self::assertTrue($this->push->abonne($id, self::SOUSCRIPTION['endpoint']));
        self::assertSame(0, (int) $this->db->valeur('SELECT echecs_suite FROM abonnements_push'));
    }

    public function testUnTelephonePartageEstReprisParLeDernierMecanoConnecte(): void
    {
        $a = $this->mecano();
        $b = $this->mecano();
        $this->push->abonner($a, self::SOUSCRIPTION);
        $this->push->abonner($b, self::SOUSCRIPTION);
        self::assertFalse($this->push->abonne($a, self::SOUSCRIPTION['endpoint'])); // les appels de A ne sonnent pas chez B, et inversement
        self::assertTrue($this->push->abonne($b, self::SOUSCRIPTION['endpoint']));
    }

    public function testUneSouscriptionIncompleteOuNonSecuriseeEstRefusee(): void
    {
        $id = $this->mecano();
        self::assertFalse($this->push->abonner($id, []));
        self::assertFalse($this->push->abonner($id, ['endpoint' => 'http://insecure.test/x', 'keys' => self::SOUSCRIPTION['keys']]));
        self::assertFalse($this->push->abonner($id, ['endpoint' => self::SOUSCRIPTION['endpoint'], 'keys' => ['p256dh' => 'a']]));
        self::assertFalse($this->push->abonner($id, ['endpoint' => 'https://x.test/' . str_repeat('a', 600), 'keys' => self::SOUSCRIPTION['keys']]));
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM abonnements_push'));
    }

    public function testLEssaiDeSonnerieAtteintLesAppareilsDuMecanoEtSeTraceDansLesNotifications(): void
    {
        $id = $this->mecano();
        self::assertSame(0, $this->push->essai($id)); // aucun téléphone abonné
        self::assertSame('echec', $this->db->valeur('SELECT statut FROM notifications WHERE mecanicien_id = :m ORDER BY id DESC LIMIT 1', ['m' => $id]));
        $this->push->abonner($id, self::SOUSCRIPTION);
        $this->push->abonner($this->mecano(), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/d-un-autre'] + self::SOUSCRIPTION);
        self::assertSame(1, $this->push->essai($id));
        self::assertSame('Essai de sonnerie', $this->envois[0]['charge']['titre']);
        self::assertNull($this->envois[0]['charge']['contact']); // pas un vrai appel : le service worker ne le garde pas affiché
        self::assertSame(['essai_sonnerie', 'envoyee'], array_values($this->db->un('SELECT type, statut FROM notifications WHERE mecanicien_id = :m ORDER BY id DESC LIMIT 1', ['m' => $id])));
    }

    public function testCEstPretExigeUnTelephoneJoignableEtNeSeFaitQuUneFois(): void
    {
        $id = $this->mecano();
        $service = new MecanoService($this->db);
        self::assertTrue($service->doitActiverLesAppels($id)); // « Dernière étape » à la première visite
        self::assertFalse($service->activerLesAppels($id)); // sans abonnement, aucun appel ne sonnerait app fermée
        self::assertTrue($service->doitActiverLesAppels($id));
        $this->push->abonner($id, self::SOUSCRIPTION);
        self::assertTrue($service->activerLesAppels($id));
        self::assertFalse($service->doitActiverLesAppels($id));
        $premiere = $this->db->valeur('SELECT appels_actives_le FROM mecaniciens WHERE id = :i', ['i' => $id]);
        $service->activerLesAppels($id);
        self::assertSame($premiere, $this->db->valeur('SELECT appels_actives_le FROM mecaniciens WHERE id = :i', ['i' => $id]));
    }

    public function testSeulUnDossierValideSeVoitProposerLEtape(): void
    {
        $service = new MecanoService($this->db);
        foreach (['brouillon', 'en_attente', 'a_reprendre', 'refuse', 'suspendu'] as $statut) {
            self::assertFalse($service->doitActiverLesAppels($this->mecano(['statut_dossier' => $statut])), $statut);
        }
    }
}
