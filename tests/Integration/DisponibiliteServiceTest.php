<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\DisponibiliteService;
use App\Services\MailService;
use PHPUnit\Framework\TestCase;

/** L'interrupteur du mécano (M2) : se rendre disponible (RG-D1), position (RG-D2), session de 12 h (RG-D3), suspension (RG-D6), historique (RG-D7). Tout est annulé à la fin. */
final class DisponibiliteServiceTest extends TestCase
{
    private const RIVIERA = ['lat' => 5.3600, 'lon' => -3.9900, 'precision' => 25];

    private Database $db;
    private DisponibiliteService $service;
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
        foreach (['journal_admin', 'notifications', 'suspensions', 'historique_disponibilite', 'historique_dossier', 'signalements', 'avis', 'contacts', 'photos', 'pieces_dossier', 'mecanicien_vehicules', 'mecanicien_specialites', 'mecaniciens'] as $t) {
            $this->db->executer("DELETE FROM $t");
        }
        $this->journalMail = sys_get_temp_dir() . '/mail-' . bin2hex(random_bytes(3)) . '.log';
        $this->service = new DisponibiliteService($this->db, new MailService($this->journalMail));
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
    private function mecano(array $champs = []): int
    {
        return $this->db->inserer('mecaniciens', $champs + ['public_id' => Ulid::generer(), 'prenom' => 'Koffi', 'nom' => 'Yao', 'metier' => 'Test', 'email' => 'koffi-' . bin2hex(random_bytes(3)) . '@exemple.ci', 'telephone' => '+22505' . random_int(10000000, 99999999),
            'mot_de_passe_hash' => 'x', 'statut_dossier' => 'valide', 'cree_le' => '2026-02-01 08:47:00']);
    }

    private function maintenant(string $h = '2026-10-21 07:32:00'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($h, new \DateTimeZone('UTC'));
    }

    public function testSeRendreDisponibleDemarreLaSessionEtOuvreUnePeriode(): void
    {
        $id = $this->mecano();
        $r = $this->service->rendreDisponible($id, self::RIVIERA, $this->maintenant());
        self::assertTrue($r['ok']);
        self::assertSame(['disponible', '07:32'], [$r['etat'], $r['depuis']]);
        $m = $this->db->un('SELECT etat, dispo_debut, dispo_lat, position_maj_le FROM mecaniciens WHERE id = :i', ['i' => $id]);
        self::assertSame(['disponible', '2026-10-21 07:32:00'], [$m['etat'], $m['dispo_debut']]);
        self::assertSame('2026-10-21 07:32:00', $m['position_maj_le']);
        self::assertEqualsWithDelta(5.36, (float) $m['dispo_lat'], 0.0001);
        $h = $this->db->tous('SELECT etat, raison, fin FROM historique_disponibilite WHERE mecanicien_id = :m', ['m' => $id]);
        self::assertSame([['etat' => 'disponible', 'raison' => 'manuel', 'fin' => null]], $h); // RG-D7
    }

