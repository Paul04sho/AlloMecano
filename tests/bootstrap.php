<?php

declare(strict_types=1);

define('RACINE', dirname(__DIR__));
require RACINE . '/vendor/autoload.php';
date_default_timezone_set('UTC');

// Tests d'intégration : même base que le site (.env), ignorés si elle ne répond pas
App\Core\Env::charger(RACINE . '/.env');
