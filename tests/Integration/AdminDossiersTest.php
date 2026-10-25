<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\AdminService;
use App\Services\DossierService;
use App\Services\JournalAdminService;
use PHPUnit\Framework\TestCase;

/** Administration : compteurs et indicateurs d'A0, file des dossiers d'A1 (jauge, pièces, recherche, tri, pages), journal (RG-C5). Tout est annulé à la fin. */
final class AdminDossiersTest extends TestCase
{
    private Database $db;
    private \DateTimeImmutable $t0;
    private int $admin;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM journal_admin LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'signalements', 'avis', 'contacts', 'pieces_dossier', 'mecanicien_specialites', 'mecaniciens', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->admin = $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->t0 = new \DateTimeImmutable('2026-10-23 12:00:00', new \DateTimeZone('UTC'));
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    /** @param array<string, mixed> $champs */
    private function mecano(string $prenom, string $nom, string $statut, ?int $heuresAttente, array $champs = []): int
    {
        return $this->db->inserer('mecaniciens', $champs + [
            'public_id' => Ulid::generer(), 'prenom' => $prenom, 'nom' => $nom, 'metier' => 'Test', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => $statut,
            'dossier_envoye_le' => $heuresAttente !== null ? $this->t0->modify("-$heuresAttente hours")->format('Y-m-d H:i:s') : null,
        ]);
    }

    private function piece(int $mecano, string $type, string $statut = 'envoyee'): void
    {
        $this->db->inserer('pieces_dossier', ['mecanicien_id' => $mecano, 'type_piece' => $type, 'fichier' => "demo/$type.jpg", 'mime' => 'image/jpeg', 'taille_octets' => 10,
            'empreinte_sha256' => str_repeat('a', 64), 'statut' => $statut]);
    }

    public function testNumeroDeDossier(): void
    {
        self::assertSame('DOS-2026-0412', DossierService::numero(412, '2026-10-20 10:14:00'));
        self::assertSame('DOS-2026-0007', DossierService::numero(7, '2026-01-02 00:00:00'));
    }

    public function testLaJaugeDAttenteSuitLesTranchesDeSixHeures(): void
    {
        $h = fn (int $heures): array => DossierService::jauge($this->t0->modify("-$heures hours")->format('Y-m-d H:i:s'), $this->t0);
        // les cinq lignes de Figma (164:99) : 31 h → 5 segments, 27 → 4, 22 → 3, 15 → 2, 9 → 1 ; 2 h garde au moins un segment
        self::assertSame([31, 5], [$h(31)['heures'], $h(31)['segments']]);
        self::assertSame(4, $h(27)['segments']);
        self::assertSame(3, $h(22)['segments']);
        self::assertSame(2, $h(15)['segments']);
        self::assertSame(1, $h(9)['segments']);
        self::assertSame(1, $h(2)['segments']);
        self::assertSame(8, $h(70)['segments']);
        self::assertFalse($h(36)['alerte']);
        self::assertTrue($h(37)['alerte']); // « alerte au-delà de 36 h »
        self::assertSame(48, $h(5)['delai']);
    }

    public function testLaFileEstLaPlusAncienneDabord(): void
    {
        $this->mecano('Récent', 'Un', 'en_attente', 2);
        $this->mecano('Ancien', 'Deux', 'en_attente', 31);
        $this->mecano('Milieu', 'Trois', 'en_attente', 15);
        $this->mecano('Validé', 'Quatre', 'valide', 99);
        $s = DossierService::depuisBase();
        $f = $s->file('en_attente', '', 1, 'desc', $this->t0);
        self::assertSame(3, $f['total']);
        self::assertSame(['Ancien Deux', 'Milieu Trois', 'Récent Un'], array_column($f['lignes'], 'nom_complet'));
        self::assertSame(['Récent Un', 'Milieu Trois', 'Ancien Deux'], array_column($s->file('en_attente', '', 1, 'asc', $this->t0)['lignes'], 'nom_complet'));
        self::assertSame(['Ancien Deux', 'Milieu Trois'], array_column($s->plusAnciens(2, $this->t0), 'nom_complet'));
        self::assertSame(['en_attente' => 3, 'a_reprendre' => 0, 'valide' => 1, 'refuse' => 0, 'suspendu' => 0], $s->decompte());
        self::assertSame('en_attente', $s->file('inconnu')['lignes'][0]['statut']); // un statut inconnu retombe sur « En attente »
    }

