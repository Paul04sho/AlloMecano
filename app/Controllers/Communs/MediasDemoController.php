<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Photos de démonstration servies depuis database/seeds/medias quand public/medias/demo n'a pas (encore) été garni
 * par `php scripts/installer-base.php --medias-demo`. Sans ce relais, les portraits des mécanos de démo n'apparaissent
 * pas sur les résultats tant que la copie n'a pas été faite. Absent en production, comme /systeme.
 * Apache et le serveur intégré servent d'abord le vrai fichier s'il existe : ce contrôleur n'est atteint que s'il manque.
 */
final class MediasDemoController
{
    /** GET /medias/demo/{fichier} */
    public function servir(Request $requete): Response
    {
        if (Env::enProduction()) {
            throw HttpException::introuvable();
        }
        $fichier = (string) $requete->param('fichier');
        // Liste blanche stricte : « koffi-160.webp », jamais de chemin
        if (!preg_match('/^[a-z]+-\d{2,4}\.webp$/', $fichier)) {
            throw HttpException::introuvable();
        }
        $chemin = dirname(__DIR__, 3) . '/database/seeds/medias/' . $fichier;
        if (!is_file($chemin)) {
            throw HttpException::introuvable();
        }
        return (new Response((string) file_get_contents($chemin), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=3600',
        ]));
    }
}
