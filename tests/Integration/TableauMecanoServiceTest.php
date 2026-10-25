<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\FicheService;
use App\Services\InscriptionService;
use App\Services\MailService;
use App\Services\PieceDossierService;
use App\Services\TableauMecanoService;
use PHPUnit\Framework\TestCase;

/** Ce que le mécano voit à l'ouverture de son espace (M2, M2a, M2b) : bornes du jour, demandes, vues de fiche, verdicts, suspension, reprise d'une pièce. Tout est annulé à la fin. */
final class TableauMecanoServiceTest extends TestCase
{
    private Database $db;
    private TableauMecanoService $service;
    private string $journalMail;
    private string $racinePieces;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT jour FROM vues_fiche LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['vues_fiche', 'journal_admin', 'notifications', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'recherches', 'photos', 'pieces_dossier', 'mecanicien_vehicules', 'mecanicien_specialites', 'mecaniciens', 'clients', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->service = new TableauMecanoService($this->db);
        $this->journalMail = sys_get_temp_dir() . '/mail-' . bin2hex(random_bytes(3)) . '.log';
        $this->racinePieces = sys_get_temp_dir() . '/pieces-' . bin2hex(random_bytes(3));
        mkdir($this->racinePieces);
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

    /** @param array<string, mixed> $champs */
    private function mecano(array $champs = []): int
    {
        return $this->db->inserer('mecaniciens', $champs + ['public_id' => Ulid::generer(), 'slug' => 'koffi-' . bin2hex(random_bytes(2)), 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Test', 'email' => 'koffi-' . bin2hex(random_bytes(3)) . '@exemple.ci',
            'telephone' => '+22505' . random_int(10000000, 99999999), 'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'cree_le' => '2026-02-01 08:47:00']);
    }

    private function client(?string $prenom = null, ?string $telephone = null): int
    {
        return $this->db->inserer('clients', ['jeton_hash' => hash('sha256', random_bytes(8)), 'prenom' => $prenom, 'telephone' => $telephone]);
    }

    /** @param array<string, mixed> $champs */
    private function contact(int $mecano, int $client, string $quand, array $champs = []): int
    {
        return $this->db->inserer('contacts', $champs + ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $mecano, 'canal' => 'tel', 'statut' => 'initie', 'resume' => 'Voiture · Batterie', 'cree_le' => $quand]);
    }

    private function maintenant(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 14:30:00', new \DateTimeZone('UTC'));
    }

    public function testLesBornesDuJourCompteLesAppelsEtLesVuesDAujourdhuiSeulement(): void
    {
        $id = $this->mecano(['note_moyenne' => 4.8, 'nb_avis' => 37]);
        $c = $this->client();
        $this->contact($id, $c, '2026-10-21 08:05:00');
        $this->contact($id, $c, '2026-10-21 10:12:00');
        $this->contact($id, $c, '2026-10-20 23:59:00'); // hier
        $this->db->inserer('vues_fiche', ['mecanicien_id' => $id, 'client_id' => $c, 'jour' => '2026-10-21']);
        $this->db->inserer('vues_fiche', ['mecanicien_id' => $id, 'client_id' => $c, 'jour' => '2026-10-20']);
        $j = $this->service->jour($id, $this->maintenant());
        self::assertSame(['mercredi 21 octobre', 2, 1, 4.8, 37], [$j['jour'], $j['appels'], $j['vues'], $j['note'], $j['nb_avis']]);
    }

    public function testUneFicheVueDeuxFoisLeMemeJourParLeMemeClientCompteUneVue(): void
    {
        $id = $this->mecano();
        $public = (string) $this->db->valeur('SELECT public_id FROM mecaniciens WHERE id = :i', ['i' => $id]);
        $fiche = new FicheService($this->db);
        $c = $this->client();
        $fiche->compterVue($public, $c);
        $fiche->compterVue($public, $c);
        $fiche->compterVue($public, $this->client());
        self::assertSame(2, (int) $this->db->valeur('SELECT COUNT(*) FROM vues_fiche WHERE mecanicien_id = :m', ['m' => $id]));
    }

    public function testLesDemandesMontrentLeClientLaPanneLaDistanceEtLeNumeroSeulementSiIlLALaisse(): void
    {
        $id = $this->mecano(['atelier_lat' => 5.3600, 'atelier_lon' => -3.9900]);
        $this->contact($id, $this->client('Aminata', '+2250700000001'), '2026-10-21 10:12:00', ['lat' => 5.3600, 'lon' => -3.9770, 'resume' => 'Voiture · Batterie']);
        $this->contact($id, $this->client(), '2026-10-21 09:47:00', ['resume' => 'Moto · Pneu crevé']);
        $d = $this->service->demandes($id, $this->maintenant());
        self::assertSame(['aujourdhui', 2, '2 clients vous ont appelé'], [$d['quand'], $d['total'], $d['titre']]);
        [$premiere, $seconde] = $d['lignes'];
        self::assertSame(['10:12', 'Aminata', 'Batterie · Voiture', '+2250700000001'], [$premiere['heure'], $premiere['client'], $premiere['resume'], $premiere['telephone']]);
        self::assertNotNull($premiere['distance']);
        self::assertSame(['Un client', null, null], [$seconde['client'], $seconde['telephone'], $seconde['distance']]); // RG-C7 : jamais un numéro non donné
    }

    public function testSansDemandeAujourdhuiOnMontreCellesDHier(): void
    {
        $id = $this->mecano();
        foreach (['2026-10-20 08:00:00', '2026-10-20 09:00:00', '2026-10-20 10:00:00'] as $q) {
            $this->contact($id, $this->client(), $q);
        }
        $d = $this->service->demandes($id, $this->maintenant());
        self::assertSame(['hier', 3, 'Trois clients vous ont appelé hier'], [$d['quand'], $d['total'], $d['titre']]);
        self::assertSame('aucune', $this->service->demandes($this->mecano(), $this->maintenant())['quand']);
    }

    public function testLaPastilleCompteLesAppelsDirectsDes24HeuresSansReponse(): void
    {
        $id = $this->mecano();
        $c = $this->client();
        $this->contact($id, $c, '2026-10-21 10:00:00', ['canal' => 'tel']);
        $this->contact($id, $c, '2026-10-21 11:00:00', ['canal' => 'whatsapp']);
        $this->contact($id, $c, '2026-10-21 12:00:00', ['canal' => 'app']); // l'appel intégré se décroche, il ne se confirme pas
        $this->contact($id, $c, '2026-10-21 13:00:00', ['canal' => 'tel', 'issue_mecano' => 'j_y_vais']);
        $this->contact($id, $c, '2026-10-19 13:00:00', ['canal' => 'tel']); // trop ancien
        self::assertSame(2, $this->service->aConfirmer($id, $this->maintenant())); // RG-I7
    }

    public function testLeDernierAvisEstLePlusRecentDesPublies(): void
    {
        $id = $this->mecano();
        $c = $this->client();
        $contact = $this->contact($id, $c, '2026-10-18 10:00:00');
        $autre = $this->contact($id, $c, '2026-10-19 10:00:00');
        foreach ([[$contact, 4, 'Vieux avis', '2026-10-18 12:00:00'], [$autre, 5, 'Arrivé en 20 minutes.', '2026-10-19 12:00:00']] as [$k, $note, $texte, $quand]) {
            $this->db->inserer('avis', ['contact_id' => $k, 'mecanicien_id' => $id, 'client_id' => $c, 'note' => $note, 'commentaire' => $texte, 'prenom_affiche' => 'Aminata', 'panne_libelle' => 'Batterie', 'statut' => 'publie', 'cree_le' => $quand]);
        }
        $a = $this->service->dernierAvis($id, $this->maintenant());
        self::assertSame([5, 'Arrivé en 20 minutes.', 'Aminata', 'Batterie'], [$a['note'], $a['commentaire'], $a['prenom'], $a['panne']]);
        self::assertNull($this->service->dernierAvis($this->mecano()));
    }

    public function testLaLigneDePositionDitOuOnEstVuOuLaDernierePosition(): void
    {
        $maj = new \DateTimeImmutable('2026-10-20 18:40:00', new \DateTimeZone('UTC'));
        $dispo = ['etat' => 'disponible', 'quartier_dispo' => 'Riviera 3', 'position_maj_le' => new \DateTimeImmutable('2026-10-21 07:32:00', new \DateTimeZone('UTC'))];
        self::assertSame("Position\u{00A0}: Riviera 3 · mise à jour à 07:32", TableauMecanoService::lignePosition($dispo, $this->maintenant()));
        $hors = ['etat' => 'indisponible', 'quartier_dispo' => 'Riviera 3', 'position_maj_le' => $maj];
        self::assertSame("Dernière position\u{00A0}: Riviera 3 · hier à 18:40. Elle sera mise à jour quand vous vous rendrez disponible.", TableauMecanoService::lignePosition($hors, $this->maintenant()));
        self::assertStringStartsWith('Aucune position', TableauMecanoService::lignePosition(['etat' => 'indisponible', 'quartier_dispo' => null, 'position_maj_le' => null], $this->maintenant()));
        self::assertSame("10\u{00A0}km · Riviera 3", TableauMecanoService::zone(['rayon' => 10, 'quartier_dispo' => 'Riviera 3', 'quartier' => 'Marcory']));
    }

    public function testLesVerdictsDistinguentLesPiecesConformesEtCellesAReprendre(): void
    {
        $id = $this->mecano(['statut_dossier' => 'a_reprendre']);
        foreach (['cni_recto' => 'validee', 'cni_verso' => 'validee', 'permis' => 'validee', 'diplome' => 'validee', 'portrait' => 'a_reprendre'] as $type => $statut) {
            $this->db->inserer('pieces_dossier', ['mecanicien_id' => $id, 'type_piece' => $type, 'fichier' => 'x/' . $type . '.jpg', 'mime' => 'image/jpeg', 'taille_octets' => 10, 'empreinte_sha256' => str_repeat('a', 64),
                'largeur' => 10, 'hauteur' => 10, 'statut' => $statut, 'motif' => $statut === 'a_reprendre' ? 'Visage flou.' : null, 'examinee_le' => '2026-10-21 09:00:00', 'cree_le' => '2026-10-20 10:14:00']);
        }
        $v = $this->service->verdicts($id);
        self::assertSame(['portrait'], $v['a_reprendre']);
        self::assertSame(['conforme', 'conforme', 'conforme', 'conforme', 'a_reprendre'], array_column($v['pieces'], 'etat'));
        self::assertSame('Visage flou.', $v['pieces'][4]['motif']);
        self::assertSame('2026-10-21', $v['verifiees_le']->format('Y-m-d'));
    }

    public function testLaSuspensionEnCoursDitDepuisQuandPourCombienDeTempsEtPourquoi(): void
    {
        $id = $this->mecano(['statut_dossier' => 'suspendu']);
        $admin = $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->db->inserer('suspensions', ['mecanicien_id' => $id, 'admin_id' => $admin, 'motif' => 'Trois clients ont signalé que vous n’êtes pas venu.', 'debut' => '2026-10-02 09:00:00', 'fin_prevue' => '2026-10-09 09:00:00']);
        $s = $this->service->suspension($id);
        self::assertSame(['2 octobre', '9 octobre', 'Trois clients ont signalé que vous n’êtes pas venu.'], [TableauMecanoService::jourMois($s['depuis']), TableauMecanoService::jourMois($s['fin']), $s['motif']]);
        self::assertSame('7 jours', TableauMecanoService::dureeSuspension($s['depuis'], $s['fin']));
        self::assertSame("24\u{00A0}h", TableauMecanoService::dureeSuspension(new \DateTimeImmutable('2026-10-02 09:00:00'), new \DateTimeImmutable('2026-10-03 09:00:00')));
        self::assertSame('jusqu’à nouvel ordre', TableauMecanoService::dureeSuspension($s['depuis'], null));
        self::assertNull($this->service->suspension($this->mecano()));
    }

    // ---------- Reprise d'une pièce (M2b) et photo de l'atelier (M2a) ----------

    private function jpeg(): string
    {
        $fichier = tempnam(sys_get_temp_dir(), 'piece') . '.jpg';
        $img = imagecreatetruecolor(800, 600);
        imagefilledrectangle($img, 0, 0, 800, 600, (int) imagecolorallocate($img, 120, 90, 60));
        imagejpeg($img, $fichier, 90);
        return $fichier;
    }

    /** @return array{tmp_name: string, size: int, error: int} */
    private function fichier(): array
    {
        $source = $this->jpeg();
        return ['tmp_name' => $source, 'size' => (int) filesize($source), 'error' => UPLOAD_ERR_OK];
    }

    private function dossierARepartir(): int
    {
        $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $id = $this->mecano(['statut_dossier' => 'a_reprendre']);
        foreach (['cni_recto' => 'validee', 'cni_verso' => 'validee', 'permis' => 'a_reprendre', 'diplome' => 'validee', 'portrait' => 'a_reprendre'] as $type => $statut) {
            $this->db->inserer('pieces_dossier', ['mecanicien_id' => $id, 'type_piece' => $type, 'fichier' => 'x/' . $type . '.jpg', 'mime' => 'image/jpeg', 'taille_octets' => 10, 'empreinte_sha256' => str_repeat('a', 64),
                'largeur' => 10, 'hauteur' => 10, 'statut' => $statut, 'motif' => $statut === 'a_reprendre' ? 'À refaire.' : null, 'cree_le' => '2026-10-20 10:14:00']);
        }
        return $id;
    }

    private function inscription(): InscriptionService
    {
        return new InscriptionService($this->db, new MailService($this->journalMail), new PieceDossierService($this->db, $this->racinePieces));
    }

    public function testLeDossierRepartChezLEquipeQuandLaDerniereAReprendreEstRenvoyee(): void
    {
        $id = $this->dossierARepartir();
        $service = $this->inscription();
        $r = $service->deposerPiece($id, 'permis', $this->fichier());
        self::assertTrue($r['ok']);
        self::assertFalse($r['renvoye']); // il reste la photo
        self::assertSame('a_reprendre', $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE id = :i', ['i' => $id]));
        $r = $service->deposerPiece($id, 'portrait', $this->fichier());
        self::assertTrue($r['renvoye']); // RG-DO4
        self::assertSame('en_attente', $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE id = :i', ['i' => $id]));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM historique_dossier WHERE mecanicien_id = :m AND de_statut = \'a_reprendre\' AND vers_statut = \'en_attente\'', ['m' => $id]));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM notifications WHERE admin_id IS NOT NULL AND type = \'nouveau_dossier\''));
    }

