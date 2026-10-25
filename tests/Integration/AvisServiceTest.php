<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\AvisService;
use App\Services\ClientService;
use App\Services\FicheService;
use PHPUnit\Framework\TestCase;

/** Avis : droit après un vrai contact (RG-AV1), un par contact modifiable 24 h (RG-AV2), recalcul de la note (RG-AV6), bandeau (RG-AV7). Tout est annulé à la fin. */
final class AvisServiceTest extends TestCase
{
    private Database $db;
    private AvisService $service;
    private int $client;
    private int $autre;
    private int $mecano;
    private string $mecanoPublic;

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
        $this->db->executer('DELETE FROM pieces_dossier'); // les dossiers de démo de l'administration tiennent aux mécanos
        $this->db->executer('DELETE FROM mecaniciens');
        $clients = new ClientService($this->db);
        $this->client = $clients->identifier(null)['id'];
        $this->autre = $clients->identifier(null)['id'];
        $this->mecanoPublic = Ulid::generer();
        $this->mecano = $this->db->inserer('mecaniciens', [
            'public_id' => $this->mecanoPublic, 'slug' => 'avis-koffi', 'prenom' => 'Koffi', 'nom' => 'Test', 'metier' => 'Test', 'telephone' => '+22507' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'etat' => 'disponible', 'note_moyenne' => null, 'nb_avis' => 0,
        ]);
        $this->service = new AvisService($this->db);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    /** @param array<string, mixed> $champs */
    private function contact(array $champs = [], ?int $client = null): string
    {
        $public = Ulid::generer();
        $this->db->inserer('contacts', $champs + [
            'public_id' => $public, 'client_id' => $client ?? $this->client, 'mecanicien_id' => $this->mecano, 'canal' => 'tel', 'statut' => 'initie',
            'resume' => 'Voiture · Batterie', 'quartier_libelle' => 'Riviera 2', 'issue_client' => 'arrive',
        ]);
        return $public;
    }

    /** @return array{note: int, etiquettes: list<int>, commentaire: ?string, prenom: string, telephone: string} */
    private function saisie(int $note = 4, ?string $commentaire = 'Arrivé en 20 minutes.'): array
    {
        $ids = array_column($this->service->etiquettes(), 'id');
        return ['note' => $note, 'etiquettes' => [$ids[0], $ids[2]], 'commentaire' => $commentaire, 'prenom' => 'Aminata', 'telephone' => '07 07 12 34 56'];
    }

    public function testLesSixEtiquettesDeFigma(): void
    {
        self::assertSame(['Arrivé vite', 'Prix clair', 'Travail propre', 'Poli', 'En retard', 'Plus cher qu’annoncé'], array_column($this->service->etiquettes(), 'libelle'));
    }

    public function testPasDAvisSansVraiContact(): void
    {
        self::assertSame('inconnu', $this->service->droit($this->client, Ulid::generer())['refus']);
        self::assertSame('inconnu', $this->service->droit($this->autre, $this->contact())['refus']); // le contact d'un autre
        $sans = $this->contact(['issue_client' => null]);
        self::assertSame('pas_de_droit', $this->service->droit($this->client, $sans)['refus']); // appui sur « Appeler » seulement
        self::assertSame('pas_de_droit', $this->service->droit($this->client, $this->contact(['issue_client' => 'pas_reponse']))['refus']);
        self::assertArrayHasKey('contact', $this->service->droit($this->client, $this->contact())); // « Il arrive » du client (RG-AV1)
        self::assertArrayHasKey('contact', $this->service->droit($this->client, $this->contact(['issue_client' => null, 'issue_mecano' => 'j_y_vais']))); // « J'y vais » du mécano
    }

    public function testAppelIntegreAssezLongOuvreLeDroit(): void
    {
        $court = $this->contact(['issue_client' => null, 'canal' => 'app', 'statut' => 'termine', 'duree_s' => 20]);
        $long = $this->contact(['issue_client' => null, 'canal' => 'app', 'statut' => 'termine', 'duree_s' => 95]);
        self::assertSame('pas_de_droit', $this->service->droit($this->client, $court)['refus']);
        self::assertArrayHasKey('contact', $this->service->droit($this->client, $long));
    }

    public function testSeptJoursPourDonnerSonAvis(): void
    {
        $vieux = $this->contact(['cree_le' => gmdate('Y-m-d H:i:s', time() - 8 * 86400)]);
        self::assertSame('delai', $this->service->droit($this->client, $vieux)['refus']);
        self::assertArrayHasKey('contact', $this->service->droit($this->client, $this->contact(['cree_le' => gmdate('Y-m-d H:i:s', time() - 6 * 86400)])));
    }

