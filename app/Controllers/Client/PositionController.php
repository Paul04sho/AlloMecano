<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Format;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class PositionController
{
    /** C2a · Où êtes-vous ? */
    public function index(Request $requete): Response
    {
        return Response::html(View::rendre('client/position', [
            'titre' => Format::typo('Où êtes-vous ?'),
            'description' => Format::typo('Utilisez votre position pour trouver les mécaniciens proches de vous, ou choisissez votre commune.'),
            'css' => ['css/pages/position.css'],
            'js' => ['js/pages/position.js'],
            'sansBarre' => true,
            'sansPied' => true,
            'classeCorps' => 'page-position',
        ], 'layouts/client'));
    }
}
