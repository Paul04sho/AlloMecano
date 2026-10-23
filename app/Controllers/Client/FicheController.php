<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AccueilService;
use App\Services\FicheService;
use App\Services\ParcoursService;
use App\Services\PositionService;
use App\Services\RechercheService;

/** C5 · Fiche mécanicien (Figma 76:1211, sombre 185:1697, plus disponible 78:1304) et le panneau de D1 (158:542). */
final class FicheController
{
    /** Avis affichés d'abord, puis par paquets (« Lire les 37 avis », 76:1350) */
    private const AVIS_VISIBLES = 2;
    private const AVIS_PAR_PAQUET = 10;

    /** GET /mecano/{slug}?r=<recherche> : la fiche en pleine page. */
    public function page(Request $requete): Response
    {
        $d = self::donnees((string) $requete->param('slug'), $this->recherche($requete), self::tous($requete));
        return Response::html(View::rendre('client/fiche', $d + [
            'titre' => $d['fiche']['nom_complet'] . ' · ' . $d['fiche']['metier'],
            'sansBarre' => true, 'sansPied' => true, 'classeCorps' => 'page-fiche',
            'css' => ['css/pages/fiche.css'], 'js' => ['js/pages/fiche.js'],
        ], 'layouts/client'));
    }

    /** GET /api/mecano/{slug}/fiche?r=<recherche> : le panneau de 420 px de D1, sans recharger la page. */
    public function panneau(Request $requete): Response
    {
        return Response::html(View::rendre('client/fiche-panneau', self::donnees((string) $requete->param('slug'), $this->recherche($requete), false)));
    }

    /** GET /api/mecano/{slug}/avis?decalage=N : le paquet d'avis suivant, déjà mis en page. */
    public function avis(Request $requete): Response
    {
        $service = FicheService::depuisBase();
        $fiche = $service->fiche((string) $requete->param('slug'));
        if ($fiche === null) {
            throw HttpException::introuvable();
        }
        $decalage = max(0, (int) $requete->query('decalage', self::AVIS_VISIBLES));
        $paquet = $service->avis($fiche['public_id'], self::AVIS_PAR_PAQUET, $decalage);
        $html = '';
        foreach ($paquet['avis'] as $a) {
            $html .= View::rendre('composants/avis', $a + ['prenomMecano' => $fiche['prenom']]);
        }
        return Response::json(['html' => $html, 'decalage' => $decalage + count($paquet['avis']), 'reste' => max(0, $paquet['total'] - $decalage - count($paquet['avis']))]);
    }

    /**
     * Tout ce que les vues de la fiche affichent : la fiche, les avis, le message, d'où on vient (recherche figée, RG-R7).
     *
     * @return array<string, mixed>
     */
    public static function donnees(string $slug, ?string $recherchePublic, bool $tousLesAvis): array
    {
        $service = FicheService::depuisBase();
        $client = Session::get('client_id');
        $recherche = $recherchePublic !== null && is_int($client) ? RechercheService::depuisBase()->lire($recherchePublic, $client) : null;
        $parcours = ParcoursService::lire();
        $position = ParcoursService::position();

        // Distance : celle de la liste figée si on vient d'une recherche, sinon depuis la position du client
        $fiche = $service->fiche($slug, null, $position !== null ? ['lat' => $position['lat'], 'lon' => $position['lon']] : null);
        if ($fiche === null) {
            throw HttpException::introuvable();
        }
        if ($recherche !== null) {
            $ligne = array_values(array_filter($recherche['resultats'], static fn (array $r): bool => $r['public_id'] === $fiche['public_id']))[0] ?? null;
            if ($ligne !== null) {
                $fiche['distance_m'] = $ligne['distance_m'];
            }
        } elseif ($recherchePublic !== null) {
            $recherchePublic = null; // pas la recherche de ce client : on ne s'y réfère pas (RG-C7)
        }

        $accueil = AccueilService::depuisBase();
        $pannes = array_column($accueil->pannes(), 'libelle', 'code');
        if ($recherche !== null) {
            $r = $recherche['recherche'];
            $lieu = $r['quartier'] ?? null;
            $repere = $r['repere'] ?? null;
            $vehicule = $r['vehicule_libelle'];
            $panne = $r['panne_libelle'];
            $exacte = $r['position_effacee_le'] === null; // RG-P1 : plus de lien de position après 24 h
            $lat = $exacte ? (float) $r['lat'] : null;
            $lon = $exacte ? (float) $r['lon'] : null;
        } else {
            $quartier = $position !== null && $position['quartier'] !== null ? PositionService::depuisBase()->quartier($position['quartier']) : null;
            $lieu = $quartier['nom'] ?? null;
            $repere = $position['repere'] ?? null;
            $vehicule = isset($parcours['vehicule']) ? ($accueil->vehicules()[$parcours['vehicule']] ?? null) : null;
            $panne = isset($parcours['panne']) ? ($pannes[$parcours['panne']] ?? null) : null;
            $lat = $position['lat'] ?? null;
            $lon = $position['lon'] ?? null;
        }
        $avis = $service->avis($fiche['public_id'], $tousLesAvis ? 50 : self::AVIS_VISIBLES);

        return [
            'fiche' => $fiche,
            'avis' => $avis['avis'],
            'totalAvis' => $avis['total'],
            'message' => FicheService::message($fiche['prenom'], compact('lieu', 'repere', 'vehicule', 'panne'), $lat, $lon),
            'recherche' => $recherche['recherche'] ?? null,
            'retour' => $recherche !== null ? '/resultats/' . $recherche['recherche']['public_id'] : ($position !== null ? '/recherche' : '/'),
        ];
    }

    /** Recherche d'où l'on vient, si l'adresse la donne (ULID de 26 caractères). */
    private function recherche(Request $requete): ?string
    {
        $r = (string) $requete->query('r', '');
        return preg_match('/^[0-9A-Z]{26}$/', $r) === 1 ? $r : null;
    }

    private static function tous(Request $requete): bool
    {
        return (string) $requete->query('avis', '') === 'tous';
    }
}
