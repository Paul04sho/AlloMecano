<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\ClientService;
use App\Services\GeoService;
use App\Services\PurgeService;
use App\Services\RechercheService;
use PHPUnit\Framework\TestCase;

/**
 * Recherche (RG-R2 à RG-R9), liste figée (RG-R7), zones sans résultat (RG-R8), purge (RG-P1, RG-P2).
 * Mécanos de test placés à des distances connues de Riviera 2 ; tout est annulé à la fin.
 */
final class RechercheServiceTest extends TestCase
{
    private const LAT = 5.3600;
    private const LON = -3.9600;
    private Database $db;
    private int $client;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM pannes LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->db->executer('DELETE FROM avis'); // les avis de démo bloquent la suppression des mécanos (RESTRICT) ; annulé à la fin
        $this->db->executer('DELETE FROM contacts');
        $this->db->executer('DELETE FROM mecaniciens');
        $this->db->executer('DELETE FROM zones_sans_resultat');
        $this->client = (new ClientService($this->db))->identifier(null)['id'];
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    /** Un mécano à $metres plein est (ou ouest si négatif) du client. */
    private function mecano(string $nom, int $metres, array $vehicules = ['voiture'], array $specialites = [], array $champs = [], string $quartier = 'cocody-riviera-2'): int
    {
        $lon = self::LON + $metres / (111320 * cos(deg2rad(self::LAT)));
        $id = $this->db->inserer('mecaniciens', $champs + [
            'public_id' => Ulid::generer(), 'slug' => 'test-' . $nom, 'prenom' => ucfirst($nom), 'nom' => 'Test', 'metier' => 'Test',
            'telephone' => '+22507' . random_int(10000000, 99999999), 'mot_de_passe_hash' => 'x', 'rayon_km' => 20,
            'statut_dossier' => 'valide', 'etat' => 'disponible', 'dispo_lat' => self::LAT, 'dispo_lon' => $lon,
            'dispo_quartier_id' => (int) $this->db->valeur('SELECT id FROM quartiers WHERE slug = :s', ['s' => $quartier]),
            'position_maj_le' => gmdate('Y-m-d H:i:s'), 'note_moyenne' => 4.5,
        ]);
        foreach ($vehicules as $v) {
            $this->db->inserer('mecanicien_vehicules', ['mecanicien_id' => $id, 'type_vehicule' => $v]);
        }
        foreach ($specialites as $s) {
            $this->db->executer('INSERT INTO mecanicien_specialites (mecanicien_id, specialite_id) SELECT :m, id FROM specialites WHERE code = :s', ['m' => $id, 's' => $s]);
        }
        return $id;
    }

    private function position(): array
    {
        return ['source' => 'gps', 'lat' => self::LAT, 'lon' => self::LON, 'precision_m' => 18, 'quartier' => 'cocody-riviera-2', 'repere' => 'pharmacie'];
    }

    private function lancer(string $vehicule = 'voiture', string $panne = 'batterie', ?int $rayon = null): array
    {
        $service = new RechercheService($this->db);
        $r = $service->lancer($this->client, $this->position(), $vehicule, $panne, null, $rayon);
        return $service->lire($r['public_id'], $this->client) + ['lance' => $r];
    }

    public function testLeVehiculeFiltreStrictement(): void
    {
        $this->mecano('auto', 800);
        $this->mecano('moto', 500, ['moto']);
        $noms = array_column($this->lancer('voiture')['resultats'], 'prenom');
        self::assertSame(['Auto'], $noms); // RG-R2
    }

    public function testSeulsLesMecanosEligiblesSortent(): void
    {
        $this->mecano('ok', 900);
        $this->mecano('indispo', 300, champs: ['etat' => 'indisponible']);
        $this->mecano('attente', 300, champs: ['statut_dossier' => 'en_attente']);
        $this->mecano('ancien', 300, champs: ['position_maj_le' => gmdate('Y-m-d H:i:s', time() - 13 * 3600)]);
        $this->mecano('petitrayon', 6000, champs: ['rayon_km' => 5]); // hors de son propre rayon (le palier de 10 km le couvrirait)
        self::assertSame(['Ok'], array_column($this->lancer()['resultats'], 'prenom')); // RG-R3
    }

