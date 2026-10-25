<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\CompteService;
use App\Services\MailService;
use PHPUnit\Framework\TestCase;

/** A3 · Comptes : liste, tiroir, suspension (RG-D6, RG-R3, RG-DO8), levée, suppression, numéros des clients (RG-C5). Tout est annulé à la fin. */
final class CompteServiceTest extends TestCase
{
    private Database $db;
    private int $admin;
    private \DateTimeImmutable $t0;
    private CompteService $service;
    private string $journalMail;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT date_naissance FROM mecaniciens LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        foreach (['journal_admin', 'notifications', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'photos', 'pieces_dossier', 'mecanicien_specialites', 'mecaniciens', 'clients', 'admins'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->admin = $this->db->inserer('admins', ['prenom' => 'Aïcha', 'nom' => 'Bamba', 'email' => 'a@allomecano.test', 'mot_de_passe_hash' => 'x']);
        $this->t0 = new \DateTimeImmutable('2026-10-23 12:00:00', new \DateTimeZone('UTC'));
        $this->journalMail = sys_get_temp_dir() . '/mail-' . bin2hex(random_bytes(3)) . '.log';
        $this->service = new CompteService($this->db, new MailService($this->journalMail));
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        if (isset($this->journalMail) && is_file($this->journalMail)) {
            unlink($this->journalMail);
        }
    }

    /** @param array<string, mixed> $champs */
    private function mecano(string $prenom, string $nom, string $statut = 'valide', array $champs = []): string
    {
        $public = Ulid::generer();
        $this->db->inserer('mecaniciens', $champs + ['public_id' => $public, 'prenom' => $prenom, 'nom' => $nom, 'metier' => 'Test', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => $statut, 'cree_le' => '2026-02-01 08:47:00']);
        return $public;
    }

    public function testNumerosEtDerniereActivite(): void
    {
        self::assertSame('MEC-0031', CompteService::numero(31));
        $a = fn (string $d): string => CompteService::activite($d, $this->t0);
        self::assertSame("il y a 12\u{00A0}min", $a('2026-10-23 11:48:00'));
        self::assertSame("il y a 1\u{00A0}h", $a('2026-10-23 10:30:00'));
        self::assertSame('hier, 19:40', $a('2026-10-22 19:40:00'));
        self::assertSame('18 octobre', $a('2026-10-18 09:00:00'));
        self::assertSame('1er février 2025', $a('2025-02-01 09:00:00'));
        self::assertSame('à l’instant', $a('2026-10-23 11:59:30'));
    }

    public function testLaListeEstTrieeParDerniereActiviteEtSeRecherche(): void
    {
        $this->mecano('Koffi', 'Yao', 'valide', ['vu_le' => '2026-10-23 11:48:00', 'note_moyenne' => 4.8, 'nb_avis' => 37]);
        $this->mecano('Adama', 'Traoré', 'valide', ['vu_le' => '2026-10-23 11:00:00']);
        $this->mecano('Paul', 'Assi', 'refuse', ['vu_le' => '2026-10-15 09:00:00']);
        $this->mecano('Brouillon', 'Pas-un-compte', 'brouillon');
        $this->mecano('Supprimé', 'Aussi', 'valide', ['supprime_le' => '2026-10-01 00:00:00']);
        $l = $this->service->mecaniciens('', 1, $this->t0);
        self::assertSame(3, $l['total']);
        self::assertSame(['Koffi Yao', 'Adama Traoré', 'Paul Assi'], array_column($l['lignes'], 'nom_complet'));
        self::assertSame(4.8, $l['lignes'][0]['note']);
        self::assertNull($l['lignes'][1]['note']);
        self::assertSame(['mecaniciens' => 3, 'clients' => 0], $this->service->decompte());
        self::assertSame(['Adama Traoré'], array_column($this->service->mecaniciens('traor', 1, $this->t0)['lignes'], 'nom_complet'));
        $id = $this->db->valeur('SELECT id FROM mecaniciens WHERE nom = \'Assi\'');
        self::assertSame(['Paul Assi'], array_column($this->service->mecaniciens(CompteService::numero((int) $id), 1, $this->t0)['lignes'], 'nom_complet'));
        self::assertSame([], $this->service->mecaniciens('100%', 1, $this->t0)['lignes']);
    }

    public function testLesChiffresDuCompte(): void
    {
        $p = $this->mecano('Adama', 'Traoré', 'valide', ['note_moyenne' => 4.6, 'nb_avis' => 21]);
        $m = (int) $this->db->valeur('SELECT id FROM mecaniciens WHERE public_id = :p', ['p' => $p]);
        $client = $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'a')]);
        foreach ([1, 5, 40] as $jours) { // le contact d'il y a 40 jours sort des 30 jours
            $this->db->inserer('contacts', ['public_id' => Ulid::generer(), 'client_id' => $client, 'mecanicien_id' => $m, 'canal' => 'tel', 'statut' => 'termine', 'resume' => 'x',
                'cree_le' => $this->t0->modify("-$jours days")->format('Y-m-d H:i:s')]);
        }
        $this->db->inserer('signalements', ['public_id' => Ulid::generer(), 'mecanicien_id' => $m, 'motif' => 'pas_venu', 'description' => 'Arrivé avec 1 h de retard', 'statut' => 'sans_suite',
            'traite_par_admin_id' => $this->admin, 'cree_le' => '2026-10-19 16:40:00']);
        $f = $this->service->fiche($p, $this->t0);
        self::assertSame(2, $f['appels']);
        self::assertSame(1, $f['signalements']);
        self::assertSame(21, $f['nb_avis']);
        self::assertSame(0, $f['suspensions']);
        self::assertSame('« Arrivé avec 1 h de retard » : classé sans suite par M. Bamba.', $f['historique'][0]['texte']);
        self::assertSame('drapeau', $f['historique'][0]['icone']);
        self::assertNull($this->service->fiche(Ulid::generer()));
    }

    public function testLeMessageDeSuspensionEstCeluiDeFigma(): void
    {
        self::assertSame('Bonjour Adama, votre compte allomécano est suspendu jusqu’à nouvel ordre. Motif : carte d’identité expirée le 15 octobre 2026. Votre fiche revient dès que la suspension est levée. Vos avis sont conservés.',
            CompteService::messageSuspension('Adama', 'Carte d’identité expirée le 15 octobre 2026.', 'indefinie'));
        self::assertStringContainsString('suspendu pendant 24 h.', CompteService::messageSuspension('Adama', 'x', '24h'));
        self::assertStringContainsString('suspendu pendant 7 jours.', CompteService::messageSuspension('Adama', 'x', '7j'));
    }

    public function testSuspendreRetireLaFicheEtLeveeLaRend(): void
    {
        $p = $this->mecano('Adama', 'Traoré', 'valide', ['email' => 'adama@exemple.ci', 'etat' => 'disponible', 'position_maj_le' => $this->t0->format('Y-m-d H:i:s')]);
        self::assertSame(['ok' => false, 'erreur' => 'Indiquez le motif de la suspension (500 caractères au plus) : il est cité tel quel au mécanicien.'], $this->service->suspendre($this->admin, $p, '  ', '7j', '127.0.0.1'));
        self::assertSame(['ok' => false, 'erreur' => 'Choisissez la durée de la suspension.'], $this->service->suspendre($this->admin, $p, 'Motif', 'jamais', '127.0.0.1'));
        self::assertSame(['ok' => true], $this->service->suspendre($this->admin, $p, 'Carte d’identité expirée', '7j', '127.0.0.1', $this->t0));
        $m = $this->db->un('SELECT statut_dossier, etat FROM mecaniciens WHERE public_id = :p', ['p' => $p]);
        self::assertSame(['statut_dossier' => 'suspendu', 'etat' => 'indisponible'], $m); // RG-D6 : indisponible, et plus « validé » : plus de poinçon (RG-DO8), plus dans les résultats (RG-R3)
        $s = $this->db->un('SELECT motif, fin_prevue, admin_id FROM suspensions');
        self::assertSame('2026-10-30 12:00:00', $s['fin_prevue']);
        self::assertSame('Carte d’identité expirée', $s['motif']);
        self::assertStringContainsString('Motif : carte d’identité expirée.', (string) file_get_contents($this->journalMail));
        self::assertSame('suspension', $this->db->valeur('SELECT type FROM notifications'));
        self::assertSame('suspendre_compte', $this->db->valeur('SELECT action FROM journal_admin'));
        self::assertSame('suspension', $this->db->valeur('SELECT raison FROM historique_disponibilite'));
        self::assertSame('Seul un compte dont le dossier est validé peut être suspendu.', $this->service->suspendre($this->admin, $this->mecano('E', 'Attente', 'en_attente'), 'x', '24h', '127.0.0.1')['erreur']);
        self::assertSame('Ce compte est déjà suspendu.', $this->service->suspendre($this->admin, $p, 'x', '24h', '127.0.0.1')['erreur']);
        $f = $this->service->fiche($p, $this->t0);
        self::assertSame(1, $f['suspensions']);
        self::assertStringContainsString('Compte suspendu par A. Bamba', $f['historique'][0]['texte']);
        self::assertSame(['ok' => true], $this->service->lever($this->admin, $p, '127.0.0.1', $this->t0->modify('+1 day')));
        self::assertSame('valide', $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE public_id = :p', ['p' => $p]));
        self::assertNotNull($this->db->valeur('SELECT levee_le FROM suspensions'));
        self::assertSame('Ce compte n’est pas suspendu.', $this->service->lever($this->admin, $p, '127.0.0.1')['erreur']);
    }

    public function testLesSuspensionsDeDureeFinissentToutesSeules(): void
    {
        $court = $this->mecano('Court', 'Un');
        $long = $this->mecano('Sans', 'Fin');
        $this->service->suspendre($this->admin, $court, 'x', '24h', '127.0.0.1', $this->t0);
        $this->service->suspendre($this->admin, $long, 'x', 'indefinie', '127.0.0.1', $this->t0);
        self::assertSame(0, $this->service->leverEchues($this->t0->modify('+23 hours')));
        self::assertSame(1, $this->service->leverEchues($this->t0->modify('+25 hours'))); // seule celle de 24 h
        self::assertSame('valide', $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE public_id = :p', ['p' => $court]));
        self::assertSame('suspendu', $this->db->valeur('SELECT statut_dossier FROM mecaniciens WHERE public_id = :p', ['p' => $long]));
        self::assertSame(0, $this->service->leverEchues($this->t0->modify('+400 days')));
    }

    public function testSupprimerAnonymiseEtEffaceLesPieces(): void
    {
        $p = $this->mecano('Adama', 'Traoré', 'valide', ['email' => 'adama@exemple.ci', 'slug' => 'adama-traore-abobo']);
        $id = (int) $this->db->valeur('SELECT id FROM mecaniciens WHERE public_id = :p', ['p' => $p]);
        $fichier = 'test-' . bin2hex(random_bytes(3)) . '.jpg';
        $chemin = dirname(__DIR__, 2) . '/storage/pieces/' . $fichier;
        file_put_contents($chemin, 'x');
        $this->db->inserer('pieces_dossier', ['mecanicien_id' => $id, 'type_piece' => 'permis', 'fichier' => $fichier, 'mime' => 'image/jpeg', 'taille_octets' => 1, 'empreinte_sha256' => str_repeat('c', 64)]);
        self::assertSame(['ok' => true], $this->service->supprimer($this->admin, $p, '127.0.0.1'));
        $m = $this->db->un('SELECT prenom, nom, email, slug, supprime_le FROM mecaniciens WHERE id = :i', ['i' => $id]);
        self::assertSame(['Compte', 'supprimé', null, null], [$m['prenom'], $m['nom'], $m['email'], $m['slug']]);
        self::assertNotNull($m['supprime_le']);
        self::assertFileDoesNotExist($chemin);
        self::assertNull($this->db->valeur('SELECT fichier FROM pieces_dossier WHERE mecanicien_id = :i', ['i' => $id]));
        self::assertNull($this->service->fiche($p)); // il disparaît de la liste
        self::assertSame(0, $this->service->mecaniciens()['total']);
        self::assertSame('Ce compte est introuvable.', $this->service->supprimer($this->admin, $p, '127.0.0.1')['erreur']);
    }

    public function testLesNumerosDesClientsSontMasquesEtChaqueAffichageEstJournalise(): void
    {
        $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'aminata'), 'prenom' => 'Aminata', 'telephone' => '+2250707123456', 'vu_le' => $this->t0->format('Y-m-d H:i:s')]);
        $this->db->inserer('clients', ['jeton_hash' => hash('sha256', 'anonyme')]); // sans téléphone : absent de la liste
        $l = $this->service->clients('', 1, $this->t0);
        self::assertSame(1, $l['total']);
        self::assertSame("+225\u{00A0}07\u{00A0}••\u{00A0}••\u{00A0}••\u{00A0}56", $l['lignes'][0]['masque']);
        self::assertStringNotContainsString('1234', $l['lignes'][0]['masque']);
        self::assertSame(['Aminata'], array_column($this->service->clients('07 07 12', 1, $this->t0)['lignes'], 'prenom'));
        $ref = $l['lignes'][0]['ref'];
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin'));
        $numero = $this->service->afficherNumero($this->admin, $ref, '127.0.0.1');
        self::assertSame(\App\Core\Format::telephone('+2250707123456'), $numero);
        self::assertSame('afficher_numero', $this->db->valeur('SELECT action FROM journal_admin')); // RG-C5
        self::assertNull($this->service->afficherNumero($this->admin, 'inconnu', '127.0.0.1'));
        self::assertNull($this->service->afficherNumero($this->admin, str_repeat('0', 16), '127.0.0.1'));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM journal_admin'));
    }
}
