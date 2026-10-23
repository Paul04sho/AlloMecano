<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ClientService;

/** RG-C7 : le cookie a disparu, le navigateur renvoie sa copie (localStorage) pour retrouver son historique. */
final class ClientController
{
    public function restaurer(Request $requete): Response
    {
        $jeton = (string) $requete->input('jeton', '');
        $provisoire = Session::get('client_id');
        $id = ClientService::depuisBase()->restaurer($jeton, is_int($provisoire) ? $provisoire : null);
        if ($id === null) {
            return Response::json(['message' => 'Jeton illisible.'], 422);
        }
        ClientService::poserCookie($jeton);
        Session::set('client_id', $id);
        Session::oublier('client_jeton_neuf');
        return Response::json(['ok' => true]);
    }

    /** « Effacer » (C3) : prénom et téléphone oubliés, en base comme sur le téléphone. */
    public function effacer(Request $requete): Response
    {
        $client = Session::get('client_id');
        if (is_int($client)) {
            ClientService::depuisBase()->effacerContact($client);
        }
        return Response::json(['ok' => true]);
    }
}
