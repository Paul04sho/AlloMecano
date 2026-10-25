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

    /** @return array{positions: int, recherches: int, zones: int, signalisation: int, suspensions: int, pieces: int} lignes touchées */
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
        // RG-P3 : messages de l'appel intégré effacés 1 h après la fin de l'appel
        $signalisation = (new AppelService($this->db))->purgerSignalisation();
        // Fin des suspensions de 24 h et de 7 jours (A3)
        $suspensions = CompteService::depuisBase()->leverEchues();
        // RG-P5 : pièces d'un dossier refusé effacées au bout de 6 mois
        $pieces = $this->purgerPiecesRefusees();
        return ['positions' => $positions, 'recherches' => $recherches, 'zones' => $zones, 'signalisation' => $signalisation, 'suspensions' => $suspensions, 'pieces' => $pieces];
    }

    /** RG-P5 : les pièces d'identité d'un dossier refusé depuis plus de 6 mois sont effacées (fichier et ligne vidée). @return int pièces effacées */
    public function purgerPiecesRefusees(): int
    {
        $racine = realpath(dirname(__DIR__, 2) . '/storage/pieces');
        $n = 0;
        foreach ($this->db->tous(
            'SELECT p.id, p.fichier FROM pieces_dossier p JOIN mecaniciens m ON m.id = p.mecanicien_id
              WHERE m.statut_dossier = \'refuse\' AND p.fichier IS NOT NULL AND p.fichier_supprime_le IS NULL
                AND (SELECT MAX(h.cree_le) FROM historique_dossier h WHERE h.mecanicien_id = m.id AND h.vers_statut = \'refuse\') < UTC_TIMESTAMP() - INTERVAL 6 MONTH'
        ) as $p) {
            $chemin = $racine === false ? false : realpath($racine . DIRECTORY_SEPARATOR . $p['fichier']);
            if ($chemin !== false && str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR)) {
                @unlink($chemin);
            }
            $this->db->executer('UPDATE pieces_dossier SET fichier = NULL, fichier_supprime_le = UTC_TIMESTAMP() WHERE id = :i', ['i' => $p['id']]);
            $n++;
        }
        return $n;
    }
}
