<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class PageController
{
    /** C1 · Accueil : version provisoire du socle, remplacée par le chantier A. */
    public function accueil(Request $requete): Response
    {
        return Response::html(View::rendre('communs/accueil-provisoire', [
            'titre' => null,
            'css' => ['css/pages/systeme.css'],
        ], 'layouts/client'));
    }
}