    public function testLesPaliersSElargissentTantQuIlManqueDesResultats(): void
    {
        $this->mecano('un', 1000);
        $this->mecano('deux', 3000);
        $this->mecano('trois', 4500);
        $this->mecano('loin', 8000);
        $r = $this->lancer();
        self::assertSame(5, $r['lance']['rayon_km']); // RG-R5 : 2 km (1 résultat) puis 5 km (3)
        self::assertSame(['Un', 'Deux', 'Trois'], array_column($r['resultats'], 'prenom'));
    }

    public function testLeSpecialisteEtLAutreRiveChangentLOrdre(): void
    {
        $this->mecano('proche', 1000);
        $this->mecano('specialiste', 1200, specialites: ['batterie']);  // 1200 × 0,75 = 900 < 1000 (RG-R6)
        $this->mecano('lagune', 700, quartier: 'marcory-zone-4');       // 700 × 1,3 = 910, rive sud (RG-R9)
        $r = $this->lancer();
        self::assertSame(['Specialiste', 'Lagune', 'Proche'], array_column($r['resultats'], 'prenom'));
        self::assertTrue($r['resultats'][0]['specialiste']);
        self::assertSame(['Batterie'], $r['resultats'][0]['specialites_panne']);
        self::assertTrue($r['resultats'][1]['autre_rive']);
    }

    public function testLaListeEstFigeeEtReserveeASonClient(): void
    {
        $this->mecano('un', 600);
        $r = $this->lancer();
        $this->db->executer('UPDATE mecaniciens SET etat = \'indisponible\'');
        $relue = (new RechercheService($this->db))->lire($r['recherche']['public_id'], $this->client);
        self::assertCount(1, $relue['resultats']); // RG-R7
        self::assertFalse($relue['resultats'][0]['disponible']);
        $autre = (new ClientService($this->db))->identifier(null)['id'];
        self::assertNull((new RechercheService($this->db))->lire($r['recherche']['public_id'], $autre)); // RG-C7
    }

    public function testAucunResultatCompteLaZoneSansLienAvecLeClient(): void
    {
        $r = $this->lancer();
        self::assertSame([], $r['resultats']);
        self::assertSame(10, $r['lance']['rayon_km']);
        $zone = $this->db->un('SELECT * FROM zones_sans_resultat WHERE zone_id = :z', ['z' => GeoService::geohash(self::LAT, self::LON)]);
        self::assertSame(1, (int) $zone['nb']); // RG-R8
        self::assertArrayNotHasKey('client_id', $zone);
    }

    public function testLeRayonEtenduNEstUtiliseQueSurDemande(): void
    {
        $this->mecano('loin', 15000);
        self::assertSame([], $this->lancer()['resultats']);
        $r = $this->lancer(rayon: 20);
        self::assertSame(['Loin'], array_column($r['resultats'], 'prenom'));
        self::assertSame(20, $r['lance']['rayon_km']);
    }

    public function testLaPurgeEffaceLaPositionPuisLaRecherche(): void
    {
        $this->mecano('un', 600);
        $id = $this->lancer()['recherche']['public_id'];
        $this->db->executer('UPDATE recherches SET cree_le = UTC_TIMESTAMP() - INTERVAL 25 HOUR WHERE public_id = :p', ['p' => $id]);
        (new PurgeService($this->db))->purger();
        $r = $this->db->un('SELECT lat, lon, quartier_id, position_effacee_le FROM recherches WHERE public_id = :p', ['p' => $id]);
        self::assertNull($r['lat']); // RG-P1
        self::assertNotNull($r['quartier_id']);
        self::assertNotNull($r['position_effacee_le']);
        $this->db->executer('UPDATE recherches SET cree_le = UTC_TIMESTAMP() - INTERVAL 91 DAY WHERE public_id = :p', ['p' => $id]);
        (new PurgeService($this->db))->purger();
        self::assertNull($this->db->valeur('SELECT id FROM recherches WHERE public_id = :p', ['p' => $id])); // RG-P2
    }
}
