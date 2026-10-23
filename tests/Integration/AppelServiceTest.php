<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\AppelService;
use App\Services\ClientService;
use App\Services\ContactException;
use App\Services\ContactService;
use App\Services\Parametres;
use App\Services\PushService;
use PHPUnit\Framework\TestCase;

/**
 * Appel intégré : qui peut appeler (RG-A2 à RG-A4), sonnerie et délais (RG-A5), décroché et fin (RG-A6), échecs (RG-A7),
 * signalisation (RG-A8), « J'y vais » (RG-I2, RG-D4), injoignable (RG-D5), purge (RG-P3). Tout est annulé à la fin.
 */
final class AppelServiceTest extends TestCase
{
    private Database $db;
    private AppelService $appel;
    private ContactService $contacts;
    /** @var list<array{abonnements: list<array<string, mixed>>, charge: array<string, mixed>}> */
    private array $envois = [];
    private int $client;
    private int $autreClient;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM pannes LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->db->executer('DELETE FROM avis');
        $this->db->executer('DELETE FROM contacts');
        $this->db->executer('DELETE FROM mecaniciens');
        $this->db->executer("REPLACE INTO parametres (cle, valeur, type, description) VALUES ('appel.integre_actif', '1', 'int', 'test')");
        Parametres::vider();
        $clients = new ClientService($this->db);
        $this->client = $clients->identifier(null)['id'];
        $this->autreClient = $clients->identifier(null)['id'];
        $push = new PushService($this->db, function (array $abonnements, array $charge): int {
            $this->envois[] = ['abonnements' => $abonnements, 'charge' => $charge];
            return count($abonnements);
        });
        $this->appel = new AppelService($this->db, $push);
        $this->contacts = new ContactService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        Parametres::vider();
    }

    /** @param array<string, mixed> $champs */
    private function mecano(string $nom, array $champs = []): string
    {
        $public = Ulid::generer();
        $this->db->inserer('mecaniciens', $champs + [
            'public_id' => $public, 'slug' => 'appel-' . $nom, 'prenom' => ucfirst($nom), 'nom' => 'Test', 'metier' => 'Test',
            'telephone' => '+2250700' . random_int(100000, 999999), 'mot_de_passe_hash' => 'x', 'rayon_km' => 20,
            'statut_dossier' => 'valide', 'etat' => 'disponible', 'dispo_lat' => 5.36, 'dispo_lon' => -3.96, 'position_maj_le' => gmdate('Y-m-d H:i:s'),
            'vu_le' => gmdate('Y-m-d H:i:s'), 'note_moyenne' => 4.5,
        ]);
        return $public;
    }

    private function id(string $mecano): int
    {
        return (int) $this->db->valeur('SELECT id FROM mecaniciens WHERE public_id = :p', ['p' => $mecano]);
    }

    /** @return array<string, mixed> */
    private function contexte(): array
    {
        return ['recherche' => null, 'lieu' => 'Riviera 2', 'repere' => null, 'vehicule' => 'Voiture', 'panne' => 'Batterie', 'lat' => 5.36, 'lon' => -3.96];
    }

    private function appeler(string $mecano, ?int $client = null, bool $webrtc = true): array
    {
        return $this->contacts->creer($client ?? $this->client, $mecano, 'app', true, $this->contexte(), null, $webrtc);
    }

    private function statut(string $contact): string
    {
        return (string) $this->db->valeur('SELECT statut FROM contacts WHERE public_id = :p', ['p' => $contact]);
    }

    public function testAppelerOuvreLAppelIntegreSeulementSiTout(): void
    {
        $m = $this->mecano('koffi');
        self::assertSame('app', $this->appeler($m)['canal']);
        self::assertSame('tel', $this->appeler($m, $this->autreClient, false)['canal']); // navigateur sans WebRTC (RG-A2)

        $ancien = $this->mecano('ancien', ['vu_le' => gmdate('Y-m-d H:i:s', time() - 25 * 3600)]);
        self::assertSame('tel', $this->appeler($ancien)['canal']); // ni abonnement push, ni app ouverte depuis 24 h
        $this->db->inserer('abonnements_push', ['mecanicien_id' => $this->id($ancien), 'endpoint' => 'https://push.test/x', 'endpoint_hash' => hash('sha256', 'x'), 'cle_p256dh' => 'a', 'cle_auth' => 'b']);
        self::assertSame('app', $this->appeler($ancien, $this->autreClient)['canal']); // un abonnement push suffit

        $this->db->executer("UPDATE parametres SET valeur = '0' WHERE cle = 'appel.integre_actif'");
        Parametres::vider();
        self::assertSame('tel', $this->appeler($this->mecano('autre'))['canal']); // l'appel intégré est éteint
    }

    public function testUnMecanoNeRecoitQuUnAppelALaFois(): void
    {
        $m = $this->mecano('koffi');
        $premier = $this->appeler($m);
        self::assertTrue($this->appel->signaler($premier['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'v=0'])));
        $second = $this->appeler($m, $this->autreClient); // RG-A3
        self::assertSame('occupe', $second['statut']);
        self::assertSame('occupe', $this->statut($second['public_id']));
        self::assertSame('sonne', $this->statut($premier['public_id']));
    }

    public function testUnClientNAppelleQuUnMecanoALaFois(): void
    {
        $this->appeler($this->mecano('a'));
        try {
            $this->appeler($this->mecano('b'));
            self::fail('Deuxième appel accepté');
        } catch (ContactException $e) {
            self::assertSame('appel_en_cours', $e->code_refus); // RG-A4
        }
    }

    public function testLOffreFaitSonnerEtLePushEstRenvoyeToutesLesCinqSecondes(): void
    {
        $m = $this->mecano('koffi');
        $this->db->inserer('abonnements_push', ['mecanicien_id' => $this->id($m), 'endpoint' => 'https://push.test/k', 'endpoint_hash' => hash('sha256', 'k'), 'cle_p256dh' => 'a', 'cle_auth' => 'b']);
        $c = $this->appeler($m);
        self::assertSame('initie', $this->statut($c['public_id']));
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'v=0']));
        self::assertSame('sonne', $this->statut($c['public_id']));
        self::assertCount(1, $this->envois);
        self::assertSame($c['public_id'], $this->envois[0]['charge']['contact']);

        $this->appel->etat($c['public_id'], $this->client); // moins de 5 s : rien de plus
        self::assertCount(1, $this->envois);
        $this->db->executer('UPDATE notifications SET cree_le = UTC_TIMESTAMP() - INTERVAL 6 SECOND WHERE mecanicien_id = :m', ['m' => $this->id($m)]);
        $this->appel->etat($c['public_id'], $this->client); // RG-A5
        self::assertCount(2, $this->envois);
    }

    public function testDecrocherPuRaccrocherEnregistreLaDureeEtQuiARaccroche(): void
    {
        $m = $this->mecano('koffi');
        $c = $this->appeler($m);
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'offre']));
        self::assertTrue($this->appel->signaler($c['public_id'], 'mecanicien', $m, 'reponse', json_encode(['sdp' => 'reponse'])));
        self::assertSame('decroche', $this->statut($c['public_id'])); // RG-A6
        $this->db->executer('UPDATE contacts SET decroche_le = UTC_TIMESTAMP(3) - INTERVAL 130 SECOND WHERE public_id = :p', ['p' => $c['public_id']]);
        self::assertSame(130, $this->appel->etat($c['public_id'], $this->client)['en_ligne_depuis_s']);
        self::assertTrue($this->appel->signaler($c['public_id'], 'client', $this->client, 'raccroche', null));
        $ligne = $this->db->un('SELECT statut, fin_par, duree_s FROM contacts WHERE public_id = :p', ['p' => $c['public_id']]);
        self::assertSame('termine', $ligne['statut']);
        self::assertSame('client', $ligne['fin_par']);
        self::assertSame(130, (int) $ligne['duree_s']);
        self::assertFalse($this->appel->signaler($c['public_id'], 'client', $this->client, 'raccroche', null)); // deux fois : rien ne change
        self::assertSame(130, (int) $this->db->valeur('SELECT duree_s FROM contacts WHERE public_id = :p', ['p' => $c['public_id']]));
    }

    public function testApresTrenteSecondesSansReponseCEstManque(): void
    {
        $m = $this->mecano('koffi');
        $c = $this->appeler($m);
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'offre']));
        self::assertSame('sonne', $this->appel->etat($c['public_id'], $this->client)['statut']);
        $this->db->executer('UPDATE contacts SET cree_le = UTC_TIMESTAMP(3) - INTERVAL 31 SECOND WHERE public_id = :p', ['p' => $c['public_id']]);
        $etat = $this->appel->etat($c['public_id'], $this->client);
        self::assertSame('manque', $etat['statut']); // RG-A5
        self::assertTrue($etat['fin']);
        self::assertSame('delai', $etat['fin_par']);
        self::assertFalse($this->appel->signaler($c['public_id'], 'mecanicien', $m, 'reponse', json_encode(['sdp' => 'trop tard'])));
    }

    public function testRefusEtAnnulation(): void
    {
        $m = $this->mecano('koffi');
        $a = $this->appeler($m);
        $this->appel->signaler($a['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'o']));
        $this->appel->signaler($a['public_id'], 'mecanicien', $m, 'refus', null);
        self::assertSame('occupe', $this->statut($a['public_id'])); // RG-I1 : le client voit le suivant

        $b = $this->appeler($m, $this->autreClient);
        $this->appel->signaler($b['public_id'], 'client', $this->autreClient, 'raccroche', null);
        self::assertSame('annule', $this->statut($b['public_id']));
    }

    public function testMicroRefuseOuReseauImpossibleEstUnEchec(): void
    {
        $c = $this->appeler($this->mecano('koffi'));
        self::assertFalse($this->appel->echec($c['public_id'], $this->autreClient, 'micro_refuse')); // pas son appel
        self::assertFalse($this->appel->echec($c['public_id'], $this->client, 'autre'));
        self::assertTrue($this->appel->echec($c['public_id'], $this->client, 'micro_refuse')); // RG-A7
        self::assertSame('echoue', $this->statut($c['public_id']));
        self::assertSame('micro_refuse', $this->db->valeur('SELECT fin_par FROM contacts WHERE public_id = :p', ['p' => $c['public_id']]));
    }

    public function testLaSignalisationEstOrdonneeEtPriveeAuxDeuxCotes(): void
    {
        $m = $this->mecano('koffi');
        $c = $this->appeler($m);
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'offre']));
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'candidat', json_encode(['candidate' => 'c1']));
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'sonne', null);
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'reponse', json_encode(['sdp' => 'reponse']));
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'candidat', json_encode(['candidate' => 'm1']));

        $pourLeClient = $this->appel->signaux($c['public_id'], 'client', $this->client, 0);
        self::assertSame(['sonne', 'reponse', 'candidat'], array_column($pourLeClient, 'type')); // ses propres messages ne lui reviennent pas
        self::assertSame(['sdp' => 'reponse'], $pourLeClient[1]['charge']);
        $dernier = end($pourLeClient)['id'];
        self::assertSame([], $this->appel->signaux($c['public_id'], 'client', $this->client, $dernier));
        self::assertSame(['offre', 'candidat'], array_column($this->appel->signaux($c['public_id'], 'mecanicien', $m, 0), 'type'));

        self::assertSame([], $this->appel->signaux($c['public_id'], 'client', $this->autreClient, 0)); // jamais ceux d'un autre
        self::assertSame([], $this->appel->signaux($c['public_id'], 'mecanicien', $this->mecano('intrus'), 0));
        self::assertFalse($this->appel->signaler($c['public_id'], 'client', $this->client, 'reponse', json_encode(['sdp' => 'x']))); // pas son rôle
        self::assertFalse($this->appel->signaler($c['public_id'], 'client', $this->autreClient, 'candidat', json_encode(['c' => 1])));
        self::assertFalse($this->appel->signaler($c['public_id'], 'client', $this->client, 'candidat', '{pas du json'));
        self::assertFalse($this->appel->signaler($c['public_id'], 'client', $this->client, 'candidat', json_encode(['x' => str_repeat('a', 70000)])));
    }

    public function testTroisManquesDAfileeRendentLeMecanoIndisponible(): void
    {
        $m = $this->mecano('koffi');
        $manquer = function () use ($m): void {
            $c = $this->appeler($m);
            $this->db->executer('UPDATE contacts SET cree_le = UTC_TIMESTAMP(3) - INTERVAL 40 SECOND WHERE public_id = :p', ['p' => $c['public_id']]);
            $this->appel->etat($c['public_id'], $this->client);
        };
        $manquer();
        $manquer();
        self::assertSame('disponible', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :m', ['m' => $this->id($m)]));
        $manquer();
        self::assertSame('indisponible', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :m', ['m' => $this->id($m)])); // RG-D5
        self::assertSame(1, (int) $this->db->valeur("SELECT COUNT(*) FROM notifications WHERE type = 'indispo_auto' AND mecanicien_id = :m", ['m' => $this->id($m)]));
    }

    public function testUnAppelDecrocheRemetLeCompteurAZero(): void
    {
        $this->db->executer("REPLACE INTO parametres (cle, valeur, type, description) VALUES ('antiabus.contacts_meca_h', '20', 'int', 'test'), ('antiabus.contacts_client_10min', '20', 'int', 'test')");
        Parametres::vider();
        $m = $this->mecano('koffi');
        $manquer = function () use ($m): void {
            $c = $this->appeler($m);
            $this->db->executer('UPDATE contacts SET cree_le = UTC_TIMESTAMP(3) - INTERVAL 40 SECOND WHERE public_id = :p', ['p' => $c['public_id']]);
            $this->appel->etat($c['public_id'], $this->client);
        };
        $manquer();
        $manquer();
        $c = $this->appeler($m);
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'o']));
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'reponse', json_encode(['sdp' => 'r']));
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'raccroche', null);
        $manquer();
        $manquer();
        self::assertSame('disponible', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :m', ['m' => $this->id($m)]));
    }

    public function testJyVaisPasseLeMecanoEnInterventionUneSeuleFois(): void
    {
        $m = $this->mecano('koffi');
        $c = $this->appeler($m);
        self::assertFalse($this->appel->repondreMecano($c['public_id'], $m, 'j_y_vais')); // pas encore décroché
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'o']));
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'reponse', json_encode(['sdp' => 'r']));
        $this->appel->signaler($c['public_id'], 'mecanicien', $m, 'raccroche', null);
        self::assertFalse($this->appel->repondreMecano($c['public_id'], $m, 'peut-etre'));
        self::assertTrue($this->appel->repondreMecano($c['public_id'], $m, 'j_y_vais')); // RG-I2
        self::assertSame('j_y_vais', $this->appel->etat($c['public_id'], $this->client)['issue_mecano']);
        self::assertSame('en_intervention', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :m', ['m' => $this->id($m)])); // RG-D4
        self::assertFalse($this->appel->repondreMecano($c['public_id'], $m, 'pas_pour_moi'));
    }

    public function testLesAppelsEntrantsSontListesEtProuventQueLAppEstOuverte(): void
    {
        $m = $this->mecano('koffi', ['vu_le' => gmdate('Y-m-d H:i:s', time() - 5 * 3600)]);
        $c = $this->appeler($m);
        $entrants = $this->appel->entrants($m);
        self::assertSame([$c['public_id']], array_column($entrants, 'contact'));
        self::assertSame('Voiture · Batterie', $entrants[0]['resume']);
        self::assertGreaterThan(gmdate('Y-m-d H:i:s', time() - 60), $this->db->valeur('SELECT vu_le FROM mecaniciens WHERE id = :m', ['m' => $this->id($m)]));
        self::assertSame([], $this->appel->entrants($this->mecano('autre')));
        $this->appel->signaler($c['public_id'], 'client', $this->client, 'raccroche', null);
        self::assertSame([], $this->appel->entrants($m));
    }

    public function testLaSignalisationEstEffaceeUneHeureApresLaFin(): void
    {
        $m = $this->mecano('koffi');
        $vieux = $this->appeler($m);
        $this->appel->signaler($vieux['public_id'], 'client', $this->client, 'offre', json_encode(['sdp' => 'o']));
        $this->appel->signaler($vieux['public_id'], 'client', $this->client, 'raccroche', null);
        $this->db->executer('UPDATE contacts SET termine_le = UTC_TIMESTAMP(3) - INTERVAL 61 MINUTE WHERE public_id = :p', ['p' => $vieux['public_id']]);
        $recent = $this->appeler($m, $this->autreClient);
        $this->appel->signaler($recent['public_id'], 'client', $this->autreClient, 'offre', json_encode(['sdp' => 'o']));
        self::assertSame(1, $this->appel->purgerSignalisation()); // RG-P3 : seul l'appel fini depuis plus d'une heure
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM signalisation'));
    }
}
