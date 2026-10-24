<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** M0 · Page de présentation du parcours mécanicien. */
final class DevenirController
{
    public function index(Request $requete): Response
    {
        return Response::html(View::rendre('pro/devenir', [
            'titre' => 'Devenir mécano vérifié',
            'description' => 'Inscription gratuite, dossier vérifié sous 48 heures et maîtrise de vos disponibilités. Rejoignez les mécaniciens allomécano à Abidjan.',
            'classeCorps' => 'page-pro-devenir',
            'css' => ['css/pages/pro-devenir.css'],
            'js' => ['js/pages/pro-devenir.js'],
        ], 'layouts/client'));
    }
}
