<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\ProfilMecanoService;
use PHPUnit\Framework\TestCase;

/** M4 · Mon profil : la fiche, le changement sensible (RG-DO7), le téléphone, le mot de passe (RG-C4), les photos de l'atelier, la suppression du compte (RG-C6). Tout est annulé à la fin. */
final class ProfilMecanoServiceTest extends TestCase
{
    private Database $db;
    private ProfilMecanoService $service;
    private string $racine;
    private string $racinePieces;
    private int $id;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT mot_de_passe_modifie_le FROM mecaniciens LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        if (!function_exists('imagewebp')) {
            self::markTestSkipped('GD sans WebP.');
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'abonnements_push', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'photos', 'pieces_dossier', 'mecanicien_vehicules', 'mecanicien_specialites', 'mecaniciens', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->racine = sys_get_temp_dir() . '/medias-' . bin2hex(random_bytes(3));
        $this->racinePieces = sys_get_temp_dir() . '/pieces-' . bin2hex(random_bytes(3));
        mkdir($this->racine);
        mkdir($this->racinePieces);
        $this->service = new ProfilMecanoService($this->db, $this->racine, $this->racinePieces);
        $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->id = $this->mecano();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        foreach ([$this->racine ?? null, $this->racinePieces ?? null] as $dossier) {
            if ($dossier !== null && is_dir($dossier)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dossier, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
                }
                rmdir($dossier);
            }
        }
    }

    /** @param array<string, mixed> $champs */
    private function mecano(array $champs = []): int
    {
        return $this->db->inserer('mecaniciens', $champs + ['public_id' => Ulid::generer(), 'slug' => 'koffi-' . bin2hex(random_bytes(2)), 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Électricité auto', 'annees_experience' => 12,
            'presentation' => 'Batterie et démarreur.', 'email' => 'k' . bin2hex(random_bytes(3)) . '@exemple.ci', 'telephone' => '+22507' . random_int(10000000, 99999999), 'mot_de_passe_hash' => password_hash('Motdepasse1', PASSWORD_DEFAULT),
            'statut_dossier' => 'valide', 'etat' => 'disponible', 'identite_verifiee_le' => '2026-03-12', 'rayon_km' => 10, 'tarif_diagnostic_fcfa' => 5000, 'tarif_deplacement_fcfa' => 0,
            'atelier_quartier_id' => $this->db->valeur('SELECT id FROM quartiers WHERE slug = \'cocody-riviera-3\''), 'atelier_lat' => 5.3652, 'atelier_lon' => -3.9478, 'cree_le' => '2026-02-01 08:47:00']);
    }

    /** @return array{tmp_name: string, size: int, error: int} */
    private function image(int $l = 800, int $h = 600): array
    {
        $fichier = tempnam(sys_get_temp_dir(), 'photo') . '.jpg';
        $img = imagecreatetruecolor($l, $h);
        imagefilledrectangle($img, 0, 0, $l, $h, (int) imagecolorallocate($img, 120, 90, 60));
        imagejpeg($img, $fichier, 90);
        return ['tmp_name' => $fichier, 'size' => (int) filesize($fichier), 'error' => UPLOAD_ERR_OK];
    }

    private function statut(?int $id = null): string
    {
        return (string) $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE id = :i', ['i' => $id ?? $this->id]);
    }

    public function testLaFicheRassembleTout(): void
    {
        $this->db->executer('INSERT INTO mecanicien_specialites (mecanicien_id, specialite_id) SELECT :m, id FROM specialites ORDER BY ordre LIMIT 2', ['m' => $this->id]);
        $f = $this->service->fiche($this->id);
        self::assertSame(['Koffi Yao', 'Électricité auto · 12 ans de métier', '12 mars 2026', 10, 'Riviera 3'], [$f['nom_complet'], $f['metier'], $f['verifie_le'], $f['rayon'], $f['quartier']]);
        self::assertCount(2, $f['specialites']);
        self::assertStringEndsWith('autour de votre atelier à Riviera 3.', $f['zone']);
        self::assertSame([5000, 0], [$f['diagnostic'], $f['deplacement']]);
        self::assertTrue($f['mot_de_passe']);
        self::assertSame('1er février 2026', $f['mdp_le']); // sans changement : depuis la création du compte
        self::assertNull($this->service->fiche(999999));
    }

    public function testChangerDeNomOuDePortraitRenvoieLeDossierEnVerification(): void
    {
        self::assertSame(['ok' => true, 'renvoye' => false], $this->service->modifierIdentite($this->id, 'Koffi', 'Yao', null)); // rien n'a changé
        self::assertSame('valide', $this->statut());
        $r = $this->service->modifierIdentite($this->id, 'Koffi', 'Yao-Kouassi', null, new \DateTimeImmutable('2026-10-21 10:14:00', new \DateTimeZone('UTC')));
        self::assertSame(['ok' => true, 'renvoye' => true], $r);
        $m = $this->db->un('SELECT statut_dossier, etat, nom, identite_verifiee_le, dossier_envoye_le FROM mecaniciens WHERE id = :i', ['i' => $this->id]);
        self::assertSame(['en_attente', 'indisponible', 'Yao-Kouassi', null, '2026-10-21 10:14:00'], array_values($m)); // RG-DO7, RG-DO8, RG-D6
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM historique_dossier WHERE mecanicien_id = :m AND de_statut = \'valide\' AND vers_statut = \'en_attente\'', ['m' => $this->id]));
        self::assertSame('indisponible', $this->db->valeur('SELECT etat FROM historique_disponibilite WHERE mecanicien_id = :m AND fin IS NULL', ['m' => $this->id]));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM notifications WHERE admin_id IS NOT NULL AND type = \'nouveau_dossier\''));
    }

    public function testUnNouveauPortraitRenvoieAussiLeDossierEtGardeLAncienneCopie(): void
    {
        $r = $this->service->modifierIdentite($this->id, 'Koffi', 'Yao', $this->image());
        self::assertTrue($r['ok'] && $r['renvoye']);
        self::assertSame('en_attente', $this->statut());
        self::assertSame('envoyee', $this->db->valeur('SELECT statut FROM pieces_dossier WHERE mecanicien_id = :m AND type_piece = \'portrait\' AND statut <> \'remplacee\'', ['m' => $this->id]));
    }

    public function testUnPortraitIllisibleOuUnNomVideSontRefuses(): void
    {
        $r = $this->service->modifierIdentite($this->id, '', 'Yao', null);
        self::assertSame(['prenom'], array_keys($r['erreurs']));
        $illisible = ['tmp_name' => __FILE__, 'size' => 100, 'error' => UPLOAD_ERR_OK];
        $r = $this->service->modifierIdentite($this->id, 'Koffi', 'Yao', $illisible);
        self::assertFalse($r['ok']);
        self::assertArrayHasKey('portrait', $r['erreurs']);
        self::assertSame('valide', $this->statut());
    }

    public function testLesContactsSontValidesEtUniques(): void
    {
        $autre = $this->mecano(['telephone' => '+2250712345678']);
        self::assertSame(['telephone'], array_keys($this->service->modifierContacts($this->id, '07 12 34 56 78', '')['erreurs'])); // déjà pris par un autre compte
        self::assertSame(['telephone'], array_keys($this->service->modifierContacts($this->id, '', '')['erreurs']));
        self::assertSame(['whatsapp'], array_keys($this->service->modifierContacts($this->id, '07 48 22 16 90', '12')['erreurs']));
        self::assertTrue($this->service->modifierContacts($this->id, '07 48 22 16 90', '05 44 21 08 17')['ok']);
        self::assertSame(['+2250748221690', '+2250544210817'], array_values($this->db->un('SELECT telephone, whatsapp FROM mecaniciens WHERE id = :i', ['i' => $this->id])));
        self::assertTrue($this->service->modifierContacts($this->id, '07 48 22 16 90', '07 48 22 16 90')['ok']); // le même numéro : pas de WhatsApp à part
        self::assertNull($this->db->valeur('SELECT whatsapp FROM mecaniciens WHERE id = :i', ['i' => $this->id]));
        self::assertGreaterThan(0, $autre);
    }

    public function testLeMotDePasseSeChangeAvecLActuelEtRespecteLesRegles(): void
    {
        self::assertSame(['actuel'], array_keys($this->service->changerMotDePasse($this->id, 'faux', 'Nouveau123')['erreurs']));
        self::assertSame(['nouveau'], array_keys($this->service->changerMotDePasse($this->id, 'Motdepasse1', 'court')['erreurs']));
        self::assertSame(['nouveau'], array_keys($this->service->changerMotDePasse($this->id, 'Motdepasse1', 'Motdepasse1')['erreurs']));
        $r = $this->service->changerMotDePasse($this->id, 'Motdepasse1', 'Nouveau123', new \DateTimeImmutable('2026-10-02 09:00:00', new \DateTimeZone('UTC')));
        self::assertTrue($r['ok']);
        $m = $this->db->un('SELECT mot_de_passe_hash, mot_de_passe_modifie_le FROM mecaniciens WHERE id = :i', ['i' => $this->id]);
        self::assertTrue(password_verify('Nouveau123', $m['mot_de_passe_hash'])); // RG-C4 : jamais en clair
        self::assertSame('2 octobre 2026', $this->service->fiche($this->id)['mdp_le']);
    }

    public function testTroisPhotosDAtelierAuPlusEtOnPeutEnRetirer(): void
    {
        $refs = [];
        for ($i = 0; $i < 3; $i++) {
            $r = $this->service->ajouterPhotoAtelier($this->id, $this->image());
            self::assertTrue($r['ok']);
            $refs[] = $r['reference'];
        }
        self::assertFalse($this->service->ajouterPhotoAtelier($this->id, $this->image())['ok']);
        self::assertCount(3, $this->service->fiche($this->id)['atelier']);
        $dossier = (string) $this->db->valeur('SELECT dossier FROM photos WHERE mecanicien_id = :m AND dossier LIKE :r', ['m' => $this->id, 'r' => '%' . $refs[0]]);
        self::assertFileExists($this->racine . '/' . $dossier . '-360.webp');
        self::assertTrue($this->service->retirerPhotoAtelier($this->id, $refs[0]));
        self::assertFileDoesNotExist($this->racine . '/' . $dossier . '-360.webp');
        self::assertFalse($this->service->retirerPhotoAtelier($this->id, $refs[0])); // déjà retirée
        self::assertFalse($this->service->retirerPhotoAtelier($this->mecano(), $refs[1])); // pas la photo d'un autre
        self::assertCount(2, $this->service->fiche($this->id)['atelier']);
        self::assertTrue($this->service->ajouterPhotoAtelier($this->id, $this->image())['ok']);
    }

    public function testUnFichierTropLourdOuNonImageEstRefuseCommePhotoDAtelier(): void
    {
        self::assertFalse($this->service->ajouterPhotoAtelier($this->id, ['tmp_name' => __FILE__, 'size' => 100, 'error' => UPLOAD_ERR_OK])['ok']);
        self::assertFalse($this->service->ajouterPhotoAtelier($this->id, ['tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE])['ok']);
        $gros = $this->image();
        $gros['size'] = ProfilMecanoService::TAILLE_MAX_O + 1;
        self::assertFalse($this->service->ajouterPhotoAtelier($this->id, $gros)['ok']);
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM photos'));
    }

    public function testSupprimerSonCompteDemandeSonNomEtAnonymise(): void
    {
        $this->db->inserer('abonnements_push', ['mecanicien_id' => $this->id, 'endpoint' => 'https://push.test/x', 'endpoint_hash' => hash('sha256', 'x'), 'cle_p256dh' => 'a', 'cle_auth' => 'b']);
        $this->service->ajouterPhotoAtelier($this->id, $this->image());
        self::assertFalse($this->service->supprimer($this->id, '')['ok']);
        self::assertFalse($this->service->supprimer($this->id, 'Koffi')['ok']);
        self::assertNull($this->db->valeur('SELECT supprime_le FROM mecaniciens WHERE id = :i', ['i' => $this->id]));
        self::assertTrue($this->service->supprimer($this->id, '  koffi   YAO ')['ok']); // RG-C6 : casse et espaces sans importance
        $m = $this->db->un('SELECT prenom, nom, telephone, email, slug, supprime_le, etat FROM mecaniciens WHERE id = :i', ['i' => $this->id]);
        self::assertSame(['Compte', 'supprimé', null, null], [$m['prenom'], $m['nom'], $m['email'], $m['slug']]);
        self::assertNotNull($m['supprime_le']);
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM abonnements_push WHERE mecanicien_id = :m', ['m' => $this->id]));
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM photos WHERE mecanicien_id = :m AND active = 1', ['m' => $this->id]));
        self::assertNull($this->service->fiche($this->id));
        self::assertFalse($this->service->supprimer($this->id, 'Compte supprimé')['ok']); // déjà supprimé
    }
}