    public function testLaSaisieEstVerifiee(): void
    {
        $r = $this->service->valider(['note' => '', 'prenom' => '', 'telephone' => '07 12', 'commentaire' => str_repeat('a', 601)]);
        self::assertSame(['note', 'commentaire', 'prenom', 'telephone'], array_keys($r['erreurs']));
        self::assertSame('Touchez une étoile pour donner votre note.', $r['erreurs']['note']);
        self::assertSame([], $this->service->valider($this->saisie() + ['etiquettes' => []])['erreurs']);
        $r = $this->service->valider(['note' => 9, 'prenom' => 'A', 'telephone' => '0707123456']);
        self::assertArrayHasKey('note', $r['erreurs']);
        $ok = $this->service->valider(['note' => 5, 'prenom' => ' Aminata ', 'telephone' => '0707123456', 'etiquettes' => ['1', '999', 'x'], 'commentaire' => '  ']);
        self::assertSame([1], $ok['propre']['etiquettes']); // une étiquette inconnue est ignorée
        self::assertNull($ok['propre']['commentaire']);
        self::assertSame('Aminata', $ok['propre']['prenom']);
    }

    public function testPublierEstImmediatEtRecalculeLaNote(): void
    {
        $r = $this->service->publier($this->client, $this->contact(), $this->saisie(4));
        self::assertSame(4, $r['avis']['note']);
        self::assertSame('Batterie', $r['avis']['panne']);
        self::assertSame(2, count($r['avis']['etiquettes']));
        $meca = $this->db->un('SELECT note_moyenne, nb_avis FROM mecaniciens WHERE id = :m', ['m' => $this->mecano]);
        self::assertSame(1, (int) $meca['nb_avis']); // RG-AV6
        self::assertSame(4.0, (float) $meca['note_moyenne']);
        self::assertSame('publie', $this->db->valeur('SELECT statut FROM avis WHERE contact_id = (SELECT id FROM contacts WHERE client_id = :c LIMIT 1)', ['c' => $this->client])); // RG-AV4
        // le client garde son prénom et son numéro, jamais montrés
        $client = (new ClientService($this->db))->contact($this->client);
        self::assertSame('Aminata', $client['prenom']);
        self::assertSame('+2250707123456', $client['telephone']);
    }

    public function testNouveauSousTroisAvisPuisLaNoteApparait(): void
    {
        $fiche = new FicheService($this->db);
        foreach ([5, 4, 3] as $i => $note) {
            $this->service->publier($this->client, $this->contact(), $this->saisie($note));
            if ($i < 2) {
                self::assertNull($fiche->fiche('avis-koffi')['note']);
            }
        }
        self::assertSame(4.0, $fiche->fiche('avis-koffi')['note']);
    }

    public function testUnSeulAvisParContactModifiableVingtQuatreHeures(): void
    {
        $c = $this->contact();
        $this->service->publier($this->client, $c, $this->saisie(2, 'Bof'));
        $r = $this->service->publier($this->client, $c, $this->saisie(5, 'Finalement très bien'));
        self::assertSame(5, $r['avis']['note']);
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM avis'));
        self::assertSame(5.0, (float) $this->db->valeur('SELECT note_moyenne FROM mecaniciens WHERE id = :m', ['m' => $this->mecano]));
        $this->db->executer('UPDATE avis SET cree_le = UTC_TIMESTAMP() - INTERVAL 25 HOUR');
        self::assertSame('verrouille', $this->service->publier($this->client, $c, $this->saisie(1, 'Trop tard'))['erreur']); // RG-AV2
        self::assertSame(5, (int) $this->db->valeur('SELECT note FROM avis'));
        self::assertSame('pas_de_droit', $this->service->publier($this->autre, $this->contact(['issue_client' => null], $this->autre), $this->saisie())['erreur']);
    }

    public function testLeBandeauDeuxHeuresApresPendantVingtQuatreHeures(): void
    {
        $maintenant = time();
        $c = $this->contact(['issue_client_le' => gmdate('Y-m-d H:i:s', $maintenant - 3600)]);
        self::assertNull($this->service->bandeau($this->client)); // trop tôt : moins de 2 h
        $this->db->executer('UPDATE contacts SET issue_client_le = UTC_TIMESTAMP() - INTERVAL 3 HOUR WHERE public_id = :p', ['p' => $c]);
        self::assertSame($c, $this->service->bandeau($this->client)['public_id']); // RG-AV7
        self::assertNull($this->service->bandeau($this->autre));
        $this->db->executer('UPDATE contacts SET issue_client_le = UTC_TIMESTAMP() - INTERVAL 27 HOUR WHERE public_id = :p', ['p' => $c]);
        self::assertNull($this->service->bandeau($this->client)); // plus de 24 h de bandeau
        $this->db->executer('UPDATE contacts SET issue_client_le = UTC_TIMESTAMP() - INTERVAL 5 HOUR WHERE public_id = :p', ['p' => $c]);
        $this->service->publier($this->client, $c, $this->saisie());
        self::assertNull($this->service->bandeau($this->client)); // il a donné son avis
    }
}
