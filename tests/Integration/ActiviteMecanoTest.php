<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\AvisMecanoService;
use App\Services\ContactService;
use App\Services\DemandesMecanoService;
use PHPUnit\Framework\TestCase;

/** M3 · Demandes et Avis : ce que les clients ont fait et dit (RG-C7, RG-P1, RG-AV4, RG-AV5, RG-AV6, RG-AV8). Tout est annulé à la fin. */
final class ActiviteMecanoTest extends TestCase
{
    private Database $db;
    private int $mecano;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT distance_m FROM contacts LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'messages_contact', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'photos', 'pieces_dossier', 'mecanicien_vehicules', 'mecanicien_specialites', 'mecaniciens', 'clients'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->mecano = $this->mecano();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    private function mecano(): int
    {
        return $this->db->inserer('mecaniciens', ['public_id' => Ulid::generer(), 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Test', 'email' => 'k' . bin2hex(random_bytes(3)) . '@exemple.ci', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'identite_verifiee_le' => '2026-03-12', 'cree_le' => '2026-02-01 08:47:00']);
    }

    private function client(?string $prenom = null, ?string $telephone = null): int
    {
        return $this->db->inserer('clients', ['jeton_hash' => hash('sha256', random_bytes(8)), 'prenom' => $prenom, 'telephone' => $telephone]);
    }

    /** @param array<string, mixed> $champs */
    private function contact(int $client, string $quand, array $champs = []): int
    {
        return $this->db->inserer('contacts', $champs + ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $this->mecano, 'canal' => 'tel', 'statut' => 'initie', 'resume' => 'Voiture · Batterie',
            'quartier_libelle' => 'Riviera 2', 'distance_m' => 1400, 'cree_le' => $quand]);
    }

    private function maintenant(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 10:14:00', new \DateTimeZone('UTC'));
    }

    // ---------- Demandes ----------

    public function testLesDemandesSontRangeesParJourAvecLeRetourDuClient(): void
    {
        $aminata = $this->client('Aminata', '+2250712343456');
        $this->contact($aminata, '2026-10-21 10:12:00', ['issue_client' => 'pas_reponse']);
        $this->contact($this->client(), '2026-10-21 09:47:00', ['canal' => 'whatsapp', 'issue_client' => 'arrive', 'quartier_libelle' => 'Riviera 4', 'distance_m' => 2600]);
        $this->contact($aminata, '2026-10-20 18:20:00', ['canal' => 'app', 'statut' => 'termine']);
        $this->contact($aminata, '2026-10-19 09:05:00', ['issue_client' => 'arrive']);
        $this->contact($aminata, '2026-10-10 09:05:00'); // au-delà de 7 jours : plus listé
        $l = $this->service()->liste($this->mecano, $this->maintenant());
        self::assertSame(['10:14', 4, '4 clients vous ont contacté'], [$l['maj'], $l['total'], $l['titre']]);
        self::assertSame(['Aujourd’hui · 2', 'Hier · mardi 20 octobre · 1', 'lundi 19 octobre · 1 · numéros masqués'], array_column($l['jours'], 'libelle'));
        [$a, $b] = $l['jours'][0]['lignes'];
        self::assertSame(['10:12', 'Aminata', 'Batterie · Voiture', 'batterie', 'Appel', 'Riviera 2', '1,4' . "\u{00A0}" . 'km', 'non_joint'], [$a['heure'], $a['client'], $a['resume'], $a['code_panne'], $a['canal'], $a['lieu'], $a['distance'], $a['retour']]);
        self::assertSame(['09:47', 'Un client', 'WhatsApp', 'joint', null], [$b['heure'], $b['client'], $b['canal'], $b['retour'], $b['rappel']]); // RG-C7 : pas de numéro, pas de « Rappeler »
        self::assertSame('joint', $l['jours'][1]['lignes'][0]['retour']); // un appel intégré décroché
    }

    public function testUnSeulBoutonJauneVaAuDernierClientNonJoint(): void
    {
        $c = $this->client('Aminata', '+2250712343456');
        $this->contact($c, '2026-10-21 10:12:00', ['issue_client' => 'pas_entendus']);
        $this->contact($c, '2026-10-21 08:05:00', ['issue_client' => 'pas_reponse']);
        $this->contact($c, '2026-10-21 07:40:00', ['issue_client' => 'arrive']);
        $lignes = $this->service()->liste($this->mecano, $this->maintenant())['jours'][0]['lignes'];
        self::assertSame([true, false, false], array_column($lignes, 'principal'));
    }

    public function testLeNumeroDuClientSeMasqueApres48HeuresEtRappelerDisparait(): void
    {
        $c = $this->client('Aminata', '+2250712343456');
        $recent = $this->contact($c, '2026-10-20 12:00:00'); // 22 h
        $ancien = $this->contact($c, '2026-10-19 09:05:00', ['public_id' => Ulid::generer()]); // 49 h
        self::assertGreaterThan(0, $recent + $ancien);
        $jours = $this->service()->liste($this->mecano, $this->maintenant())['jours'];
        $hier = $jours[0]['lignes'][0];
        $lundi = $jours[1]['lignes'][0];
        self::assertSame(['+2250712343456', null], [$hier['rappel'], $hier['masque']]);
        self::assertSame([null, '07 •• •• 34 56'], [$lundi['rappel'], $lundi['masque']]); // RG-P1 : jamais le numéro entier après 48 h
        self::assertTrue($jours[1]['masque']);
        self::assertSame('07 •• •• 34 56', DemandesMecanoService::masquer('+225 07 12 34 34 56'));
    }

    public function testLaPastilleCompteLesClientsNonJointsDes24Heures(): void
    {
        $c = $this->client('Aminata', '+2250712343456');
        $this->contact($c, '2026-10-21 10:12:00', ['issue_client' => 'pas_entendus']);
        $this->contact($c, '2026-10-21 09:00:00', ['issue_client' => 'pas_reponse']);
        $this->contact($c, '2026-10-21 08:00:00', ['issue_client' => 'arrive']);
        $this->contact($c, '2026-10-21 07:00:00', ['canal' => 'app', 'statut' => 'manque']); // un appel intégré manqué compte aussi
        $this->contact($c, '2026-10-21 06:00:00', ['canal' => 'app', 'statut' => 'termine']);
        $this->contact($c, '2026-10-19 10:00:00', ['issue_client' => 'pas_reponse']); // plus de 24 h
        self::assertSame(3, $this->service()->aRappeler($this->mecano, $this->maintenant()));
    }

    public function testSansDemandeOnLeDit(): void
    {
        $l = $this->service()->liste($this->mecano, $this->maintenant());
        self::assertSame([0, 'Aucun client ne vous a encore contacté', []], [$l['total'], $l['titre'], $l['jours']]);
    }

    public function testUnContactGardeSaDistanceMemeSansPositionEtNeMontrePasCelleDUnAutre(): void
    {
        $this->contact($this->client(), '2026-10-21 10:00:00', ['lat' => null, 'lon' => null]);
        $autre = $this->mecano();
        $this->db->inserer('contacts', ['public_id' => Ulid::generer(), 'client_id' => $this->client(), 'mecanicien_id' => $autre, 'canal' => 'tel', 'statut' => 'initie', 'resume' => 'Voiture · Freins', 'cree_le' => '2026-10-21 10:01:00']);
        $l = $this->service()->liste($this->mecano, $this->maintenant());
        self::assertSame(1, $l['total']); // RG-C1 : ses propres demandes seulement
        self::assertSame("1,4\u{00A0}km", $l['jours'][0]['lignes'][0]['distance']);
    }

    public function testLaDistanceDUnContactSeCalculeALaCreation(): void
    {
        $this->db->modifier('mecaniciens', ['dispo_lat' => 5.36, 'dispo_lon' => -3.954, 'etat' => 'disponible', 'position_maj_le' => gmdate('Y-m-d H:i:s'), 'slug' => 'koffi-test'], ['id' => $this->mecano]);
        $public = (string) $this->db->valeur('SELECT public_id FROM mecaniciens WHERE id = :i', ['i' => $this->mecano]);
        $client = $this->client();
        $contexte = ['recherche' => null, 'lieu' => 'Riviera 2', 'repere' => null, 'vehicule' => 'Voiture', 'panne' => 'Batterie', 'lat' => 5.36, 'lon' => -3.963];
        $r = (new ContactService($this->db))->creer($client, $public, 'tel', true, $contexte);
        self::assertEqualsWithDelta(997, (int) $this->db->valeur('SELECT distance_m FROM contacts WHERE public_id = :p', ['p' => $r['public_id']]), 25);
        $r2 = (new ContactService($this->db))->creer($this->client(), $public, 'tel', false, $contexte); // position non partagée : pas de distance (RG-A11)
        self::assertNull($this->db->valeur('SELECT distance_m FROM contacts WHERE public_id = :p', ['p' => $r2['public_id']]));
    }

    // ---------- Avis ----------

    private function avis(int $note, string $quand, ?string $texte = 'Très bien.', string $statut = 'publie', ?int $mecano = null): string
    {
        $contact = $this->contact($this->client('Aminata'), $quand, ['mecanicien_id' => $mecano ?? $this->mecano, 'public_id' => Ulid::generer()]);
        $this->db->inserer('avis', ['contact_id' => $contact, 'mecanicien_id' => $mecano ?? $this->mecano, 'note' => $note, 'commentaire' => $texte, 'prenom_affiche' => 'Aminata', 'panne_libelle' => 'Batterie', 'statut' => $statut, 'cree_le' => $quand]);
        return (string) $this->db->valeur('SELECT public_id FROM contacts WHERE id = :i', ['i' => $contact]);
    }

    private function service(): DemandesMecanoService
    {
        return new DemandesMecanoService($this->db);
    }

    private function avisService(): AvisMecanoService
    {
        return new AvisMecanoService($this->db);
    }

    public function testLaNoteLaRepartitionEtLesPlusRecentsNeCompteQueLesAvisPublies(): void
    {
        foreach ([5, 5, 5, 4] as $i => $note) {
            $this->avis($note, '2026-10-1' . $i . ' 10:00:00');
        }
        $this->avis(1, '2026-10-20 10:00:00', 'Abusif', 'masque'); // RG-AV4 : masqué par l'équipe
        $p = $this->avisService()->page($this->mecano, false, $this->maintenant());
        self::assertSame([4, 4.8, [5 => 3, 4 => 1, 3 => 0, 2 => 0, 1 => 0]], [$p['nb'], $p['note'], $p['repartition']]);
        self::assertSame('12 mars 2026', $p['depuis']);
        self::assertCount(3, $p['avis']);
        self::assertSame(1, $p['reste']);
        self::assertSame(['Aminata', 'Batterie'], [$p['avis'][0]['prenom'], $p['avis'][0]['panne']]); // RG-AV5
        self::assertCount(4, $this->avisService()->page($this->mecano, true, $this->maintenant())['avis']);
    }

    public function testSansAvisIlNYAPasDeNote(): void
    {
        $p = $this->avisService()->page($this->mecano);
        self::assertSame([0, null, []], [$p['nb'], $p['note'], $p['avis']]);
    }

    public function testUneReponsePubliqueParAvisModifiableEtJamaisSurUnAutreMecano(): void
    {
        $contact = $this->avis(5, '2026-10-19 10:00:00');
        $service = $this->avisService();
        self::assertFalse($service->repondre($this->mecano, $contact, '   ')['ok']);
        self::assertFalse($service->repondre($this->mecano, $contact, str_repeat('a', 601))['ok']);
        self::assertTrue($service->repondre($this->mecano, $contact, '  Merci Aminata, bonne route !  ', $this->maintenant())['ok']);
        self::assertSame('Merci Aminata, bonne route !', $service->page($this->mecano)['avis'][0]['reponse']);
        self::assertTrue($service->repondre($this->mecano, $contact, 'Merci beaucoup !')['ok']); // RG-AV8 : modifiable
        self::assertSame('Merci beaucoup !', $service->page($this->mecano)['avis'][0]['reponse']);
        self::assertFalse($service->repondre($this->mecano(), $contact, 'Pas à moi')['ok']); // RG-C1 : pas l'avis d'un autre
        self::assertFalse($service->repondre($this->mecano, Ulid::generer(), 'Inconnu')['ok']);
    }

    public function testOnNeRepondPasAUnAvisMasque(): void
    {
        $contact = $this->avis(1, '2026-10-19 10:00:00', 'Abusif', 'masque');
        self::assertFalse($this->avisService()->repondre($this->mecano, $contact, 'Non')['ok']);
    }

    public function testSignalerUnAvisAbusifEcritALEquipeUneFoisParJour(): void
    {
        $contact = $this->avis(1, '2026-10-19 10:00:00', 'Voleur !');
        $service = $this->avisService();
        $r = $service->signaler($this->mecano, $contact, '::1', $this->maintenant());
        self::assertSame([true, false], [$r['ok'], $r['deja']]);
        $m = $this->db->un('SELECT profil, nom, message FROM messages_contact');
        self::assertSame(['mecano', 'Koffi Yao'], [$m['profil'], $m['nom']]);
        self::assertStringContainsString($contact, $m['message']);
        self::assertStringContainsString('Voleur !', $m['message']);
        self::assertTrue($service->signaler($this->mecano, $contact, '::1', $this->maintenant())['deja']);
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM messages_contact'));
        self::assertFalse($service->signaler($this->mecano(), $contact)['ok']); // pas l'avis d'un autre
    }
}
