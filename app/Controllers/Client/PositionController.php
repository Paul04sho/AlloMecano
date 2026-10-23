<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AccueilService;
use App\Services\ParcoursService;
use App\Services\PositionService;

/**
 * C2a · Localisation GPS (66:437, 198:1963) et confirmation (67:462) ; C2b · Commune, quartier, repère (68:604).
 * La position passe toujours en POST, jamais dans l'URL (RG-P1).
 */
final class PositionController
{
    private const VUE = ['sansPied' => true, 'classeCorps' => 'page-parcours'];

    /** GET /position : C2a. Reprend le véhicule et la panne choisis sur l'accueil desktop (D0). */
    public function gps(Request $requete): Response
    {
        $this->retenirChoix($requete);
        return $this->page('client/position', [
            'titre' => 'Où êtes-vous ?',
            'horsAbidjan' => Session::flash('position_hors_abidjan') !== null,
            'erreur' => Session::flash('position_erreur'),
            'css' => ['css/composants/carte.css', 'css/pages/position.css'],
            'js' => ['js/pages/position.js'],
        ]);
    }

    /** POST /position : mesure GPS du navigateur. */
    public function enregistrerGps(Request $requete): Response
    {
        $position = PositionService::valider($requete->tout());
        if ($position === 'hors_abidjan') {
            Session::flash('position_hors_abidjan', '1');
            return Response::redirection('/position');
        }
        if (is_string($position)) {
            Session::flash('position_erreur', $position);
            return Response::redirection('/position');
        }
        ParcoursService::enregistrerGps($position['lat'], $position['lon'], $position['precision_m']);
        return Response::redirection('/position/confirmer');
    }

    /** GET /position/confirmer : la carte glisse sous l'écrou, le client ajuste et confirme. */
    public function confirmer(Request $requete): Response
    {
        $gps = ParcoursService::gps();
        if ($gps === null) {
            return Response::redirection('/position');
        }
        $service = PositionService::depuisBase();
        $quartier = $service->quartierLePlusProche($gps['lat'], $gps['lon']);
        $position = ParcoursService::position();
        return $this->page('client/position-confirmer', [
            'titre' => 'Confirmer la position',
            'gps' => $gps,
            'quartier' => $quartier,
            'coordonnees' => PositionService::coordonnees($gps['lat'], $gps['lon'], $gps['precision_m']),
            'imprecis' => ($gps['precision_m'] ?? 0) > PositionService::PRECISION_AJUSTER_M, // RG-R1
            'repere' => $position['repere'] ?? null,
            'quartiers' => $service->quartiers(),
            'css' => ['vendor/leaflet-1.9.4/leaflet.css', 'css/composants/carte.css', 'css/pages/position.css'],
            'js' => ['vendor/leaflet-1.9.4/leaflet.js', 'vendor/protomaps-leaflet-5.1.0/protomaps-leaflet.js', 'js/pages/position-confirmer.js'],
        ]);
    }

    /** POST /position/confirmer : point GPS (ou déplacé sur la carte) + repère. */
    public function enregistrerConfirmation(Request $requete): Response
    {
        $gps = ParcoursService::gps();
        $point = PositionService::valider($requete->tout());
        if ($gps === null || !is_array($point)) {
            Session::flash('position_erreur', 'La position n’a pas pu être confirmée. Réessayez.');
            return Response::redirection('/position');
        }
        // RG-R1 : la troisième source, « point déplacé sur la carte », dès que le client a bougé le point
        $deplace = (string) $requete->input('deplace') === '1';
        $quartier = PositionService::depuisBase()->quartierLePlusProche($point['lat'], $point['lon']);
        ParcoursService::enregistrerPosition(
            $deplace ? 'carte' : 'gps', $point['lat'], $point['lon'], $deplace ? null : $gps['precision_m'],
            $quartier['slug'] ?? null, (string) $requete->input('repere', '')
        );
        return Response::redirection('/panne');
    }

    /** GET /position/quartier : C2b. ?commune=cocody ouvre ses quartiers, ?q= reprend la saisie de D0. */
    public function quartier(Request $requete): Response
    {
        $this->retenirChoix($requete);
        $service = PositionService::depuisBase();
        $position = ParcoursService::position();
        $retenu = $position !== null && $position['source'] === 'quartier' ? $service->quartier((string) $position['quartier']) : null;
        $commune = $service->commune((string) $requete->query('commune', '')) ?? ($retenu !== null ? $service->commune($retenu['commune']) : null);
        $q = trim((string) $requete->query('q', ''));
        return $this->page('client/position-quartier', [
            'titre' => 'Commune, quartier, repère',
            'communes' => $service->communes(),
            'quartiers' => $service->quartiers(),
            'commune' => $commune,
            'q' => $q,
            'resultats' => $q !== '' ? array_slice($service->chercher($q), 0, 12) : null,
            'choix' => $retenu['slug'] ?? null,
            'repere' => $position['repere'] ?? null,
            'erreur' => Session::flash('quartier_erreur'),
            'css' => ['css/pages/position.css'],
            'js' => ['js/pages/position-quartier.js'],
        ]);
    }

    /** POST /position/quartier : quartier choisi (centre du quartier) ou « je ne sais pas » (centre de la commune). */
    public function enregistrerQuartier(Request $requete): Response
    {
        $service = PositionService::depuisBase();
        $choix = (string) $requete->input('quartier', '');
        $repere = (string) $requete->input('repere', '');
        if (str_starts_with($choix, 'commune:') && ($commune = $service->commune(substr($choix, 8))) !== null) {
            $proche = $service->quartierLePlusProche($commune['lat'], $commune['lon']);
            ParcoursService::enregistrerPosition('quartier', $commune['lat'], $commune['lon'], null, $proche['slug'] ?? null, $repere);
            return Response::redirection('/panne');
        }
        if (($quartier = $service->quartier($choix)) !== null) {
            ParcoursService::enregistrerPosition('quartier', $quartier['lat'], $quartier['lon'], null, $quartier['slug'], $repere);
            return Response::redirection('/panne');
        }
        // Sans JS : seule la commune a été choisie, on ouvre ses quartiers
        $commune = $service->commune((string) $requete->input('commune', ''));
        if ($commune !== null) {
            return Response::redirection('/position/quartier?commune=' . rawurlencode($commune['slug']));
        }
        Session::flash('quartier_erreur', 'Choisissez votre quartier, ou « Je ne sais pas » pour partir du centre de la commune.');
        return Response::redirection('/position/quartier');
    }

    /** Véhicule et panne de D0 : seulement des valeurs connues. */
    private function retenirChoix(Request $requete): void
    {
        $vehicule = (string) $requete->query('vehicule', '');
        $panne = (string) $requete->query('panne', '');
        if ($vehicule === '' && $panne === '') {
            return;
        }
        $accueil = AccueilService::depuisBase();
        ParcoursService::enregistrerChoix(
            array_key_exists($vehicule, $accueil->vehicules()) ? $vehicule : null,
            in_array($panne, array_column($accueil->pannes(), 'code'), true) ? $panne : null
        );
    }

    /** @param array<string, mixed> $donnees */
    private function page(string $vue, array $donnees): Response
    {
        return Response::html(View::rendre($vue, $donnees + self::VUE, 'layouts/client'));
    }
}
