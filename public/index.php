<?php

declare(strict_types=1);

use App\Controllers\Communs\ErreurController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

// Serveur intégré de PHP (composer serve) : laisser passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $fichier = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($fichier)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

Session::demarrer();
$requete = Request::depuisGlobales();
$routeur = new Router();
require RACINE . '/app/routes.php';

try {
    $reponse = $routeur->dispatcher($requete);
} catch (HttpException $e) {
    $reponse = ErreurController::depuisException($requete, $e->statut, $e->getMessage());
} catch (\Throwable $e) {
    error_log((string) $e);
    $reponse = ErreurController::depuisException($requete, 500, App\Core\Env::enProduction() ? '' : $e->getMessage());
}

$reponse instanceof Response && $reponse->envoyer();
