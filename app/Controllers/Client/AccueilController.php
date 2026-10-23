<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Core\Session;
use App\Services\AccueilService;
use App\Services\ContactService;

/** C1 · Accueil (mobile, Figma 58:15) et D0 · Accueil (desktop, Figma 154:14). */
final class AccueilController
{
    public function index(Request $requete): Response
    {
        $service = AccueilService::depuisBase();
        $client = Session::get('client_id');
        $bandeau = is_int($client) ? ContactService::depuisBase()->enAttenteDeReponse($client) : null; // RG-I4
        return Response::html(View::rendre('client/accueil', [
            'titre' => null,
            'communes' => $service->communes(),
            'vehicules' => $service->vehicules(),
            'pannes' => $service->pannes(),
            'trombinoscope' => AccueilService::trombinoscope(),
            'maintenant' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            'bandeauContact' => $bandeau,
            'css' => $bandeau !== null ? ['css/pages/accueil.css', 'css/pages/contact.css'] : ['css/pages/accueil.css'],
            'js' => ['js/pages/accueil.js'],
        ], 'layouts/client'));
    }
}
