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
    // Fond de carte PMTiles : lu par plages d'octets (Range), que le serveur intégré ne sait pas servir seul.
    // En ligne, Apache le fait nativement.
    if (str_ends_with($fichier, '.pmtiles') && is_file($fichier)) {
        $taille = filesize($fichier);
        [$debut, $fin] = [0, $taille - 1];
        if (preg_match('/bytes=(\d+)-(\d*)/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $m)) {
            $debut = (int) $m[1];
            $fin = $m[2] !== '' ? min((int) $m[2], $taille - 1) : $taille - 1;
            http_response_code(206);
            header("Content-Range: bytes $debut-$fin/$taille");
        }
        header('Content-Type: application/octet-stream');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($fin - $debut + 1));
        $f = fopen($fichier, 'rb');
        fseek($f, $debut);
        echo fread($f, $fin - $debut + 1);
        return true;
    }
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
