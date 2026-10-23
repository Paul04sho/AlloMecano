<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ClientService;

/**
 * Parcours client : chaque page connaît son client anonyme (RG-C7). Un client neuf reçoit son jeton en cookie
 * HttpOnly ; la page le confie une fois au navigateur (localStorage) par <meta name="client-jeton">.
 */
final class IdentifierClient implements Middleware
{
    public function traiter(Request $requete, callable $suite): Response
    {
        $client = ClientService::depuisBase()->identifier($requete->cookie(ClientService::COOKIE));
        Session::set('client_id', $client['id']);
        if ($client['nouveau'] !== null) {
            ClientService::poserCookie($client['nouveau']);
            Session::set('client_jeton_neuf', $client['nouveau']);
        }
        return $suite($requete);
    }
}
