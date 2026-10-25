<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\ClientService;
use App\Services\ContactException;
use App\Services\ContactService;
use App\Services\Parametres;
use App\Services\RechercheService;
use PHPUnit\Framework\TestCase;

/**
 * Contact direct et WhatsApp (RG-A1, RG-A10, RG-A11), retour d'appel (RG-A12, RG-I4) et mécano suivant (RG-I5).
 * Mécanos de test à des distances connues ; tout est annulé à la fin.
 */
final class ContactServiceTest extends TestCase
{
    private const LAT = 5.3600;
    private const LON = -3.9600;
    private Database $db;
    private ContactService $service;
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
        Parametres::vider();
        $this->db->pdo()->beginTransaction();
        $this->db->executer('DELETE FROM avis');
        $this->db->executer('DELETE FROM contacts');
        $this->db->executer('DELETE FROM pieces_dossier'); // les dossiers de démo de l'administration tiennent aux mécanos
        $this->db->executer('DELETE FROM mecaniciens');
        $clients = new ClientService($this->db);
        $this->client = $clients->identifier(null)['id'];
        $this->autreClient = $clients->identifier(null)['id'];
        $this->service = new ContactService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        Parametres::vider();
    }

    /** @param array<string, mixed> $champs */
    private function mecano(string $nom, int $metres, array $champs = []): string
    {
        $public = Ulid::generer();
        $id = $this->db->inserer('mecaniciens', $champs + [
            'public_id' => $public, 'slug' => 'test-' . $nom, 'prenom' => ucfirst($nom), 'nom' => 'Test', 'metier' => 'Test',
            'telephone' => '+2250700' . random_int(100000, 999999), 'mot_de_passe_hash' => 'x', 'rayon_km' => 20,
            'statut_dossier' => 'valide', 'etat' => 'disponible', 'dispo_lat' => self::LAT, 'dispo_lon' => self::LON + $metres / (111320 * cos(deg2rad(self::LAT))),
            'dispo_quartier_id' => (int) $this->db->valeur('SELECT id FROM quartiers WHERE slug = :s', ['s' => 'cocody-riviera-2']),
            'position_maj_le' => gmdate('Y-m-d H:i:s'), 'note_moyenne' => 4.5,
        ]);
        $this->db->inserer('mecanicien_vehicules', ['mecanicien_id' => $id, 'type_vehicule' => 'voiture']);
        return $public;
    }

    /** @return array{0: string, 1: array<string, mixed>} la recherche (liste figée) et le contexte pour les contacts */
    private function recherche(): array
    {
        $r = (new RechercheService($this->db))->lancer($this->client, ['source' => 'gps', 'lat' => self::LAT, 'lon' => self::LON, 'precision_m' => 18, 'quartier' => 'cocody-riviera-2', 'repere' => 'devant la pharmacie'], 'voiture', 'batterie', null);
        return [$r['public_id'], $this->contexte($r['public_id'])];
    }

    /** @return array<string, mixed> */
    private function contexte(?string $recherche = null): array
    {
        return ['recherche' => $recherche, 'lieu' => 'Riviera 2', 'repere' => 'devant la pharmacie', 'vehicule' => 'Voiture', 'panne' => 'Batterie', 'lat' => self::LAT, 'lon' => self::LON];
    }

    public function testLeContactExisteAvantQueLeNumeroSoitDonne(): void
    {
        $m = $this->mecano('koffi', 800);
        $r = $this->service->creer($this->client, $m, 'tel', true, $this->contexte());
        $ligne = $this->db->un('SELECT canal, statut, resume, quartier_libelle, position_partagee FROM contacts WHERE public_id = :p', ['p' => $r['public_id']]);
        self::assertSame(['canal' => 'tel', 'statut' => 'initie', 'resume' => 'Voiture · Batterie', 'quartier_libelle' => 'Riviera 2', 'position_partagee' => 1], $ligne); // RG-A1
        self::assertStringStartsWith('tel:+2250700', $r['cible']);
    }

    public function testAppelerOuvreLAppelDirectSansWebRtc(): void
    {
        $r = $this->service->creer($this->client, $this->mecano('koffi', 800), 'app', true, $this->contexte());
        self::assertSame('tel', $r['canal']); // RG-A2 : repli (le JS n'a pas annoncé de WebRTC)
    }

    public function testWhatsAppPorteLeMessagePretEtLaPositionSeulementSiActive(): void
    {
        $m = $this->mecano('awa', 800);
        $avec = $this->service->creer($this->client, $m, 'whatsapp', true, $this->contexte())['cible'];
        $sans = $this->service->creer($this->client, $m, 'whatsapp', false, $this->contexte())['cible'];
        self::assertStringStartsWith('https://wa.me/225', $avec);
        self::assertStringContainsString(rawurlencode('Bonjour Awa, je vous contacte via allomécano.'), $avec);
        self::assertStringContainsString(rawurlencode('devant la pharmacie'), $avec);
        self::assertStringContainsString(rawurlencode('mlat=5.3600'), $avec);
        self::assertStringNotContainsString('mlat', $sans); // RG-A11
        $position = $this->db->un('SELECT lat, repere FROM contacts WHERE position_partagee = 0 AND client_id = :c', ['c' => $this->client]);
        self::assertNull($position['lat']); // pas partagée : rien n'est gardé pour le mécano
        self::assertNull($position['repere']);
    }

    public function testUnMecanoIndisponibleOuInconnuEstRefuse(): void
    {
        foreach ([$this->mecano('absent', 500, ['etat' => 'indisponible']), $this->mecano('vieux', 500, ['position_maj_le' => gmdate('Y-m-d H:i:s', time() - 13 * 3600)]), Ulid::generer()] as $m) {
            try {
                $this->service->creer($this->client, $m, 'tel', true, $this->contexte());
                self::fail('Contact accepté');
            } catch (ContactException $e) {
                self::assertSame('indisponible', $e->code_refus);
            }
        }
    }

    public function testTroisContactsParHeureVersLeMemeMecano(): void
    {
        $m = $this->mecano('koffi', 800);
        for ($i = 0; $i < 3; $i++) {
            $this->service->creer($this->client, $m, 'tel', true, $this->contexte());
        }
        $this->expectException(ContactException::class); // RG-A10
        $this->service->creer($this->client, $m, 'tel', true, $this->contexte());
    }

    public function testCinqContactsEnDixMinutesParClient(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->creer($this->client, $this->mecano('m' . $i, 500 + $i), 'tel', true, $this->contexte());
        }
        try {
            $this->service->creer($this->client, $this->mecano('de-trop', 900), 'tel', true, $this->contexte());
            self::fail('Sixième contact accepté');
        } catch (ContactException $e) {
            self::assertSame('limite', $e->code_refus);
        }
        // Un autre client n'est pas gêné
        self::assertNotEmpty($this->service->creer($this->autreClient, $this->mecano('libre', 900), 'tel', true, $this->contexte())['public_id']);
    }

    public function testLeSuivantSauteLesContactesEtLesIneligibles(): void
    {
        $this->mecano('premier', 300);
        $deuxieme = $this->mecano('deuxieme', 600, ['etat' => 'disponible']);
        $troisieme = $this->mecano('troisieme', 900);
        $quatrieme = $this->mecano('quatrieme', 1200);
        [$recherche, $contexte] = $this->recherche();
        $premier = $this->db->valeur('SELECT public_id FROM mecaniciens WHERE slug = \'test-premier\'');
        $c = $this->service->creer($this->client, (string) $premier, 'tel', true, $contexte);

        self::assertSame($deuxieme, $this->service->suivant($c['public_id'], $this->client)['public_id']); // RG-I5
        self::assertSame('Second plus proche', $this->service->suivant($c['public_id'], $this->client)['rubrique']);
        $this->db->modifier('mecaniciens', ['etat' => 'en_intervention'], ['public_id' => $deuxieme]);
        $suivant = $this->service->suivant($c['public_id'], $this->client);
        self::assertSame($troisieme, $suivant['public_id']);
        self::assertSame('Troisième plus proche', $suivant['rubrique']);
        $this->service->creer($this->client, $troisieme, 'tel', true, $contexte, $c['public_id']);
        self::assertSame($quatrieme, $this->service->suivant($c['public_id'], $this->client)['public_id']);
        $this->db->modifier('mecaniciens', ['etat' => 'indisponible'], ['public_id' => $quatrieme]);
        self::assertNull($this->service->suivant($c['public_id'], $this->client)); // plus personne dans la liste figée
        self::assertNull($this->service->suivant($c['public_id'], $this->autreClient)); // et jamais celle d'un autre
        self::assertNotNull($recherche);
    }

    public function testLeNouveauContactGardeLeLienVersLePrecedent(): void
    {
        $a = $this->mecano('a', 300);
        $b = $this->mecano('b', 600);
        $premier = $this->service->creer($this->client, $a, 'tel', true, $this->contexte());
        $second = $this->service->creer($this->client, $b, 'whatsapp', true, $this->contexte(), $premier['public_id']);
        $lien = $this->db->valeur('SELECT p.public_id FROM contacts c JOIN contacts p ON p.id = c.contact_precedent_id WHERE c.public_id = :p', ['p' => $second['public_id']]);
        self::assertSame($premier['public_id'], $lien);
        // Le contact d'un autre client ne se réutilise pas comme précédent
        $autre = $this->service->creer($this->autreClient, $a, 'tel', true, $this->contexte());
        $x = $this->service->creer($this->client, $b, 'tel', true, $this->contexte(), $autre['public_id']);
        self::assertNull($this->db->valeur('SELECT contact_precedent_id FROM contacts WHERE public_id = :p', ['p' => $x['public_id']]));
    }

    public function testLaPremiereReponseCompteEtSeulLeClientLaDonne(): void
    {
        $c = $this->service->creer($this->client, $this->mecano('koffi', 800), 'tel', true, $this->contexte());
        self::assertFalse($this->service->repondre($c['public_id'], $this->autreClient, 'arrive'));
        self::assertTrue($this->service->repondre($c['public_id'], $this->client, 'pas_reponse'));
        self::assertFalse($this->service->repondre($c['public_id'], $this->client, 'arrive'));
        self::assertSame('pas_reponse', $this->service->lire($c['public_id'], $this->client)['issue']);
        $this->expectException(ContactException::class);
        $this->service->repondre($c['public_id'], $this->client, 'peut-etre');
    }

    public function testLAbsenceEstBorneeEtPrivee(): void
    {
        $c = $this->service->creer($this->client, $this->mecano('koffi', 800), 'tel', true, $this->contexte());
        self::assertTrue($this->service->enregistrerAbsence($c['public_id'], $this->client, 42));
        self::assertSame(42, (int) $this->db->valeur('SELECT absence_s FROM contacts WHERE public_id = :p', ['p' => $c['public_id']]));
        $this->service->enregistrerAbsence($c['public_id'], $this->client, 999999);
        self::assertSame(65535, (int) $this->db->valeur('SELECT absence_s FROM contacts WHERE public_id = :p', ['p' => $c['public_id']]));
        self::assertFalse($this->service->enregistrerAbsence($c['public_id'], $this->autreClient, 5));
    }

    public function testLeBandeauRevientPendantVingtQuatreHeuresSansReponse(): void
    {
        $m = $this->mecano('koffi', 800);
        self::assertNull($this->service->enAttenteDeReponse($this->client));
        $c = $this->service->creer($this->client, $m, 'whatsapp', true, $this->contexte());
        self::assertSame('Koffi', $this->service->enAttenteDeReponse($this->client)['prenom']); // RG-I4
        self::assertNull($this->service->enAttenteDeReponse($this->autreClient));
        $this->service->repondre($c['public_id'], $this->client, 'arrive');
        self::assertNull($this->service->enAttenteDeReponse($this->client));
        $vieux = $this->service->creer($this->autreClient, $m, 'tel', true, $this->contexte());
        $this->db->executer('UPDATE contacts SET cree_le = UTC_TIMESTAMP() - INTERVAL 25 HOUR WHERE public_id = :p', ['p' => $vieux['public_id']]);
        self::assertNull($this->service->enAttenteDeReponse($this->autreClient)); // ensuite l'issue reste inconnue
    }

    public function testLeNumeroNeSeRevelePasAuNeEnPasProprietaire(): void
    {
        $c = $this->service->creer($this->client, $this->mecano('koffi', 800), 'tel', true, $this->contexte());
        $revele = $this->service->reveler($c['public_id'], $this->client, $this->contexte());
        self::assertStringStartsWith('+2250700', $revele['telephone']);
        self::assertStringStartsWith('https://web.whatsapp.com/send?phone=225', $revele['lienWhatsApp']);
        self::assertNull($this->service->reveler($c['public_id'], $this->autreClient, $this->contexte())); // RG-A1
    }
}
