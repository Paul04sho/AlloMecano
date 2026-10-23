<?php

declare(strict_types=1);

use App\Core\Env;

define('RACINE', dirname(__DIR__));

if (!is_file(RACINE . '/vendor/autoload.php')) {
    // Dépendances absentes : on le dit clairement plutôt qu'une erreur fatale
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Dépendances manquantes : lancer « composer install » à la racine du projet (" . RACINE . "), puis recharger.\n");
}
require RACINE . '/vendor/autoload.php';

Env::charger(RACINE . '/.env');

date_default_timezone_set('UTC'); // Abidjan = GMT toute l'année
mb_internal_encoding('UTF-8');
ini_set('display_errors', Env::bool('APP_DEBUG') && !Env::enProduction() ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RACINE . '/storage/logs/php.log');
error_reporting(E_ALL);
