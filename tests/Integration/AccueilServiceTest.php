<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Ulid;
use App\Services\AccueilService;
use PHPUnit\Framework\TestCase;

/**
 * Compte réel des mécanos par commune (RG-R3). Tout se passe dans une transaction annulée à la fin :
 * la base (démo ou CI) n'est pas modifiée.
 */
final class AccueilServiceTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        try {
            $this->db = Database::instance();
            $this->db->valeur('SELECT 1 FROM communes LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Base indisponible : ' . $e->getMessage());
        }
        $this->db->pdo()->beginTransaction();
        $this->db->executer('DELETE FROM avis'); // avis de démo : RESTRICT sur les mécanos, annulé à la fin
        $this->db->executer('DELETE FROM contacts');
        $this->db->executer('DELETE FROM pieces_dossier'); // les dossiers de démo de l'administration tiennent aux mécanos
        $this->db->executer('DELETE FROM mecaniciens');
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
    }

    private function mecano(string $slug, string $quartier, array $champs = []): void
    {
        $quartierId = $this->db->valeur('SELECT id FROM quartiers WHERE slug = :s', ['s' => $quartier])
            ?? $this->db->inserer('quartiers', [
                'commune_id' => (int) $this->db->valeur('SELECT id FROM communes WHERE slug = \'cocody\''),
                'nom' => 'Test ' . $quartier, 'slug' => $quartier, 'lat' => 5.36, 'lon' => -3.96,
            ]);
        $this->db->inserer('mecaniciens', $champs + [
            'public_id' => Ulid::generer(), 'slug' => $slug, 'prenom' => 'Test', 'nom' => $slug, 'metier' => 'Test',
            'telephone' => '+22507' . random_int(10000000, 99999999), 'mot_de_passe_hash' => 'x',
            'statut_dossier' => 'valide', 'etat' => 'disponible', 'dispo_quartier_id' => (int) $quartierId,
            'dispo_lat' => 5.36, 'dispo_lon' => -3.96, 'position_maj_le' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function testCompteSeulementLesMecanosEligibles(): void
    {
        $this->mecano('eligible-1', 'cocody-test');
        $this->mecano('eligible-2', 'cocody-test');
        $this->mecano('indisponible', 'cocody-test', ['etat' => 'indisponible']);
        $this->mecano('en-intervention', 'cocody-test', ['etat' => 'en_intervention']);
        $this->mecano('en-attente', 'cocody-test', ['statut_dossier' => 'en_attente']);
        $this->mecano('supprime', 'cocody-test', ['supprime_le' => gmdate('Y-m-d H:i:s')]);
        $this->mecano('position-ancienne', 'cocody-test', ['position_maj_le' => gmdate('Y-m-d H:i:s', time() - 13 * 3600)]);

        $communes = array_column(AccueilService::depuisBase()->communes(), null, 'slug');

        self::assertSame(2, $communes['cocody']['nombre']);
        self::assertSame('disponibles', $communes['cocody']['etat']);
        self::assertSame('aucun', $communes['yopougon']['etat']);
        self::assertSame('cocody', array_key_first($communes));
        self::assertCount(13, $communes);
    }

    public function testLesPannesOntUneIconeDuSprite(): void
    {
        $sprite = (string) file_get_contents(RACINE . '/public/assets/icones/sprite.svg');
        $pannes = AccueilService::depuisBase()->pannes();
        self::assertNotEmpty($pannes);
        foreach ($pannes as $p) {
            self::assertStringContainsString('id="i-' . $p['icone'] . '"', $sprite);
        }
    }
}
