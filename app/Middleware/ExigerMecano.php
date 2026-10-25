<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MecanoService;

/**
 * L'espace pro est réservé aux mécanos connectés (RG-C1) : sans session, on passe par la connexion et l'on revient où l'on voulait aller.
 * Un compte supprimé (RG-C6) perd l'accès à la requête suivante.
 */
final class ExigerMecano implements Middleware
{
    public function traiter(Request $requete, callable $suite): Response
    {
        $id = Session::get('mecano_id');
        if (!is_int($id) || MecanoService::depuisBase()->compte($id) === null) {
            Session::oublier('mecano_id');
            $suivant = $requete->methode === 'GET' ? '?suivant=' . rawurlencode($requete->chemin) : '';
            return Response::redirection('/connexion' . $suivant);
        }
        return $suite($requete);
    }
}
