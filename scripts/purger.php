<?php
/**
 * Durées de conservation (RG-P1, RG-P2, RG-P4). À lancer chaque heure (tâche planifiée Systalink) :
 *   php scripts/purger.php
 */
declare(strict_types=1);

use App\Core\Env;
use App\Services\PurgeService;

require dirname(__DIR__) . '/vendor/autoload.php';
define('RACINE', dirname(__DIR__));
Env::charger(RACINE . '/.env');
date_default_timezone_set('UTC');

$r = PurgeService::depuisBase()->purger();
printf("Positions effacées : %d · recherches supprimées : %d · zones supprimées : %d · suspensions levées : %d · pièces effacées : %d\n", $r['positions'], $r['recherches'], $r['zones'], $r['suspensions'], $r['pieces']);