    public function testHuitDossiersParPage(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->mecano("Meca$i", 'Test', 'en_attente', $i);
        }
        $s = DossierService::depuisBase();
        self::assertSame([8, 12, 2], [count($s->file('en_attente', '', 1)['lignes']), $s->file('en_attente', '', 1)['total'], $s->file('en_attente', '', 1)['pages']]);
        self::assertSame(4, count($s->file('en_attente', '', 2)['lignes'])); // « 8 SUR 12 » puis les 4 derniers
        self::assertSame(4, count($s->file('en_attente', '', 99)['lignes'])); // une page trop loin retombe sur la dernière
    }

    public function testLaRechercheTrouveParNomTelephoneEtNumero(): void
    {
        $id = $this->mecano('Moussa', 'Diabaté', 'en_attente', 31, ['telephone' => '+2250712345678']);
        $this->mecano('Fatou', 'Bamba', 'en_attente', 22);
        $s = DossierService::depuisBase();
        self::assertSame(['Moussa Diabaté'], array_column($s->file('en_attente', 'diabat', 1)['lignes'], 'nom_complet'));
        self::assertSame(['Moussa Diabaté'], array_column($s->file('en_attente', 'Diabaté Moussa', 1)['lignes'], 'nom_complet'));
        self::assertSame(['Moussa Diabaté'], array_column($s->file('en_attente', '07 12 34 56', 1)['lignes'], 'nom_complet'));
        self::assertSame(['Moussa Diabaté'], array_column($s->file('en_attente', DossierService::numero($id, $this->t0->format('Y-m-d H:i:s')), 1)['lignes'], 'nom_complet'));
        self::assertSame([], $s->file('en_attente', 'personne', 1)['lignes']);
        self::assertSame([], $s->file('en_attente', '100%', 1)['lignes']); // les jokers LIKE sont neutralisés
    }

    public function testLesPastillesDePieces(): void
    {
        $id = $this->mecano('Ibrahim', 'Ouattara', 'a_reprendre', 27);
        foreach (['cni_recto', 'cni_verso', 'diplome', 'portrait'] as $t) {
            $this->piece($id, $t);
        }
        $this->piece($id, 'permis', 'a_reprendre');
        $this->piece($id, 'atelier', 'remplacee'); // remplacée : ne compte plus
        $l = DossierService::depuisBase()->file('a_reprendre', '', 1, 'desc', $this->t0)['lignes'][0];
        self::assertSame(['cni_recto' => 'envoyee', 'cni_verso' => 'envoyee', 'permis' => 'a_reprendre', 'diplome' => 'envoyee', 'portrait' => 'envoyee', 'atelier' => 'facultative'], $l['pieces']);
        self::assertTrue($l['complet']);
        $vide = $this->mecano('Brice', 'Gnagne', 'en_attente', 6);
        $l2 = DossierService::depuisBase()->file('en_attente', 'Gnagne', 1, 'desc', $this->t0)['lignes'][0];
        self::assertSame('manquante', $l2['pieces']['cni_recto']);
        self::assertFalse($l2['portrait']);
        self::assertFalse($l2['complet']);
        self::assertNotSame($vide, 0);
    }

    public function testIndicateursDuTableauDeBord(): void
    {
        $this->mecano('A', 'Un', 'en_attente', 31);
        $this->mecano('B', 'Deux', 'en_attente', 5);
        $maintenant = $this->t0->format('Y-m-d H:i:s');
        // un mécano disponible (position récente) et un autre dont la position a plus de 12 h : le second n'est pas éligible (RG-R3)
        $this->mecano('C', 'Trois', 'valide', null, ['etat' => 'disponible', 'position_maj_le' => $this->t0->modify('-1 hour')->format('Y-m-d H:i:s')]);
        $this->mecano('D', 'Quatre', 'valide', null, ['etat' => 'disponible', 'position_maj_le' => $this->t0->modify('-13 hours')->format('Y-m-d H:i:s')]);
        $m = (int) $this->db->valeur('SELECT id FROM mecaniciens LIMIT 1');
        $client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'x')]);
        foreach (['arrive', 'arrive', 'pas_entendus', 'pas_reponse'] as $i => $issue) {
            $this->db->inserer('contacts', ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $m, 'canal' => 'tel', 'statut' => 'termine', 'resume' => 'x',
                'issue_client' => $issue, 'cree_le' => $this->t0->modify("-$i days")->format('Y-m-d H:i:s')]);
        }
        $this->db->inserer('contacts', ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $m, 'canal' => 'tel', 'statut' => 'termine', 'resume' => 'x',
            'issue_client' => 'pas_reponse', 'cree_le' => $this->t0->modify('-9 days')->format('Y-m-d H:i:s')]); // hors des 7 jours
        $i = AdminService::depuisBase()->indicateurs($this->t0);
        self::assertSame(2, $i['attente']);
        self::assertSame(31, $i['plus_ancien_h']);
        self::assertSame(1, $i['disponibles']);
        self::assertSame(75, $i['joignables_pct']); // 3 réponses sur 4 ne sont pas « pas de réponse »
        self::assertNotEmpty($maintenant);
    }

    public function testSignalementsOuvertsEtCritiques(): void
    {
        $m = $this->mecano('E', 'Cinq', 'valide', null);
        $mk = fn (string $motif, string $statut, int $heures) => $this->db->inserer('signalements', ['public_id' => Ulid::generer(), 'mecanicien_id' => $m, 'motif' => $motif, 'statut' => $statut,
            'cree_le' => $this->t0->modify("-$heures hours")->format('Y-m-d H:i:s')]);
        $mk('pas_venu', 'nouveau', 2);      // ouvert
        $mk('securite', 'nouveau', 1);      // critique : sécurité
        $mk('prix_abusif', 'en_cours', 30); // critique : en retard (plus de 24 h)
        $mk('autre', 'sans_suite', 50);     // fermé : ne compte pas
        $s = AdminService::depuisBase()->signalementsOuverts($this->t0);
        self::assertSame(['ouverts' => 3, 'critiques' => 2], $s);
        self::assertSame(3, AdminService::depuisBase()->compteurs()['signalements']);
    }

    public function testMisesEnRelationSurTrenteJours(): void
    {
        $m = $this->mecano('F', 'Six', 'valide', null);
        $client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'y')]);
        foreach ([0, 0, 0, 1, 29, 30] as $jours) { // le 30e jour en arrière sort de la fenêtre
            $this->db->inserer('contacts', ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $m, 'canal' => 'tel', 'statut' => 'termine', 'resume' => 'x',
                'cree_le' => $this->t0->modify("-$jours days")->format('Y-m-d H:i:s')]);
        }
        $r = AdminService::depuisBase()->misesEnRelation($this->t0);
        self::assertCount(30, $r['jours']);
        self::assertSame(3, $r['aujourdhui']);
        self::assertSame(5, $r['total']);
        self::assertSame('2026-10-23', $r['jours'][29]['jour']);
        self::assertSame(1, $r['jours'][0]['nombre']);
    }

    public function testLAdminEtSonFiligrane(): void
    {
        $a = AdminService::depuisBase()->admin($this->admin);
        self::assertSame('Aïcha Bamba', $a['nom_complet']);
        self::assertSame('A. Bamba', $a['filigrane']); // RG-P6
        $this->db->modifier('admins', ['actif' => 0], ['id' => $this->admin]);
        self::assertNull(AdminService::depuisBase()->admin($this->admin)); // un compte désactivé perd l'accès
    }

    public function testLeJournalGardeChaqueAction(): void
    {
        $j = JournalAdminService::depuisBase();
        $j->noter($this->admin, 'consulter_piece', 'piece', 42, ['type' => 'cni_recto'], '127.0.0.1');
        $j->noter($this->admin, 'valider_dossier', 'mecanicien', 7, ['de' => 'en_attente', 'vers' => 'valide']);
        $l = $j->dernieres('piece', 42);
        self::assertCount(1, $l);
        self::assertSame('consulter_piece', $l[0]['action']);
        self::assertSame('Aïcha', $l[0]['prenom']);
        self::assertSame(['type' => 'cni_recto'], json_decode((string) $l[0]['details'], true));
        $this->expectException(\InvalidArgumentException::class);
        $j->noter($this->admin, 'x', 'inconnu', 1);
    }
}
