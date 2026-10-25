<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;
use App\Services\StatistiquesService;
use PHPUnit\Framework\TestCase;

/** A5 · Statistiques : fenêtres de 7 et 30 jours pleins, entonnoir et conversions, délai de validation, reproposition, passages automatiques à indisponible. Tout est annulé à la fin. */
final class StatistiquesServiceTest extends TestCase
{
    private Database $db;
    private int $admin;
    private int $client;
    private int $panne;
    private \DateTimeImmutable $t0;
    private StatistiquesService $service;

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
        $this->client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'c')]);
        $this->panne = (int) $this->db->valeur('SELECT id FROM pannes ORDER BY id LIMIT 1');
        $this->t0 = new \DateTimeImmutable('2026-10-23 12:00:00', new \DateTimeZone('UTC'));
        $this->service = new StatistiquesService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    private function mecano(?string $envoye = null): int
    {
        return $this->db->inserer('mecaniciens', ['public_id' => Ulid::generer(), 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Test', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'cree_le' => '2026-02-01 08:47:00', 'dossier_envoye_le' => $envoye]);
    }

    /** @param array<string, mixed> $champs */
    private function contact(int $mecano, string $quand, array $champs = []): int
    {
        return $this->db->inserer('contacts', $champs + ['public_id' => Ulid::generer(), 'client_id' => $this->client, 'mecanicien_id' => $mecano, 'canal' => 'tel', 'statut' => 'termine', 'resume' => 'Voiture · Batterie', 'cree_le' => $quand]);
    }

    private function recherche(string $quand): void
    {
        $this->db->inserer('recherches', ['public_id' => Ulid::generer(), 'client_id' => $this->client, 'type_vehicule' => 'voiture', 'panne_id' => $this->panne, 'source_position' => 'gps', 'rayon_km' => 5, 'nb_resultats' => 3, 'cree_le' => $quand]);
    }

    public function testLesPeriodesSontDesJoursPleinsQuiFinissentAujourdhui(): void
    {
        self::assertSame(30, StatistiquesService::periode('30'));
        self::assertSame(30, StatistiquesService::periode('1000'));
        self::assertSame(7, StatistiquesService::periode('7'));
        self::assertSame('2026-09-24 00:00:00', StatistiquesService::debut($this->t0, 30)->format('Y-m-d H:i:s')); // « du 24 septembre au 23 octobre »
        self::assertSame('2026-10-17 00:00:00', StatistiquesService::debut($this->t0, 7)->format('Y-m-d H:i:s'));
    }

    public function testLEntonnoirCompteLesEtapesSurLesDeuxPeriodesEtCalculeLesConversions(): void
    {
        $m = $this->mecano();
        foreach (['2026-10-23 08:00:00', '2026-10-20 08:00:00', '2026-10-10 08:00:00', '2026-09-24 00:00:00', '2026-09-23 23:59:59'] as $q) {
            $this->recherche($q); // les deux dernières : 30 jours pile / un jour trop tôt
        }
        $this->contact($m, '2026-10-22 10:00:00', ['issue_client' => 'arrive']);
        $this->contact($m, '2026-10-11 10:00:00', ['issue_client' => 'pas_entendus']);
        $this->contact($m, '2026-10-12 10:00:00', ['issue_client' => 'pas_reponse']);
        $this->contact($m, '2026-10-12 11:00:00');
        $c = $this->contact($m, '2026-10-22 11:00:00', ['issue_client' => 'arrive']);
        $this->db->inserer('avis', ['contact_id' => $c, 'mecanicien_id' => $m, 'note' => 5, 'panne_libelle' => 'Batterie', 'cree_le' => '2026-10-22 12:00:00']);
        $s = $this->service->tout(30, $this->t0);
        $e = array_column($s['etapes'], null, 'cle');
        self::assertSame([4, 5, 3, 1], [$e['recherches']['valeur'], $e['contacts']['valeur'], $e['joints']['valeur'], $e['avis']['valeur']]);
        self::assertSame([2, 2, 2, 1], [$e['recherches']['autre'], $e['contacts']['autre'], $e['joints']['autre'], $e['avis']['autre']]); // 7 jours : 17 → 23 octobre
        self::assertSame(100.0, $e['recherches']['largeur']);
        self::assertSame(125.0, $e['contacts']['largeur']); // la barre suit la première étape : ici plus de contacts que de recherches
        self::assertSame('base', $e['recherches']['conversion']);
        self::assertSame("125\u{202F}% des recherches", $e['contacts']['conversion']);
        self::assertSame("60\u{202F}% des contacts", $e['joints']['conversion']);
        self::assertSame("33\u{202F}% de ceux qui ont pu joindre", $e['avis']['conversion']);
        $sept = $this->service->tout(7, $this->t0);
        self::assertSame(7, $sept['periode']);
        self::assertSame(30, $sept['autre']);
        self::assertSame(2, $sept['etapes'][0]['valeur']);
        self::assertSame(4, $sept['etapes'][0]['autre']);
    }

    public function testSansRechercheLaConversionResteVide(): void
    {
        $s = $this->service->tout(30, $this->t0);
        self::assertSame([0, 0, 0, 0], array_column($s['etapes'], 'valeur'));
        self::assertSame(['base', null, null, null], array_column($s['etapes'], 'conversion'));
        self::assertNull($s['reproposition']['pct']);
        self::assertNull($s['validation']['heures']);
        self::assertSame(0, $s['indisponibles']['mecanos']);
    }

    public function testLeTauxDeRepropositionVientDesContactsSansReponseSuivisDunAutreAppel(): void
    {
        $m = $this->mecano();
        $a = $this->contact($m, '2026-10-20 10:00:00', ['issue_client' => 'pas_reponse']);
        $this->contact($m, '2026-10-20 10:03:00', ['contact_precedent_id' => $a]); // le mécano proposé ensuite
        $this->contact($m, '2026-10-21 10:00:00', ['statut' => 'manque']);              // appel manqué, rien ensuite
        $this->contact($m, '2026-10-21 12:00:00', ['issue_client' => 'arrive']);        // joint : ne compte pas
        $this->contact($m, '2026-08-01 10:00:00', ['issue_client' => 'pas_reponse']);   // hors période
        $r = $this->service->tout(30, $this->t0)['reproposition'];
        self::assertSame(['pct' => 50, 'reproposes' => 1, 'sans_reponse' => 2], $r);
    }

    public function testLeDelaiDeValidationVaDeLEnvoiALaDecisionDeLEquipe(): void
    {
        $h = fn (int $mecano, string $vers, string $quand, ?int $admin) => $this->db->inserer('historique_dossier', ['mecanicien_id' => $mecano, 'de_statut' => 'en_attente', 'vers_statut' => $vers, 'admin_id' => $admin, 'cree_le' => $quand]);
        $a = $this->mecano('2026-10-20 08:00:00');
        $h($a, 'valide', '2026-10-21 04:00:00', $this->admin);   // 20 h
        $b = $this->mecano('2026-10-22 08:00:00');
        $h($b, 'refuse', '2026-10-23 08:00:00', $this->admin);   // 24 h
        $c = $this->mecano('2026-10-22 08:00:00');
        $h($c, 'a_reprendre', '2026-10-21 08:00:00', $this->admin); // décision d'avant le dernier envoi : ignorée
        $d = $this->mecano('2026-10-20 08:00:00');
        $h($d, 'valide', '2026-10-20 20:00:00', null);           // pas une décision d'admin : ignorée
        $e = $this->mecano('2026-09-10 08:00:00');
        $h($e, 'valide', '2026-09-12 08:00:00', $this->admin);   // 48 h, le mois dernier
        $v = $this->service->tout(30, $this->t0)['validation'];
        self::assertSame(['heures' => 22, 'decisions' => 2, 'objectif' => 48, 'mois_dernier' => 48, 'plus_long' => 24], $v);
        self::assertSame(2, $this->service->tout(7, $this->t0)['validation']['decisions']);
    }

    public function testLesPassagesAutomatiquesDistinguentCeuxQuiReviennentDansLaJournee(): void
    {
        $i = fn (int $m, string $etat, string $raison, string $debut) => $this->db->inserer('historique_disponibilite', ['mecanicien_id' => $m, 'etat' => $etat, 'raison' => $raison, 'debut' => $debut]);
        [$a, $b, $c] = [$this->mecano(), $this->mecano(), $this->mecano()];
        $i($a, 'indisponible', 'auto_injoignable', '2026-10-20 10:00:00');
        $i($a, 'disponible', 'manuel', '2026-10-20 15:00:00');          // revenu dans la journée
        $i($b, 'indisponible', 'auto_injoignable', '2026-10-21 10:00:00');
        $i($b, 'disponible', 'manuel', '2026-10-23 10:00:00');          // revenu, mais bien plus tard
        $i($c, 'indisponible', 'auto_12h', '2026-10-21 10:00:00');      // une autre cause : ne compte pas
        $i($c, 'indisponible', 'auto_injoignable', '2026-07-01 10:00:00'); // hors période
        $x = $this->service->tout(30, $this->t0)['indisponibles'];
        self::assertSame(['mecanos' => 2, 'revenus' => 1, 'seuil' => 3], $x);
    }

    public function testLExportCsvRepeteLEcranSansRienDeNominatif(): void
    {
        $m = $this->mecano();
        $this->recherche('2026-10-22 08:00:00');
        $this->contact($m, '2026-10-22 09:00:00', ['issue_client' => 'arrive']);
        $csv = StatistiquesService::csv($this->service->tout(7, $this->t0));
        self::assertStringStartsWith("\u{FEFF}\"Statistiques allomécano\";\"du 17 octobre 2026 au 23 octobre 2026\"\r\n", $csv);
        self::assertStringContainsString("\"Étape\";\"7 jours\";\"30 jours\";\"Conversion\"\r\n", $csv);
        self::assertStringContainsString("\"Recherches\";\"1\";\"1\";\"base\"\r\n", $csv);
        self::assertStringContainsString("\"Contacts\";\"1\";\"1\";\"100 % des recherches\"\r\n", $csv);
        self::assertStringContainsString('"Taux de reproposition (%)";"";"0 clients sur 0 sans réponse"', $csv);
        self::assertStringNotContainsString('+225', $csv);
        self::assertStringNotContainsString(Format::FINE, $csv);
    }
}
