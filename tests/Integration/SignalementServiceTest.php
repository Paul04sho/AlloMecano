<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;
use App\Services\ClientService;
use App\Services\SignalementService;
use PHPUnit\Framework\TestCase;

/** Signalement : un par contact (RG-S1), motif « Autre » avec texte, photo de 300 Ko au plus sans EXIF (RG-S3), numéro de suivi. Tout est annulé à la fin. */
final class SignalementServiceTest extends TestCase
{
    private Database $db;
    private SignalementService $service;
    private int $client;
    private int $autre;
    private int $mecano;
    private string $dossier;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM signalements LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->db->executer('DELETE FROM signalements');
        $this->db->executer('DELETE FROM avis');
        $this->db->executer('DELETE FROM contacts');
        $this->db->executer('DELETE FROM mecaniciens');
        $clients = new ClientService($this->db);
        $this->client = $clients->identifier(null)['id'];
        $this->autre = $clients->identifier(null)['id'];
        $this->mecano = $this->db->inserer('mecaniciens', [
            'public_id' => Ulid::generer(), 'slug' => 'sig-koffi', 'prenom' => 'Koffi', 'nom' => 'Test', 'metier' => 'Test', 'telephone' => '+22507' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'etat' => 'disponible',
        ]);
        $this->dossier = sys_get_temp_dir() . '/sig-' . bin2hex(random_bytes(4));
        $this->service = new SignalementService($this->db, $this->dossier);
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        foreach (glob($this->dossier . '/*') ?: [] as $f) {
            unlink($f);
        }
        if (isset($this->dossier) && is_dir($this->dossier)) {
            rmdir($this->dossier);
        }
    }

    private function contact(?int $client = null): string
    {
        $public = Ulid::generer();
        $this->db->inserer('contacts', [
            'public_id' => $public, 'client_id' => $client ?? $this->client, 'mecanicien_id' => $this->mecano, 'canal' => 'tel', 'statut' => 'initie',
            'resume' => 'Voiture · Batterie', 'quartier_libelle' => 'Riviera 2',
        ]);
        return $public;
    }

    /** @return array{motif: string, description: ?string, prenom: string, telephone: string} */
    private function propre(string $motif = 'pas_venu'): array
    {
        return $this->service->valider(['motif' => $motif, 'description' => '', 'prenom' => 'Aminata', 'telephone' => '07 07 12 34 56', ])['propre'];
    }

    public function testLesSixMotifsDeFigma(): void
    {
        self::assertSame(['Ce n’est pas la personne de la photo', 'Il a demandé de l’argent avant de venir', 'Comportement agressif ou menaçant',
            'Prix très différent de ce qui était annoncé', 'Il n’est jamais venu', 'Autre'], array_values(SignalementService::MOTIFS));
        self::assertSame(['identite', 'securite', 'comportement', 'prix_abusif', 'pas_venu', 'autre'], array_keys(SignalementService::MOTIFS));
    }

    public function testPasDeSignalementSansContact(): void
    {
        self::assertSame('inconnu', $this->service->cible($this->client, 'nexiste-pas', null)['refus']);
        self::assertSame('pas_de_contact', $this->service->cible($this->client, 'sig-koffi', null)['refus']);
        $this->contact($this->autre); // le contact d'un autre client ne compte pas
        self::assertSame('pas_de_contact', $this->service->cible($this->client, 'sig-koffi', null)['refus']);
    }

    public function testUnSeulSignalementParContact(): void
    {
        $premier = $this->contact();
        $r = $this->service->enregistrer($this->client, 'sig-koffi', $premier, $this->propre(), null);
        self::assertArrayHasKey('public_id', $r);
        self::assertSame('deja', $this->service->cible($this->client, 'sig-koffi', $premier)['refus']); // RG-S1
        $second = $this->contact();
        self::assertSame($second, $this->service->cible($this->client, 'sig-koffi', $premier)['cible']['contact']); // un autre contact reste libre
    }

    public function testValidation(): void
    {
        $v = $this->service->valider(['motif' => '', 'description' => '', 'prenom' => '', 'telephone' => '12']);
        self::assertSame('Choisissez ce qui ne va pas.', $v['erreurs']['motif']);
        self::assertArrayHasKey('prenom', $v['erreurs']);
        self::assertArrayHasKey('telephone', $v['erreurs']);
        $autre = $this->service->valider(['motif' => 'autre', 'description' => '  ', 'prenom' => 'Aminata', 'telephone' => '07 07 12 34 56']);
        self::assertSame(['description'], array_keys($autre['erreurs'])); // « Obligatoire seulement si vous choisissez « Autre » »
        self::assertSame([], $this->service->valider(['motif' => 'pas_venu', 'description' => '', 'prenom' => 'Aminata', 'telephone' => '07 07 12 34 56'])['erreurs']);
        self::assertArrayHasKey('description', $this->service->valider(['motif' => 'pas_venu', 'description' => str_repeat('a', 1001), 'prenom' => 'A', 'telephone' => '07 07 12 34 56'])['erreurs']);
        self::assertArrayHasKey('motif', $this->service->valider(['motif' => 'inconnu', 'prenom' => 'A', 'telephone' => '07 07 12 34 56'])['erreurs']);
    }

    public function testNumeroEtFilDeSuivi(): void
    {
        $contact = $this->contact();
        $public = $this->service->enregistrer($this->client, 'sig-koffi', $contact, $this->propre(), null)['public_id'];
        $e = $this->service->envoye($this->client, $public);
        self::assertMatchesRegularExpression('/^SIG-\d{4}-\d{4}$/', $e['numero']);
        self::assertSame('Koffi', $e['prenom']);
        self::assertSame(86400, $e['limite']->getTimestamp() - $e['cree_le']->getTimestamp()); // RG-S2 : lu sous 24 h
        self::assertSame(Format::telephone('+2250707123456'), $e['telephone']);
        self::assertNull($this->service->envoye($this->autre, $public)); // jamais celui d'un autre client
    }

    public function testLaPhotoEstAllegeeSansExifEtHorsDuDossierPublic(): void
    {
        $contact = $this->contact();
        $source = tempnam(sys_get_temp_dir(), 'sig');
        $img = imagecreatetruecolor(800, 800);
        for ($x = 0; $x < 800; $x++) { // du bruit : un fichier lourd
            for ($y = 0; $y < 800; $y++) {
                imagesetpixel($img, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
        imagepng($img, $source);
        self::assertGreaterThan(300 * 1024, filesize($source));
        $r = $this->service->enregistrer($this->client, 'sig-koffi', $contact, $this->propre(), $source);
        unlink($source);
        self::assertArrayHasKey('public_id', $r);
        $fichier = $this->db->valeur('SELECT photo_fichier FROM signalements WHERE public_id = :p', ['p' => $r['public_id']]);
        self::assertSame($r['public_id'] . '.jpg', $fichier);
        $chemin = $this->dossier . '/' . $fichier;
        self::assertLessThanOrEqual(300 * 1024, filesize($chemin)); // RG-S3
        self::assertSame(IMAGETYPE_JPEG, getimagesize($chemin)[2]);
        self::assertStringNotContainsString('Exif', (string) file_get_contents($chemin));
        self::assertStringNotContainsString(realpath(dirname(__DIR__, 2) . '/public'), (string) realpath($chemin));
    }

    public function testUnFichierQuiNEstPasUneImageEstRefuse(): void
    {
        $contact = $this->contact();
        $source = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents($source, '<?php echo 1;');
        $r = $this->service->enregistrer($this->client, 'sig-koffi', $contact, $this->propre(), $source);
        unlink($source);
        self::assertSame('photo', $r['erreur']);
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM signalements'));
    }
}
