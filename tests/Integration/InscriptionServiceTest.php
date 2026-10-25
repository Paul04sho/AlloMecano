<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;
use App\Services\InscriptionService;
use App\Services\MailService;
use App\Services\PieceDossierService;
use PHPUnit\Framework\TestCase;

/** Inscription du mécanicien (M1a à M1d) : compte (RG-C1), brouillon champ par champ (RG-DO1), pièces (RG-DO2), envoi (RG-DO3). Tout est annulé à la fin. */
final class InscriptionServiceTest extends TestCase
{
    private Database $db;
    private InscriptionService $service;
    private string $journalMail;
    private string $racinePieces;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT date_naissance FROM mecaniciens LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'photos', 'pieces_dossier', 'mecanicien_vehicules', 'mecanicien_specialites', 'mecaniciens', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->journalMail = sys_get_temp_dir() . '/mail-' . bin2hex(random_bytes(3)) . '.log';
        $this->racinePieces = sys_get_temp_dir() . '/pieces-' . bin2hex(random_bytes(3));
        mkdir($this->racinePieces);
        $this->service = new InscriptionService($this->db, new MailService($this->journalMail), new PieceDossierService($this->db, $this->racinePieces));
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        if (isset($this->journalMail) && is_file($this->journalMail)) {
            unlink($this->journalMail);
        }
        if (isset($this->racinePieces) && is_dir($this->racinePieces)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->racinePieces, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($this->racinePieces);
        }
    }

    /** @return array<string, string> */
    private function saisie(array $en_plus = []): array
    {
        return $en_plus + ['prenom' => 'Moussa', 'nom' => 'Diabaté', 'telephone' => '07 48 22 16 90', 'email' => 'Moussa.Diabate@Exemple.ci', 'mot_de_passe' => 'Motdepasse1'];
    }

    private function compte(array $en_plus = []): int
    {
        $r = $this->service->creerCompte($this->saisie($en_plus));
        self::assertTrue($r['ok']);
        return $r['id'];
    }

    private function jpeg(int $largeur, int $hauteur): string
    {
        $fichier = tempnam(sys_get_temp_dir(), 'piece') . '.jpg';
        $img = imagecreatetruecolor($largeur, $hauteur);
        imagefilledrectangle($img, 0, 0, $largeur, $hauteur, (int) imagecolorallocate($img, 120, 90, 60));
        imagejpeg($img, $fichier, 90);
        return $fichier;
    }

    /** Dépose les cinq pièces obligatoires. */
    private function toutesLesPieces(int $id): void
    {
        foreach (['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait'] as $type) {
            $source = $this->jpeg(800, 600);
            $r = (new PieceDossierService($this->db, $this->racinePieces))->deposer($id, $type, ['tmp_name' => $source, 'size' => filesize($source), 'error' => UPLOAD_ERR_OK]);
            self::assertTrue($r['ok']);
            unlink($source);
        }
    }

    public function testLeCompteSeCreeEnBrouillonAvecLesCoordonneesNormalisees(): void
    {
        $id = $this->compte();
        $b = $this->service->brouillon($id);
        self::assertSame('brouillon', $b['statut']);
        self::assertSame('+2250748221690', $b['telephone']);
        self::assertSame('moussa.diabate@exemple.ci', $b['email']);
        self::assertSame('voiture', $b['vehicules']); // « Voiture » est choisi d'office
        self::assertTrue(password_verify('Motdepasse1', (string) $this->db->valeur('SELECT mot_de_passe_hash FROM mecaniciens WHERE id = :i', ['i' => $id])));
    }

    public function testChaqueErreurDitQuoiCorriger(): void
    {
        $r = $this->service->creerCompte(['prenom' => '', 'nom' => '', 'telephone' => '', 'email' => 'pas-un-email', 'mot_de_passe' => 'court']);
        self::assertFalse($r['ok']);
        self::assertSame('Indiquez votre prénom.', $r['erreurs']['prenom']);
        self::assertSame('Indiquez votre nom.', $r['erreurs']['nom']);
        self::assertSame('Indiquez le numéro sur lequel les clients vous appelleront.', $r['erreurs']['telephone']);
        self::assertSame('Cette adresse e-mail ne semble pas complète.', $r['erreurs']['email']);
        self::assertSame('8 caractères au minimum, dont 1 chiffre.', $r['erreurs']['mot_de_passe']);
        self::assertStringStartsWith('Il manque 2 chiffres', $this->service->creerCompte($this->saisie(['telephone' => '07 48 22 16']))['erreurs']['telephone']);
    }

    public function testUnNumeroOuUnEmailDejaPrisSontRefuses(): void
    {
        $this->compte();
        $r = $this->service->creerCompte($this->saisie(['prenom' => 'Autre', 'email' => 'autre@exemple.ci']));
        self::assertSame('Ce numéro a déjà un compte. Connectez-vous plutôt.', $r['erreurs']['telephone']);
        $r = $this->service->creerCompte($this->saisie(['telephone' => '05 11 22 33 44']));
        self::assertSame('Cette adresse est déjà utilisée par un compte.', $r['erreurs']['email']);
        // l'e-mail est facultatif (RG-C3 : sans e-mail, un admin réinitialise)
        self::assertTrue($this->service->creerCompte($this->saisie(['telephone' => '05 11 22 33 44', 'email' => '']))['ok']);
    }

    public function testUnCompteGoogleNAPasDeMotDePasse(): void
    {
        $r = $this->service->creerCompte($this->saisie(['mot_de_passe' => '', 'email' => '']), ['sub' => 'google-123', 'email' => 'G@exemple.ci']);
        self::assertTrue($r['ok']);
        self::assertNull($this->db->valeur('SELECT mot_de_passe_hash FROM mecaniciens WHERE id = :i', ['i' => $r['id']]));
        self::assertSame('G@exemple.ci', $this->db->valeur('SELECT email FROM mecaniciens WHERE id = :i', ['i' => $r['id']]));
    }

    public function testChaqueChampDuMetierSEnregistreSeul(): void
    {
        $id = $this->compte();
        $r = $this->service->enregistrerMetier($id, ['specialites' => ['mecanique_generale', 'carrosserie'], 'annees' => '12', 'rayon' => '5', 'presentation' => 'Mécanique générale et tôlerie à Marcory.'], new \DateTimeImmutable('2026-10-23 10:14:00'));
        self::assertSame([], $r['erreurs']);
        self::assertSame('10:14', $r['enregistre_a']);
        $b = $this->service->brouillon($id);
        self::assertSame(['mecanique_generale', 'carrosserie'], $b['specialites']);
        self::assertSame(12, $b['annees']);
        self::assertSame(5, $b['rayon']);
        self::assertSame('Mécanique générale', $this->db->valeur('SELECT metier FROM mecaniciens WHERE id = :i', ['i' => $id])); // le métier suit la première spécialité de la liste
        // un seul champ à la fois : le reste ne bouge pas
        $this->service->enregistrerMetier($id, ['vehicules' => 'les_deux']);
        $b = $this->service->brouillon($id);
        self::assertSame('les_deux', $b['vehicules']);
        self::assertSame(12, $b['annees']);
        $this->service->enregistrerMetier($id, ['vehicules' => 'moto']);
        self::assertSame('moto', $this->service->brouillon($id)['vehicules']);
    }

    public function testLesChampsInvalidesSontRenvoyesSansBloquerLesAutres(): void
    {
        $id = $this->compte();
        $r = $this->service->enregistrerMetier($id, ['annees' => '99', 'rayon' => '7', 'quartier' => 'Nulle part', 'specialites' => ['inconnue'], 'tarif_diagnostic' => 'cinq mille', 'presentation' => str_repeat('a', 141), 'vehicules' => 'camion', 'tarif_deplacement' => '2 000']);
        self::assertEqualsCanonicalizing(['annees', 'rayon', 'quartier', 'specialites', 'tarif_diagnostic', 'presentation', 'vehicules'], array_keys($r['erreurs']));
        self::assertSame('Choisissez un quartier dans la liste.', $r['erreurs']['quartier']);
        self::assertSame(2000, $this->service->brouillon($id)['tarif_deplacement']); // le champ valide, lui, est enregistré
    }

    public function testLeQuartierSeChoisitParSonLibelleEtDonneLeCentreDeLAtelier(): void
    {
        $id = $this->compte();
        $this->service->enregistrerMetier($id, ['quartier' => 'marcory, zone 4']); // sans tenir compte de la casse
        $b = $this->service->brouillon($id);
        self::assertSame('Marcory, Zone 4', $b['quartier_libelle']);
        self::assertNotNull($this->db->valeur('SELECT atelier_lat FROM mecaniciens WHERE id = :i', ['i' => $id]));
        $this->service->enregistrerMetier($id, ['quartier' => '']);
        self::assertNull($this->service->brouillon($id)['quartier']);
    }

    public function testLeDeplacementInclusVautZeroEtSeDecocheSansLaisserDeMontant(): void
    {
        $id = $this->compte();
        $this->service->enregistrerMetier($id, ['tarif_diagnostic' => "5\u{202F}000", 'tarif_deplacement' => '2000']);
        self::assertSame([5000, 2000, false], [$this->service->brouillon($id)['tarif_diagnostic'], $this->service->brouillon($id)['tarif_deplacement'], $this->service->brouillon($id)['deplacement_inclus']]);
        $this->service->enregistrerMetier($id, ['deplacement_inclus' => true]);
        self::assertTrue($this->service->brouillon($id)['deplacement_inclus']);
        $this->service->enregistrerMetier($id, ['deplacement_inclus' => false]);
        self::assertNull($this->service->brouillon($id)['tarif_deplacement']);
    }

    public function testLesPiecesSontRecompresseesSansExifEtRangeesHorsDuPublic(): void
    {
        $id = $this->compte();
        $gros = $this->jpeg(3200, 2400);
        $r = (new PieceDossierService($this->db, $this->racinePieces))->deposer($id, 'permis', ['tmp_name' => $gros, 'size' => filesize($gros), 'error' => UPLOAD_ERR_OK]);
        self::assertTrue($r['ok']);
        $ligne = $this->db->un('SELECT fichier, mime, largeur, hauteur, taille_octets, empreinte_sha256, statut FROM pieces_dossier WHERE mecanicien_id = :m', ['m' => $id]);
        self::assertSame(['image/jpeg', 2200, 1650, 'envoyee'], [$ligne['mime'], (int) $ligne['largeur'], (int) $ligne['hauteur'], $ligne['statut']]);
        self::assertFileExists($this->racinePieces . '/' . $ligne['fichier']);
        self::assertSame(hash_file('sha256', $this->racinePieces . '/' . $ligne['fichier']), $ligne['empreinte_sha256']);
        self::assertSame($ligne['fichier'], str_replace('\\', '/', $ligne['fichier']));
        // un nouvel envoi remplace l'ancien sans l'effacer (historique)
        $petit = $this->jpeg(400, 300);
        $service = new PieceDossierService($this->db, $this->racinePieces);
        $service->deposer($id, 'permis', ['tmp_name' => $petit, 'size' => filesize($petit), 'error' => UPLOAD_ERR_OK]);
        self::assertSame(['remplacee', 'envoyee'], array_column($this->db->tous('SELECT statut FROM pieces_dossier WHERE mecanicien_id = :m ORDER BY id', ['m' => $id]), 'statut'));
        self::assertFileExists($this->racinePieces . '/' . $ligne['fichier']);
        self::assertSame(400, $service->courantes($id)['permis'] ? (int) $this->db->valeur('SELECT largeur FROM pieces_dossier WHERE id = :i', ['i' => $service->courantes($id)['permis']['id']]) : 0);
        unlink($gros);
        unlink($petit);
    }

    public function testUnFichierQuiNEstPasUneImageOuQuiPeseTropEstRefuse(): void
    {
        $id = $this->compte();
        $service = new PieceDossierService($this->db, $this->racinePieces);
        $texte = tempnam(sys_get_temp_dir(), 'piece');
        file_put_contents($texte, 'pas une image');
        self::assertStringStartsWith('Envoyez une photo', $service->deposer($id, 'permis', ['tmp_name' => $texte, 'size' => 13, 'error' => UPLOAD_ERR_OK])['erreur']);
        self::assertSame('Ce fichier est trop lourd : 5 Mo au plus. Reprenez la photo en plus petit.', $service->deposer($id, 'permis', ['tmp_name' => $texte, 'size' => 6 * 1024 * 1024, 'error' => UPLOAD_ERR_OK])['erreur']);
        self::assertSame('Cette pièce n’existe pas.', $service->deposer($id, 'passeport', ['tmp_name' => $texte, 'size' => 13, 'error' => UPLOAD_ERR_OK])['erreur']);
        self::assertSame('Le fichier n’est pas arrivé. Réessayez.', $service->deposer($id, 'permis', ['error' => UPLOAD_ERR_PARTIAL])['erreur']);
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM pieces_dossier'));
        unlink($texte);
    }

    public function testLaPhraseDeCeQuiManque(): void
    {
        self::assertSame('le diplôme et la photo de vous', InscriptionService::phraseManque(['diplome', 'portrait']));
        self::assertSame('la CNI recto, la CNI verso, le permis, le diplôme et la photo de vous', InscriptionService::phraseManque(['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait']));
        self::assertSame('la photo de vous', InscriptionService::phraseManque(['portrait']));
        $id = $this->compte();
        $e = $this->service->etatPieces($id);
        self::assertSame(['manquante', 'manquante', 'manquante', 'manquante', 'manquante', 'facultative'], array_values($e['etats']));
        self::assertSame(0, $e['obligatoires_envoyees']);
    }

    public function testLEnvoiExigeToutLeDossierEtLesTroisAccords(): void
    {
        $id = $this->compte();
        $tous = ['cgu' => '1', 'verification' => '1', 'visibilite' => '1'];
        self::assertSame('Cochez les 3 cases pour envoyer votre dossier.', $this->service->envoyer($id, ['cgu' => '1', 'verification' => '1'])['erreur']);
        $r = $this->service->envoyer($id, $tous);
        self::assertFalse($r['ok']);
        self::assertContains('Choisissez au moins une spécialité.', $r['manques']);
        $this->service->enregistrerMetier($id, ['specialites' => ['moteur'], 'quartier' => 'Marcory, Zone 4']);
        self::assertSame(['Il manque la CNI recto, la CNI verso, le permis, le diplôme et la photo de vous.'], $this->service->envoyer($id, $tous)['manques']);
        self::assertSame('brouillon', $this->service->brouillon($id)['statut']);
    }

    public function testLEnvoiPasseLeDossierEnAttenteEtPrevientToutLeMonde(): void
    {
        $id = $this->compte();
        $this->service->enregistrerMetier($id, ['specialites' => ['moteur'], 'quartier' => 'Marcory, Zone 4']);
        $this->toutesLesPieces($id);
        $r = $this->service->envoyer($id, ['cgu' => 'on', 'verification' => '1', 'visibilite' => 'true'], new \DateTimeImmutable('2026-10-20 10:14:00'));
        self::assertTrue($r['ok']);
        self::assertSame(sprintf('%04d', $id), $r['numero']);
        $b = $this->service->brouillon($id);
        self::assertSame('en_attente', $b['statut']);
        self::assertSame('2026-10-20 10:14:00', $b['envoye_le']->format('Y-m-d H:i:s'));
        self::assertSame('1.0', $this->db->valeur('SELECT cgu_version FROM mecaniciens WHERE id = :i', ['i' => $id]));
        self::assertSame(['brouillon', 'en_attente'], array_values($this->db->un('SELECT de_statut, vers_statut FROM historique_dossier WHERE mecanicien_id = :i', ['i' => $id])));
        self::assertEqualsCanonicalizing(['dossier_recu', 'nouveau_dossier'], array_column($this->db->tous('SELECT type FROM notifications'), 'type'));
        self::assertStringContainsString('vient d’envoyer son dossier', (string) file_get_contents($this->journalMail));
        // il ne s'envoie qu'une fois
        self::assertSame('Ce dossier a déjà été envoyé.', $this->service->envoyer($id, ['cgu' => '1', 'verification' => '1', 'visibilite' => '1'])['erreur']);
        // et l'écran « dossiers à examiner » le voit
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM mecaniciens WHERE statut_dossier = \'en_attente\''));
    }

    public function testLeMecanoModifieSesCoordonneesSansMotDePasse(): void
    {
        $id = $this->compte();
        $r = $this->service->modifierCompte($id, ['prenom' => 'Moussa', 'nom' => 'Diabaté', 'telephone' => '07 48 22 16 91', 'email' => '']);
        self::assertTrue($r['ok']);
        self::assertSame('+2250748221691', $this->service->brouillon($id)['telephone']);
        self::assertSame('', $this->service->brouillon($id)['email']);
        self::assertFalse($this->service->modifierCompte($id, ['prenom' => '', 'nom' => 'x', 'telephone' => '07 48 22 16 91'])['ok']);
    }

    public function testLeNumeroDuDossierSeLitComme0412(): void
    {
        self::assertSame('0412', InscriptionService::numeroAffiche(412));
        self::assertSame('Marcory, Zone 4', InscriptionService::libelleQuartier(['commune_nom' => 'Marcory', 'nom' => 'Zone 4']));
        self::assertSame(Format::INSECABLE, "\u{00A0}");
        self::assertNotSame('', Ulid::generer());
    }
}
