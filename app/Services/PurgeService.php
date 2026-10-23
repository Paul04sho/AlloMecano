<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Durées de conservation (RG-P1, RG-P2, RG-P4), lancées chaque heure par scripts/purger.php.
 */
final class PurgeService
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** @return array{positions: int, recherches: int, zones: int} lignes touchées */
    public function purger(): array
    {
        $heures = Parametres::int('purge.position_h', 24);
        $jours = Parametres::int('purge.recherches_j', 90);
        // RG-P1 : position exacte effacée 24 h après la recherche ; le quartier est gardé
        $positions = $this->db->executer(
            'UPDATE recherches SET lat = NULL, lon = NULL, position_effacee_le = UTC_TIMESTAMP()
              WHERE lat IS NOT NULL AND cree_le < UTC_TIMESTAMP() - INTERVAL :h HOUR',
            ['h' => $heures]
        )->rowCount();
        // RG-P1 : même règle pour la position envoyée au mécano contacté
        $positions += $this->db->executer(
            'UPDATE contacts SET lat = NULL, lon = NULL WHERE lat IS NOT NULL AND cree_le < UTC_TIMESTAMP() - INTERVAL :h HOUR',
            ['h' => $heures]
        )->rowCount();
        // RG-P2 : recherches supprimées au bout de 90 jours (les contacts restent, lien vidé par la clé étrangère)
        $recherches = $this->db->executer(
            'DELETE FROM recherches WHERE cree_le < UTC_TIMESTAMP() - INTERVAL :j DAY',
            ['j' => $jours]
        )->rowCount();
        // RG-P4 : zones sans résultat gardées 90 jours, totalement anonymes
        $zones = $this->db->executer(
            'DELETE FROM zones_sans_resultat WHERE jour < UTC_DATE() - INTERVAL :j DAY',
            ['j' => $jours]
        )->rowCount();
        return ['positions' => $positions, 'recherches' => $recherches, 'zones' => $zones];
    }
}
