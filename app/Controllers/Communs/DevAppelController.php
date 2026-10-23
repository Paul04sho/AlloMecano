<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AppelService;
use App\Services\RelaisService;

/**
 * Outil de développement : joue le mécano qui reçoit l'appel intégré, en attendant l'écran M5 (appel entrant) du chantier mécano.
 * Absent en production (comme /systeme). Aucune authentification : il n'existe que pour essayer le côté client avec deux onglets.
 */
final class DevAppelController
{
    /** GET /systeme/appel */
    public function page(Request $requete): Response
    {
        $this->garde();
        return Response::html(View::rendre('communs/systeme-appel', [
            'titre' => 'Mécano de test', 'sansBarre' => true, 'sansPied' => true, 'css' => ['css/pages/appel.css'], 'js' => ['js/pages/dev-mecano.js'],
            'mecanos' => Database::instance()->tous('SELECT public_id, prenom, nom FROM mecaniciens WHERE statut_dossier = \'valide\' ORDER BY id'),
        ], 'layouts/client'));
    }

    /** GET /api/dev/mecano/{id}/entrants */
    public function entrants(Request $requete): Response
    {
        $this->garde();
        return Response::json(['entrants' => AppelService::depuisBase()->entrants((string) $requete->param('id'))]);
    }

    /** GET /api/dev/appel/{id}/signaux?mecano=…&apres=N */
    public function signaux(Request $requete): Response
    {
        $this->garde();
        $signaux = AppelService::depuisBase()->signaux((string) $requete->param('id'), 'mecanicien', (string) $requete->query('mecano', ''), (int) $requete->query('apres', 0));
        $statut = Database::instance()->valeur('SELECT statut FROM contacts WHERE public_id = :p', ['p' => (string) $requete->param('id')]);
        return Response::json(['signaux' => $signaux, 'statut' => $statut, 'dernier' => $signaux !== [] ? end($signaux)['id'] : (int) $requete->query('apres', 0)]);
    }

    /** POST /api/dev/appel/{id}/signal {mecano, type, charge} */
    public function signal(Request $requete): Response
    {
        $this->garde();
        $charge = $requete->input('charge');
        $ok = AppelService::depuisBase()->signaler((string) $requete->param('id'), 'mecanicien', (string) $requete->input('mecano', ''), (string) $requete->input('type', ''), $charge === null ? null : json_encode($charge));
        return Response::json(['ok' => $ok], $ok ? 200 : 409);
    }

    /** POST /api/dev/appel/{id}/issue {mecano, reponse} : « J'y vais » ou « Pas pour moi » (RG-I2). */
    public function issue(Request $requete): Response
    {
        $this->garde();
        $ok = AppelService::depuisBase()->repondreMecano((string) $requete->param('id'), (string) $requete->input('mecano', ''), (string) $requete->input('reponse', ''));
        return Response::json(['ok' => $ok], $ok ? 200 : 409);
    }

    /** GET /api/dev/ice : serveurs STUN / TURN pour le mécano de test (le client passe par /api/appels/ice, qui exige un client). */
    public function ice(Request $requete): Response
    {
        $this->garde();
        return Response::json(['iceServers' => (new RelaisService())->serveurs()]);
    }

    private function garde(): void
    {
        if (Env::enProduction()) {
            throw HttpException::introuvable();
        }
    }
}
