<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\FicheService;
use PHPUnit\Framework\TestCase;

/**
 * Fiche d'un mécano : dossier validé seulement (RG-DO8), « Nouveau » sous 3 avis (RG-AV6), avis publiés (RG-AV4, RG-AV5),
 * plus disponible (RG-D2, RG-D3), zone d'intervention. Tout est annulé à la fin.
 */
final class FicheServiceTest extends TestCase
{
    private Database $db;
    private FicheService $service;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM pannes LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->service = new FicheService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    /** @param array<string, mixed> $champs */
    private function mecano(string $slug, array $champs = []): int
    {
        return $this->db->inserer('mecaniciens', $champs + [
            'public_id' => Ulid::generer(), 'slug' => $slug, 'prenom' => 'Koffi', 'nom' => 'Test', 'metier' => 'Électricien auto',
            'telephone' => '+22507' . random_int(10000000, 99999999), 'mot_de_passe_hash' => 'x', 'rayon_km' => 10,
            'statut_dossier' => 'valide', 'etat' => 'disponible', 'dispo_lat' => 5.3600, 'dispo_lon' => -3.9600,
            'dispo_quartier_id' => (int) $this->db->valeur('SELECT id FROM quartiers WHERE slug = :s', ['s' => 'cocody-riviera-2']),
            'position_maj_le' => gmdate('Y-m-d H:i:s'), 'note_moyenne' => 4.8, 'nb_avis' => 37, 'annees_experience' => 12,
            'tarif_diagnostic_fcfa' => 5000, 'identite_verifiee_le' => '2026-03-12',
        ]);
    }

    private function avis(int $mecano, int $note, string $commentaire, ?string $prenom, string $statut = 'publie', string $quand = 'now'): void
    {
        $client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', Ulid::generer())]);
        $contact = $this->db->inserer('contacts', [
            'public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $mecano, 'canal' => 'tel', 'statut' => 'termine',
            'resume' => 'Voiture · Batterie',
        ]);
        $this->db->inserer('avis', [
            'contact_id' => $contact, 'mecanicien_id' => $mecano, 'client_id' => $client, 'note' => $note, 'commentaire' => $commentaire,
            'prenom_affiche' => $prenom, 'panne_libelle' => 'Batterie', 'statut' => $statut, 'cree_le' => gmdate('Y-m-d H:i:s', strtotime($quand)),
        ]);
    }

    public function testSeulUnDossierValideAUneFiche(): void
    {
        $this->mecano('fiche-ok');
        $this->mecano('fiche-attente', ['statut_dossier' => 'en_attente']);
        $this->mecano('fiche-suspendu', ['statut_dossier' => 'suspendu']);
        self::assertNotNull($this->service->fiche('fiche-ok'));
        self::assertNull($this->service->fiche('fiche-attente'));
        self::assertNull($this->service->fiche('fiche-suspendu')); // RG-DO8
        self::assertNull($this->service->fiche('inconnu'));
    }

    public function testLeNumeroNeSortJamaisDeLaFiche(): void
    {
        $this->mecano('fiche-tel');
        self::assertStringNotContainsString('+22507', json_encode($this->service->fiche('fiche-tel')) ?: ''); // RG-A1
    }

    public function testNouveauSousTroisAvis(): void
    {
        $this->mecano('fiche-neuf', ['nb_avis' => 2, 'note_moyenne' => 5.0]);
        $this->mecano('fiche-connu', ['nb_avis' => 3, 'note_moyenne' => 4.5]);
        self::assertNull($this->service->fiche('fiche-neuf')['note']); // RG-AV6
        self::assertSame(4.5, $this->service->fiche('fiche-connu')['note']);
    }

    public function testPlusDisponibleQuandIlEstSortiOuQuandSaPositionEstTropAncienne(): void
    {
        $this->mecano('dispo');
        $this->mecano('intervention', ['etat' => 'en_intervention']);
        $this->mecano('ancien', ['position_maj_le' => gmdate('Y-m-d H:i:s', time() - 13 * 3600)]);
        self::assertTrue($this->service->fiche('dispo')['disponible']);
        self::assertFalse($this->service->fiche('intervention')['disponible']); // RG-D4
        self::assertFalse($this->service->fiche('ancien')['disponible']); // RG-D3
    }

    public function testLesAvisPubliesSeulementLesPlusRecentsDAbord(): void
    {
        $id = $this->mecano('fiche-avis');
        $this->avis($id, 5, 'Ancien', 'Yao', 'publie', '-20 days');
        $this->avis($id, 4, 'Récent', null, 'publie', '-1 day');
        $this->avis($id, 1, 'Masqué par un admin', 'Zoé', 'masque', 'now');
        $public = $this->db->valeur('SELECT public_id FROM mecaniciens WHERE id = :i', ['i' => $id]);
        $r = $this->service->avis((string) $public, 10);
        self::assertSame(2, $r['total']); // RG-AV4 : masqué = absent
        self::assertSame(['Récent', 'Ancien'], array_column($r['avis'], 'commentaire'));
        self::assertSame('Un client', $r['avis'][0]['prenom']); // RG-AV5
        self::assertSame('Yao', $r['avis'][1]['prenom']);
        self::assertSame('il y a 1 jour', $r['avis'][0]['depuis']);
        self::assertSame(['Ancien'], array_column($this->service->avis((string) $public, 1, 1)['avis'], 'commentaire'));
    }

    public function testLaZoneNommeLesCommunesDuRayonLesPlusProchesDAbord(): void
    {
        $zone = $this->service->zone(5.3571, -3.9672, 10);
        self::assertContains('Cocody', $zone);
        self::assertLessThanOrEqual(3, count($zone));
        self::assertSame($zone, array_values(array_unique($zone)));
        self::assertSame([], $this->service->zone(0.0, 0.0, 2)); // en pleine mer : personne
    }

    public function testLaDistanceVientDeLaListeFigeeSinonDeLaPositionDuClient(): void
    {
        $this->mecano('fiche-dist');
        $f = $this->service->fiche('fiche-dist', 1234);
        self::assertSame(1234, $f['distance_m']);
        $f = $this->service->fiche('fiche-dist', null, ['lat' => 5.3600, 'lon' => -3.9600]);
        self::assertSame(0, $f['distance_m']);
        self::assertNull($this->service->fiche('fiche-dist')['distance_m']);
    }

    public function testLeMessageReprendLeRepereMotPourMot(): void
    {
        $m = FicheService::message('Koffi', ['lieu' => 'Riviera 2', 'repere' => 'près de la pharmacie des Jardins', 'vehicule' => 'Voiture', 'panne' => 'Batterie'], 5.3571, -3.9672);
        self::assertSame('Bonjour Koffi, je vous contacte via allomécano. Je suis en panne à Riviera 2, près de la pharmacie des Jardins. Voiture, batterie.', $m['sans']);
        self::assertSame("Ma position\u{202F}: https://osm.org/?mlat=5.3571&mlon=-3.9672", $m['position']);
        $court = FicheService::message('Awa', ['lieu' => null, 'repere' => null, 'vehicule' => null, 'panne' => null], null, null);
        self::assertSame('Bonjour Awa, je vous contacte via allomécano.', $court['sans']);
        self::assertNull($court['position']); // position effacée (RG-P1) ou jamais donnée
    }
}
