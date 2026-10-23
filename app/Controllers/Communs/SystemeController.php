<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** Page « Système » : tous les composants, pour vérifier le rendu face au Figma (page Système 40:96). */
final class SystemeController
{
    public function index(Request $requete): Response
    {
        if (Env::enProduction()) {
            throw HttpException::introuvable();
        }
        return Response::html(View::rendre('communs/systeme', [
            'titre' => 'Système',
            'css' => ['css/composants/carte.css', 'css/pages/fiche.css', 'css/pages/contact.css', 'css/pages/appel.css', 'css/pages/avis.css', 'css/pages/signalement.css', 'css/pages/systeme.css'],
            'js' => ['js/pages/systeme.js'],
        ], 'layouts/client'));
    }
}