    public function testUnDossierNonValideOuSuspenduNePeutPasSeRendreDisponible(): void
    {
        foreach (['en_attente', 'a_reprendre', 'refuse', 'suspendu'] as $statut) {
            $id = $this->mecano(['statut_dossier' => $statut]);
            $r = $this->service->rendreDisponible($id, self::RIVIERA);
            self::assertFalse($r['ok'], $statut);
            self::assertSame('indisponible', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i', ['i' => $id]));
        }
        self::assertSame(0, (int) $this->db->valeur('SELECT COUNT(*) FROM historique_disponibilite')); // RG-D1, RG-D6
    }

    public function testUnePositionHorsAbidjanOuIllisibleEstRefusee(): void
    {
        $id = $this->mecano();
        self::assertFalse($this->service->rendreDisponible($id, ['lat' => 48.85, 'lon' => 2.35])['ok']);
        self::assertFalse($this->service->rendreDisponible($id, ['lat' => 'x', 'lon' => ''])['ok']);
        self::assertFalse($this->service->rendreDisponible($id, [])['ok']);
        self::assertSame('indisponible', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i', ['i' => $id]));
    }

    public function testPasserIndisponibleFermeLaPeriodeEtGardeLaDernierePosition(): void
    {
        $id = $this->mecano();
        $this->service->rendreDisponible($id, self::RIVIERA, $this->maintenant());
        $r = $this->service->rendreIndisponible($id, 'manuel', $this->maintenant('2026-10-21 10:00:00'));
        self::assertSame('indisponible', $r['etat']);
        $h = $this->db->tous('SELECT etat, fin FROM historique_disponibilite WHERE mecanicien_id = :m ORDER BY id', ['m' => $id]);
        self::assertSame([['etat' => 'disponible', 'fin' => '2026-10-21 10:00:00'], ['etat' => 'indisponible', 'fin' => null]], $h);
        self::assertEqualsWithDelta(5.36, (float) $this->db->valeur('SELECT dispo_lat FROM mecaniciens WHERE id = :i', ['i' => $id]), 0.0001);
    }

    public function testLaPositionSeMetAJourSeulementEnLigne(): void
    {
        $id = $this->mecano();
        self::assertFalse($this->service->majPosition($id, self::RIVIERA)['ok']); // RG-D2 : disponible ou en intervention seulement
        $this->service->rendreDisponible($id, self::RIVIERA, $this->maintenant());
        $r = $this->service->majPosition($id, ['lat' => 5.3300, 'lon' => -4.0100, 'precision' => 40], $this->maintenant('2026-10-21 09:15:00'));
        self::assertTrue($r['ok']);
        self::assertSame('09:15', $r['maj']);
        self::assertSame('07:32', $r['depuis']); // la session continue
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM historique_disponibilite')); // pas de nouvelle période
    }

    public function testApresDouzeHeuresLaSessionSArreteAvecNotificationEtEmail(): void
    {
        $long = $this->mecano();
        $court = $this->mecano();
        $this->service->rendreDisponible($long, self::RIVIERA, $this->maintenant('2026-10-21 07:00:00'));
        $this->service->rendreDisponible($court, self::RIVIERA, $this->maintenant('2026-10-21 15:00:00'));
        self::assertSame(0, $this->service->expirer($this->maintenant('2026-10-21 18:59:00')));
        self::assertSame(1, $this->service->expirer($this->maintenant('2026-10-21 19:00:00'))); // RG-D3 : 12 h pile
        self::assertSame(['indisponible', 'disponible'], [
            $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i', ['i' => $long]),
            $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i', ['i' => $court]),
        ]);
        self::assertSame('auto_12h', $this->db->valeur('SELECT raison FROM historique_disponibilite WHERE mecanicien_id = :m AND fin IS NULL', ['m' => $long]));
        self::assertSame(1, (int) $this->db->valeur('SELECT COUNT(*) FROM notifications WHERE mecanicien_id = :m AND type = \'indispo_auto\'', ['m' => $long]));
        self::assertStringContainsString((string) $this->db->valeur('SELECT email FROM mecaniciens WHERE id = :i', ['i' => $long]), (string) file_get_contents($this->journalMail));
    }

    public function testExpirerPeutSeLimiterAUnMecano(): void
    {
        $a = $this->mecano();
        $b = $this->mecano();
        foreach ([$a, $b] as $id) {
            $this->service->rendreDisponible($id, self::RIVIERA, $this->maintenant('2026-10-20 07:00:00'));
        }
        self::assertSame(1, $this->service->expirer($this->maintenant('2026-10-21 12:00:00'), $a));
        self::assertSame('disponible', $this->db->valeur('SELECT etat FROM mecaniciens WHERE id = :i', ['i' => $b]));
    }

    public function testLEtatAffichePorteLHeureDeDebutEtLeQuartier(): void
    {
        $id = $this->mecano();
        $this->service->rendreDisponible($id, self::RIVIERA, $this->maintenant());
        $e = $this->service->etat($id);
        self::assertSame(['disponible', '07:32'], [$e['etat'], $e['depuis']]);
        self::assertNotNull($e['quartier']);
    }
}
