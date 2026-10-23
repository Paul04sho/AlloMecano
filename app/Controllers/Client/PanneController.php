<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AccueilService;
use App\Services\ClientService;
use App\Services\PanneService;
use App\Services\ParcoursService;
use App\Services\PositionService;

/** C3 · Ma panne (Figma 69:653) et le cas « Choc ou accident » (69:868). */
final class PanneController
{
    /** GET /panne · ?commune=cocody : lancé depuis le tableau des communes de l'accueil (Ligne commune 56:734). */
    public function formulaire(Request $requete): Response
    {
        $commune = (string) $requete->query('commune', '');
        if ($commune !== '') {
            $position = PositionService::depuisBase();
            $c = $position->commune($commune);
            if ($c === null) {
                return Response::redirection('/position/quartier');
            }
            // RG-R1 : sans GPS, la position est le centre (ici celui de la commune choisie)
            $proche = $position->quartierLePlusProche($c['lat'], $c['lon']);
            ParcoursService::enregistrerPosition('quartier', $c['lat'], $c['lon'], null, $proche['slug'] ?? null, null);
            return Response::redirection('/panne');
        }
        if (ParcoursService::position() === null) {
            return Response::redirection('/position');
        }
        $accueil = AccueilService::depuisBase();
        $parcours = ParcoursService::lire();
        $saisie = Session::flash('panne_saisie');
        $saisie = is_string($saisie) ? (array) json_decode($saisie, true) : [];
        $client = Session::get('client_id');
        $contact = is_int($client) ? ClientService::depuisBase()->contact($client) : ['prenom' => null, 'telephone' => null];
        $panne = $saisie['panne'] ?? $parcours['panne'] ?? null;
        $erreurs = Session::flash('panne_erreurs');
        return Response::html(View::rendre('client/panne', [
            'titre' => 'Ma panne',
            'vehicules' => $accueil->vehicules(),
            'pannes' => $accueil->pannes(),
            'vehicule' => $saisie['vehicule'] ?? $parcours['vehicule'] ?? 'voiture',
            'panne' => $panne,
            'precisions' => $saisie['precisions'] ?? $parcours['precisions'] ?? null,
            'prenom' => $saisie['prenom'] ?? $contact['prenom'],
            'telephone' => $saisie['telephone'] ?? $contact['telephone'],
            'erreurs' => is_string($erreurs) ? (array) json_decode($erreurs, true) : [],
            'retour' => ParcoursService::position()['source'] === 'quartier' ? '/position/quartier' : '/position/confirmer',
            'sansPied' => true,
            'classeCorps' => 'page-parcours',
            'css' => ['css/pages/position.css', 'css/pages/panne.css'],
            'js' => ['js/pages/panne.js'],
        ], 'layouts/client'));
    }

    /** POST /panne : validation serveur, puis la recherche (Partie 4). */
    public function enregistrer(Request $requete): Response
    {
        if (ParcoursService::position() === null) {
            return Response::redirection('/position');
        }
        $accueil = AccueilService::depuisBase();
        $donnees = $requete->tout();
        $r = PanneService::valider($donnees, array_keys($accueil->vehicules()), array_column($accueil->pannes(), 'code'));
        if ($r['erreurs'] !== []) {
            Session::flash('panne_erreurs', (string) json_encode($r['erreurs'], JSON_UNESCAPED_UNICODE));
            Session::flash('panne_saisie', (string) json_encode(array_intersect_key($donnees, array_flip(['vehicule', 'panne', 'precisions', 'prenom', 'telephone'])), JSON_UNESCAPED_UNICODE));
            return Response::redirection('/panne');
        }
        $p = $r['propre'];
        ParcoursService::enregistrerPanne((string) $p['vehicule'], (string) $p['panne'], $p['precisions']);
        $client = Session::get('client_id');
        if (is_int($client)) {
            // Facultatifs, jamais affichés publiquement : donnés au mécano au moment du contact
            ClientService::depuisBase()->enregistrerContact($client, $p['prenom'], $p['telephone']);
        }
        return Response::redirection('/recherche');
    }

    /** POST /panne/effacer (« Effacer », sans JS) : on oublie prénom et téléphone, le reste de la saisie est gardé. */
    public function effacerContact(Request $requete): Response
    {
        $client = Session::get('client_id');
        if (is_int($client)) {
            ClientService::depuisBase()->effacerContact($client);
        }
        $saisie = array_intersect_key($requete->tout(), array_flip(['vehicule', 'panne', 'precisions']));
        Session::flash('panne_saisie', (string) json_encode($saisie + ['prenom' => null, 'telephone' => null], JSON_UNESCAPED_UNICODE));
        return Response::redirection('/panne');
    }
}