    public function testUnePieceDejaGardeeNeSeRenvoiePas(): void
    {
        $id = $this->dossierARepartir();
        $r = $this->inscription()->deposerPiece($id, 'cni_recto', $this->fichier());
        self::assertFalse($r['ok']);
        self::assertSame('validee', $this->db->valeur('SELECT statut FROM pieces_dossier WHERE mecanicien_id = :m AND type_piece = \'cni_recto\'', ['m' => $id]));
    }

    public function testSeulementLaPhotoDeLAtelierSAjouteApresLEnvoi(): void
    {
        $service = $this->inscription();
        foreach (['en_attente', 'valide'] as $statut) {
            $id = $this->mecano(['statut_dossier' => $statut]);
            self::assertFalse($service->deposerPiece($id, 'permis', $this->fichier())['ok'], $statut);
            self::assertTrue($service->deposerPiece($id, 'atelier', $this->fichier())['ok'], $statut);
            self::assertSame($statut, $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE id = :i', ['i' => $id])); // l'atelier ne rouvre pas le dossier
        }
        foreach (['refuse', 'suspendu'] as $statut) {
            self::assertFalse($service->deposerPiece($this->mecano(['statut_dossier' => $statut]), 'atelier', $this->fichier())['ok'], $statut);
        }
    }
}
