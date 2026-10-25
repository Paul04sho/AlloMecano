<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\ExamenDossierService;
use App\Services\FiligranePiece;
use App\Services\MailService;
use App\Services\PhotoPubliqueService;
use PHPUnit\Framework\TestCase;

/** A2 · Examen d'un dossier : message exact, décisions pièce par pièce, validation (RG-DO5, RG-DO9), renvoi (RG-DO4), refus (RG-DO6), journal (RG-C5, RG-P6). Tout est annulé à la fin. */
final class ExamenDossierTest extends TestCase
{
    private Database $db;
    private int $admin;
    private string $dossierPieces;
    private string $medias;
    private string $journalMail;
    private ExamenDossierService $service;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT date_naissance FROM mecaniciens LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'photos', 'historique_dossier', 'signalements', 'avis', 'contacts', 'pieces_dossier', 'mecanicien_specialites', 'mecaniciens', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->admin = $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->dossierPieces = dirname(__DIR__, 2) . '/storage/pieces/test-' . bin2hex(random_bytes(3));
        mkdir($this->dossierPieces, 0775, true);
        $this->medias = sys_get_temp_dir() . '/medias-' . bin2hex(random_bytes(3));
        $this->journalMail = sys_get_temp_dir() . '/mail-' . bin2hex(random_bytes(3)) . '.log';
        $this->service = new ExamenDossierService($this->db, new PhotoPubliqueService($this->medias), new MailService($this->journalMail));
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        foreach ([$this->dossierPieces ?? null, $this->medias ?? null] as $d) {
            if ($d !== null && is_dir($d)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($d, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($d);
            }
        }
        if (isset($this->journalMail) && is_file($this->journalMail)) {
            unlink($this->journalMail);
        }
    }

    /** @param list<string> $sans pièces à ne pas créer */
    private function dossier(string $statut = 'en_attente', array $sans = [], ?string $email = null): string
    {
        $public = Ulid::generer();
        $id = $this->db->inserer('mecaniciens', ['public_id' => $public, 'prenom' => 'Moussa', 'nom' => 'Diabaté', 'metier' => 'Tôlerie', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'email' => $email, 'mot_de_passe_hash' => 'x', 'statut_dossier' => $statut, 'dossier_envoye_le' => gmdate('Y-m-d H:i:s', time() - 31 * 3600), 'date_naissance' => '1991-05-14']);
        foreach (ExamenDossierService::PIECES as $type) {
            if (in_array($type, $sans, true) || ($type === 'atelier' && !in_array('avec-atelier', $sans, true) && true)) {
                continue;
            }
            $this->piece($id, $type);
        }
        return $public;
    }

