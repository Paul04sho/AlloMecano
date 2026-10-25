<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AvisMecanoService;
use App\Services\DemandesMecanoService;
use App\Services\MecanoService;

/**
 * M3 · Demandes (Figma 116:967) et M3 · Avis (118:1060) : ce que les clients ont fait et dit. Réservé aux mécanos dont le dossier est validé (RG-D1) :
 * avant, il n'y a ni demande ni avis, et l'onglet ramène au statut du dossier.
 */
final class ActiviteController
{
    private const CSS = ['css/pages/pro.css', 'css/pages/pro-tableau.css', 'css/pages/pro-activite.css'];

    /** GET /pro/demandes */
    public function demandes(Request $requete): Response
    {
        $id = $this->mecano();
        if ($id === null) {
            return Response::redirection('/pro');
        }
        $service = DemandesMecanoService::depuisBase();
        return Response::html(View::rendre('pro/demandes', [
            'titre' => 'Demandes', 'css' => self::CSS, 'onglet' => 'demandes', 'demandes' => $service->aRappeler($id), 'sonnerie' => true, 'liste' => $service->liste($id),
        ], 'layouts/pro'));
    }

    /** GET /pro/avis?avis=tous&repondre=<contact> */
    public function avis(Request $requete): Response
    {
        $id = $this->mecano();
        if ($id === null) {
            return Response::redirection('/pro');
        }
        $ouvert = (string) $requete->query('repondre', '');
        return $this->pageAvis($id, preg_match('/^[0-9A-Z]{26}$/', $ouvert) === 1 ? $ouvert : null, null, null, (string) $requete->query('avis', '') === 'tous');
    }

    /** POST /pro/avis/{id}/repondre {reponse} : RG-AV8, une réponse publique par avis, modifiable. */
    public function repondre(Request $requete): Response
    {
        $id = $this->mecano();
        if ($id === null) {
            return Response::redirection('/pro');
        }
        $contact = (string) $requete->param('id');
        $texte = (string) $requete->input('reponse', '');
        $r = AvisMecanoService::depuisBase()->repondre($id, $contact, $texte);
        if (!$r['ok']) {
            return $this->pageAvis($id, $contact, $r['erreur'], $texte, true);
        }
        Session::flash('message', 'Votre réponse est publiée.');
        return Response::redirection('/pro/avis#avis-' . $contact);
    }

    /** POST /pro/avis/{id}/signaler : « Signaler un avis abusif » (RG-AV4 : l'équipe examine, et peut masquer). */
    public function signaler(Request $requete): Response
    {
        $id = $this->mecano();
        if ($id === null) {
            return Response::redirection('/pro');
        }
        $contact = (string) $requete->param('id');
        $r = AvisMecanoService::depuisBase()->signaler($id, $contact, $requete->ip());
        Session::flash('message', $r['ok'] ? 'Merci. L’équipe regarde cet avis.' : $r['erreur']);
        return Response::redirection('/pro/avis#avis-' . $contact);
    }

    private function pageAvis(int $id, ?string $ouvert, ?string $erreur, ?string $saisie, bool $tous): Response
    {
        $service = AvisMecanoService::depuisBase();
        return Response::html(View::rendre('pro/avis', [
            'titre' => 'Avis', 'css' => self::CSS, 'onglet' => 'avis', 'demandes' => DemandesMecanoService::depuisBase()->aRappeler($id), 'sonnerie' => true,
            'page' => $service->page($id, $tous), 'tous' => $tous, 'ouvert' => $ouvert, 'erreur' => $erreur, 'saisie' => $saisie,
        ], 'layouts/pro'));
    }

    /** Le mécano connecté, si son dossier est validé : lui seul a des demandes et des avis. */
    private function mecano(): ?int
    {
        $id = (int) Session::get('mecano_id');
        return MecanoService::depuisBase()->compte($id)['statut_dossier'] === 'valide' ? $id : null;
    }
}
