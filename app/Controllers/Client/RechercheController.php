<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AccueilService;
use App\Services\PanneService;
use App\Services\Parametres;
use App\Services\ParcoursService;
use App\Services\PositionService;
use App\Services\RechercheService;

/** C4b · Chargement (73:942), C4 · Résultats (60:196, 185:1669), C4c · Aucun (75:1070), D1 · desktop (156:274). */
final class RechercheController
{
    private const VUE = ['sansPied' => true, 'sansBarre' => true, 'classeCorps' => 'page-carte'];
    private const CSS = ['vendor/leaflet-1.9.4/leaflet.css', 'css/composants/carte.css', 'css/pages/resultats.css'];
    private const JS = ['vendor/leaflet-1.9.4/leaflet.js', 'vendor/protomaps-leaflet-5.1.0/protomaps-leaflet.js'];

    /** GET /recherche : le radar tourne pendant que le script lance la recherche. */
    public function chargement(Request $requete): Response
    {
        if (($manque = $this->manque()) !== null) {
            return Response::redirection($manque);
        }
        $position = ParcoursService::position();
        $parcours = ParcoursService::lire();
        $rayon = Session::get('rayon_demande');
        $paliers = Parametres::liste('recherche.rayons_km');
        return $this->page('client/recherche', [
            'titre' => 'Recherche en cours',
            'position' => $position,
            'lieu' => $this->lieu($position),
            'rayon' => is_int($rayon) ? $rayon : ($paliers[0] ?? 2),
            'resume' => $this->resume($parcours, $position),
            'js' => [...self::JS, 'js/pages/recherche.js'],
        ]);
    }

    /** POST /api/recherches : lance la recherche, renvoie l'adresse des résultats. */
    public function lancerApi(Request $requete): Response
    {
        if ($this->manque() !== null) {
            return Response::json(['message' => 'Il manque votre position ou votre panne.'], 422);
        }
        $r = $this->lancer();
        return Response::json(['url' => '/resultats/' . $r['public_id'], 'nb' => $r['nb'], 'rayon_km' => $r['rayon_km']]);
    }

    /** POST /recherche : même chose sans JS. */
    public function lancerFormulaire(Request $requete): Response
    {
        if (($manque = $this->manque()) !== null) {
            return Response::redirection($manque);
        }
        return Response::redirection('/resultats/' . $this->lancer()['public_id']);
    }

    /** POST /recherche/rayon : « Chercher jusqu'à 20 km » ou le filtre Rayon (D1). Jamais automatique (RG-R5). */
    public function rayon(Request $requete): Response
    {
        $permis = [...Parametres::liste('recherche.rayons_km'), Parametres::int('recherche.rayon_etendu_km', 20)];
        $rayon = filter_var($requete->input('rayon'), FILTER_VALIDATE_INT);
        if (in_array($rayon, $permis, true)) {
            Session::set('rayon_demande', $rayon);
        }
        return Response::redirection('/recherche');
    }

    /** POST /recherche/modifier : résumé modifiable sur place (D1) : véhicule ou panne. */
    public function modifier(Request $requete): Response
    {
        $parcours = ParcoursService::lire();
        $accueil = AccueilService::depuisBase();
        $r = PanneService::valider([
            'vehicule' => $requete->input('vehicule', $parcours['vehicule'] ?? ''),
            'panne' => $requete->input('panne', $parcours['panne'] ?? ''),
        ], array_keys($accueil->vehicules()), array_column($accueil->pannes(), 'code'));
        if ($r['erreurs'] === []) {
            ParcoursService::enregistrerPanne((string) $r['propre']['vehicule'], (string) $r['propre']['panne'], $parcours['precisions'] ?? null);
        }
        return Response::redirection('/recherche');
    }

    /** GET /resultats/{id} : la liste figée de cette recherche (RG-R7), seulement pour son client (RG-C7). */
    public function resultats(Request $requete): Response
    {
        $client = Session::get('client_id');
        $donnees = is_int($client) ? RechercheService::depuisBase()->lire((string) $requete->param('id'), $client) : null;
        if ($donnees === null) {
            throw HttpException::introuvable();
        }
        $r = $donnees['recherche'];
        $accueil = AccueilService::depuisBase();
        return $this->page('client/resultats', [
            'titre' => $donnees['resultats'] === [] ? 'Aucun mécano disponible' : 'Mécanos disponibles',
            'recherche' => $r,
            'resultats' => $donnees['resultats'],
            'resume' => ['vehicule' => $r['type_vehicule'], 'vehicule_libelle' => $r['vehicule_libelle'], 'panne' => $r['panne'],
                'panne_libelle' => $r['panne_libelle'], 'lieu' => $r['quartier'] ?? 'Votre position'],
            'vehicules' => $accueil->vehicules(),
            'pannes' => $accueil->pannes(),
            'rayonEtendu' => Parametres::int('recherche.rayon_etendu_km', 20),
            'rayons' => [...Parametres::liste('recherche.rayons_km'), Parametres::int('recherche.rayon_etendu_km', 20)],
            'js' => [...self::JS, 'js/pages/resultats.js'],
        ]);
    }

    /** @return array{public_id: string, nb: int, rayon_km: int} */
    private function lancer(): array
    {
        $parcours = ParcoursService::lire();
        $rayon = Session::get('rayon_demande');
        Session::oublier('rayon_demande');
        return RechercheService::depuisBase()->lancer(
            (int) Session::get('client_id'), (array) ParcoursService::position(), (string) $parcours['vehicule'], (string) $parcours['panne'],
            $parcours['precisions'] ?? null, is_int($rayon) ? $rayon : null
        );
    }

    /** L'étape qui manque au parcours, ou null s'il est complet. */
    private function manque(): ?string
    {
        if (ParcoursService::position() === null) {
            return '/position';
        }
        $p = ParcoursService::lire();
        return empty($p['vehicule']) || empty($p['panne']) ? '/panne' : null;
    }

    /** @param array<string, mixed> $position */
    private function lieu(array $position): string
    {
        $q = $position['quartier'] !== null ? PositionService::depuisBase()->quartier((string) $position['quartier']) : null;
        return $q['nom'] ?? 'votre position';
    }

    /**
     * @param array<string, mixed> $parcours
     * @param array<string, mixed> $position
     * @return array{vehicule: string, vehicule_libelle: string, panne: string, panne_libelle: string, lieu: string}
     */
    private function resume(array $parcours, array $position): array
    {
        $accueil = AccueilService::depuisBase();
        $pannes = array_column($accueil->pannes(), 'libelle', 'code');
        return ['vehicule' => (string) $parcours['vehicule'], 'vehicule_libelle' => $accueil->vehicules()[$parcours['vehicule']] ?? '',
            'panne' => (string) $parcours['panne'], 'panne_libelle' => $pannes[$parcours['panne']] ?? '', 'lieu' => ucfirst($this->lieu($position))];
    }

    /** @param array<string, mixed> $donnees */
    private function page(string $vue, array $donnees): Response
    {
        return Response::html(View::rendre($vue, $donnees + self::VUE + ['css' => self::CSS], 'layouts/client'));
    }
}