    private function piece(int $mecano, string $type): void
    {
        $fichier = basename($this->dossierPieces) . "/$type.jpg";
        $img = imagecreatetruecolor(400, 500);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 120, 60));
        imagejpeg($img, dirname(__DIR__, 2) . '/storage/pieces/' . $fichier);
        $this->db->inserer('pieces_dossier', ['mecanicien_id' => $mecano, 'type_piece' => $type, 'fichier' => $fichier, 'mime' => 'image/jpeg', 'taille_octets' => 1000,
            'empreinte_sha256' => str_repeat('b', 64), 'statut' => 'envoyee', 'cree_le' => gmdate('Y-m-d H:i:s', time() - 31 * 3600)]);
    }

    /** @return array<string, mixed> */
    private function toutConforme(array $en_plus = []): array
    {
        return $en_plus + ['action' => 'valider', 'piece' => ['cni_recto' => 'conforme', 'cni_verso' => 'conforme', 'permis' => 'conforme', 'diplome' => 'conforme', 'portrait' => 'conforme'],
            'concordance' => ['nom' => '1', 'prenom' => '1', 'ne_le' => '1'], 'visage' => '1'];
    }

    public function testLeMessageEstCeluiDeFigma(): void
    {
        $m = ExamenDossierService::message('Moussa', ['permis' => ['motif' => 'piece_expiree', 'precision' => 'Le permis a expiré le 3 mars 2026.']]);
        self::assertSame('Bonjour Moussa, votre dossier est presque complet. Une pièce est à reprendre : votre permis de conduire. Motif : pièce expirée. Le permis a expiré le 3 mars 2026. '
            . 'Envoyez une photo de votre permis en cours de validité depuis votre espace. Les autres pièces sont conformes, vous n’avez rien d’autre à refaire.', $m);
        self::assertSame('', ExamenDossierService::message('Moussa', []));
        $deux = ExamenDossierService::message('Awa', ['cni_verso' => ['motif' => 'photo_floue', 'precision' => ''], 'diplome' => ['motif' => 'autre', 'precision' => 'Pas le bon document']]);
        self::assertStringContainsString('2 pièces sont à reprendre : le verso de votre pièce d’identité et votre diplôme ou attestation de métier.', $deux);
        self::assertStringContainsString('Pas le bon document.', $deux);
        self::assertStringContainsString('Envoyez de nouvelles photos', $deux);
    }

    public function testLeDossierEtSaPosition(): void
    {
        $a = $this->dossier();
        $d = $this->service->dossier($a);
        self::assertSame('en_attente', $d['statut']);
        self::assertSame('Moussa Diabaté', $d['nom_complet']);
        self::assertSame('1991-05-14', $d['declare']['ne_le']->format('Y-m-d'));
        self::assertNull($d['pieces']['atelier']);
        self::assertSame(5, count(array_filter($d['pieces'])));
        self::assertSame(['rang' => 1, 'total' => 1, 'suivant' => null], $this->service->position($a));
        self::assertNull($this->service->dossier(Ulid::generer()));
    }

    public function testValiderExigeCinqPiecesConformesEtLesConcordances(): void
    {
        $p = $this->dossier();
        $r = $this->service->decider($this->admin, $p, $this->toutConforme(['piece' => ['cni_recto' => 'conforme']]), '127.0.0.1');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('Examinez les cinq pièces', $r['erreur']);
        $r = $this->service->decider($this->admin, $p, $this->toutConforme(['concordance' => ['nom' => '1']]), '127.0.0.1');
        self::assertStringContainsString('trois concordances', $r['erreur']);
        $r = $this->service->decider($this->admin, $p, $this->toutConforme(['visage' => '']), '127.0.0.1');
        self::assertStringContainsString('Le visage correspond', $r['erreur']);
        $s = $this->toutConforme();
        $s['piece']['permis'] = 'a_reprendre';
        $s['motif']['permis'] = 'piece_expiree';
        self::assertStringContainsString('une pièce est à reprendre', $this->service->decider($this->admin, $p, $s, '127.0.0.1')['erreur']);
        self::assertSame('en_attente', $this->service->dossier($p)['statut']); // rien n'a bougé
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin'));
    }

    public function testValiderPublieLeDossierEtGenereLesPhotosPubliques(): void
    {
        $p = $this->dossier('en_attente', [], 'moussa@exemple.ci');
        $r = $this->service->decider($this->admin, $p, $this->toutConforme(), '127.0.0.1', new \DateTimeImmutable('2026-10-23 17:14:00', new \DateTimeZone('UTC')));
        self::assertSame(['ok' => true, 'issue' => 'valide'], $r);
        $m = $this->db->un('SELECT statut_dossier, identite_verifiee_le, valide_par_admin_id, slug FROM mecaniciens WHERE public_id = :p', ['p' => $p]);
        self::assertSame('valide', $m['statut_dossier']);
        self::assertSame('2026-10-23', $m['identite_verifiee_le']); // RG-DO5
        self::assertSame($this->admin, (int) $m['valide_par_admin_id']);
        self::assertSame('moussa-diabate', $m['slug']);
        $photo = $this->db->un('SELECT dossier, largeurs, ratio, couleur_dominante FROM photos WHERE type = \'portrait\' AND active = 1');
        self::assertNotNull($photo); // RG-DO9
        self::assertSame('0.800', $photo['ratio']);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $photo['couleur_dominante']);
        self::assertNotSame('#000000', $photo['couleur_dominante']);
        foreach ([160, 360] as $w) {
            self::assertFileExists($this->medias . '/' . $photo['dossier'] . "-$w.webp");
        }
        self::assertStringStartsWith('m/' . $p . '/portrait-', $photo['dossier']);
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM photos WHERE type = \'atelier\'')); // atelier non fourni
        self::assertSame('validee', $this->db->valeur('SELECT statut FROM pieces_dossier WHERE type_piece = \'permis\''));
        // journal : cinq pièces conformes, concordances, validation ; historique ; l'e-mail est parti
        $actions = array_column($this->db->tous('SELECT action FROM journal_admin ORDER BY id'), 'action');
        self::assertSame(['piece_conforme' => 5, 'concordances' => 1, 'valider_dossier' => 1], array_count_values($actions));
        self::assertSame('valide', $this->db->valeur('SELECT vers_statut FROM historique_dossier'));
        self::assertStringContainsString('votre dossier est validé', (string) file_get_contents($this->journalMail));
        self::assertSame('envoyee', $this->db->valeur('SELECT statut FROM notifications WHERE type = \'dossier_valide\''));
        // un dossier validé n'est plus décidable
        self::assertStringContainsString('n’est plus en attente', $this->service->decider($this->admin, $p, $this->toutConforme(), '127.0.0.1')['erreur']);
    }

    public function testRenvoyerPourCorrectionAvecLeMessageExact(): void
    {
        $p = $this->dossier();
        $s = $this->toutConforme(['action' => 'renvoyer']);
        $s['piece']['permis'] = 'a_reprendre';
        $s['motif']['permis'] = 'piece_expiree';
        $s['precision']['permis'] = 'Le permis a expiré le 3 mars 2026.';
        self::assertSame(['ok' => true, 'issue' => 'a_reprendre'], $this->service->decider($this->admin, $p, $s, '127.0.0.1')); // RG-DO4 : une seule pièce suffit
        self::assertSame('a_reprendre', $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE public_id = :p', ['p' => $p]));
        $permis = $this->db->un('SELECT statut, motif FROM pieces_dossier WHERE type_piece = \'permis\'');
        self::assertSame(['statut' => 'a_reprendre', 'motif' => 'Pièce expirée · Le permis a expiré le 3 mars 2026.'], $permis);
        $n = $this->db->un('SELECT canal, statut, charge FROM notifications WHERE type = \'dossier_a_reprendre\'');
        self::assertSame(['canal' => 'push', 'statut' => 'en_attente'], ['canal' => $n['canal'], 'statut' => $n['statut']]); // pas d'e-mail : la notification attend dans son espace
        self::assertStringContainsString('Motif : pièce expirée. Le permis a expiré le 3 mars 2026.', json_decode($n['charge'], true)['texte']);
        self::assertContains('renvoyer_dossier', array_column($this->db->tous('SELECT action FROM journal_admin'), 'action'));
    }

    public function testRenvoyerExigeUnMotifEtUneDecisionPourChaquePiece(): void
    {
        $p = $this->dossier();
        $s = $this->toutConforme(['action' => 'renvoyer']);
        self::assertStringContainsString('Rien à renvoyer', $this->service->decider($this->admin, $p, $s, '127.0.0.1')['erreur']);
        $s['piece']['permis'] = 'a_reprendre';
        self::assertStringContainsString('Choisissez le motif', $this->service->decider($this->admin, $p, $s, '127.0.0.1')['erreur']);
        $s['motif']['permis'] = 'autre';
        self::assertStringContainsString('précisez-le', $this->service->decider($this->admin, $p, $s, '127.0.0.1')['erreur']); // « Autre » demande un texte
        unset($s['piece']['diplome']);
        $s['motif']['permis'] = 'photo_floue';
        self::assertStringContainsString('Examinez les cinq pièces', $this->service->decider($this->admin, $p, $s, '127.0.0.1')['erreur']);
    }

    public function testRefuserDemandeUnMotifEtEstDefinitif(): void
    {
        $p = $this->dossier();
        self::assertStringContainsString('indiquez le motif', $this->service->decider($this->admin, $p, ['action' => 'refuser', 'motif_refus' => '  '], '127.0.0.1')['erreur']);
        self::assertSame(['ok' => true, 'issue' => 'refuse'], $this->service->decider($this->admin, $p, ['action' => 'refuser', 'motif_refus' => 'Les pièces ne correspondent pas à la personne.'], '127.0.0.1'));
        $m = $this->db->un('SELECT statut_dossier, motif_refus FROM mecaniciens WHERE public_id = :p', ['p' => $p]);
        self::assertSame(['statut_dossier' => 'refuse', 'motif_refus' => 'Les pièces ne correspondent pas à la personne.'], $m);
        self::assertSame('refuse', $this->db->valeur('SELECT vers_statut FROM historique_dossier'));
        self::assertContains('dossier_refuse', array_column($this->db->tous('SELECT type FROM notifications'), 'type'));
    }

    public function testChaqueConsultationDePieceEstJournalisee(): void
    {
        $p = $this->dossier();
        $f = $this->service->consulter($this->admin, $p, 'cni_recto', '127.0.0.1');
        self::assertNotNull($f);
        self::assertFileExists($f['chemin']);
        $this->service->consulter($this->admin, $p, 'cni_recto', '127.0.0.1');
        self::assertSame(2, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin WHERE action = \'consulter_piece\''));
        self::assertNull($this->service->consulter($this->admin, $p, 'atelier', '127.0.0.1')); // pas fournie
        self::assertNull($this->service->consulter($this->admin, $p, '../../etc/passwd', '127.0.0.1'));
        self::assertNull($this->service->consulter($this->admin, Ulid::generer(), 'permis', '127.0.0.1'));
        $d = $this->service->dossier($p);
        $journal = $this->service->journal($d);
        self::assertSame('Envoie son dossier : 5 pièces obligatoires', end($journal)['texte']);
        self::assertSame('Consulte la CNI recto', $journal[0]['texte']);
        self::assertSame('Aïcha Bamba', $journal[0]['qui']);
        self::assertArrayHasKey($d['pieces']['cni_recto']['id'], $d['consultees']);
    }

    public function testOuvrirNeJournalisePasDeuxFoisDeSuite(): void
    {
        $d = $this->service->dossier($this->dossier());
        $this->service->ouvrir($this->admin, $d, '127.0.0.1');
        $this->service->ouvrir($this->admin, $d, '127.0.0.1');
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin WHERE action = \'ouvrir_dossier\''));
    }

    public function testLeFiligraneEstIncrusteDansLImage(): void
    {
        $fichier = $this->dossierPieces . '/clair.jpg';
        $img = imagecreatetruecolor(600, 400);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 20, 20, 20));
        imagejpeg($img, $fichier);
        $jpeg = FiligranePiece::rendre($fichier, 'ALLOMECANO - VERIFICATION - A. BAMBA', 0.8);
        self::assertNotNull($jpeg);
        $sortie = imagecreatefromstring($jpeg);
        $clairs = 0;
        for ($x = 0; $x < 600; $x += 3) {
            for ($y = 0; $y < 400; $y += 3) {
                $clairs += (imagecolorat($sortie, $x, $y) & 0xFF) > 60 ? 1 : 0;
            }
        }
        self::assertGreaterThan(200, $clairs); // du texte clair a été posé sur un fond presque noir
        file_put_contents($this->dossierPieces . '/texte.txt', 'pas une image');
        self::assertNull(FiligranePiece::rendre($this->dossierPieces . '/texte.txt', 'x'));
    }
}
