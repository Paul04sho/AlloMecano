<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AdminService;

/**
 * L'administration est réservée aux admins connectés (RG-C5) : sans session, on passe par la connexion et l'on revient où l'on voulait aller.
 * Un compte désactivé perd l'accès à la requête suivante.
 */
final class ExigerAdmin implements Middleware
{
    public function traiter(Request $requete, callable $suite): Response
    {
        $id = Session::get('admin_id');
        $admin = is_int($id) ? AdminService::depuisBase()->admin($id) : null;
        if ($admin === null) {
            Session::oublier('admin_id');
            $suivant = $requete->methode === 'GET' ? '?suivant=' . rawurlencode($requete->chemin) : '';
            return Response::redirection('/connexion' . $suivant);
        }
        if ($admin['mot_de_passe_a_changer']
            && !($requete->chemin === '/admin/mon-compte/mot-de-passe' && in_array($requete->methode, ['GET', 'POST'], true))) {
            return Response::redirection('/admin/mon-compte/mot-de-passe');
        }
        return $suite($requete);
    }
}
