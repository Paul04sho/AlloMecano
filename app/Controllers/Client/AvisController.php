<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AvisService;
use App\Services\ClientService;

/** C6b · Votre avis (Figma 87:1576) et « Merci » (88:1609). Seulement après un vrai contact (RG-AV1). */
final class AvisController
{
    /** GET /avis/{contact} */
    public function formulaire(Request $requete): Response
    {
        $client = $this->client();
        $service = AvisService::depuisBase();
        $droit = $service->droit($client, (string) $requete->param('contact'));
        if (!isset($droit['contact'])) {
            return $this->refus($droit['refus']);
        }
        $c = $droit['contact'];
        $existant = $c['avis'];
        $moi = ClientService::depuisBase()->contact($client);
        return $this->page($c, [
            'erreurs' => [],
            'saisie' => $existant !== null
                ? ['note' => $existant['note'], 'etiquettes' => $existant['etiquettes'], 'commentaire' => $existant['commentaire'], 'prenom' => $existant['prenom'] ?? $moi['prenom'], 'telephone' => $moi['telephone']]
                : ['note' => 0, 'etiquettes' => [], 'commentaire' => '', 'prenom' => $moi['prenom'] ?? '', 'telephone' => $moi['telephone'] ?? ''],
            'verrouille' => $existant !== null && !$existant['modifiable'],
        ]);
    }

    /** POST /avis/{contact} */
    public function publier(Request $requete): Response
    {
        $client = $this->client();
        $id = (string) $requete->param('contact');
        $service = AvisService::depuisBase();
        $droit = $service->droit($client, $id);
        if (!isset($droit['contact'])) {
            return $this->refus($droit['refus']);
        }
        $saisie = ['note' => $requete->input('note'), 'etiquettes' => (array) $requete->input('etiquettes', []), 'commentaire' => $requete->input('commentaire', ''),
            'prenom' => $requete->input('prenom', ''), 'telephone' => $requete->input('telephone', '')];
        $v = $service->valider($saisie);
        if ($v['erreurs'] === []) {
            $r = $service->publier($client, $id, $v['propre']);
            if (!isset($r['erreur'])) {
                Session::flash('avis_publie', gmdate('H:i'));
                return Response::redirection('/avis/' . rawurlencode($id) . '/merci');
            }
            if ($r['erreur'] === 'verrouille') {
                Session::flash('message', 'Votre avis ne peut plus être modifié (24 h).');
                return Response::redirection('/avis/' . rawurlencode($id) . '/merci');
            }
            return $this->refus($r['erreur']);
        }
        return $this->page($droit['contact'], ['erreurs' => $v['erreurs'], 'saisie' => $saisie + ['note' => (int) ($saisie['note'] ?? 0)], 'verrouille' => false]);
    }

    /** GET /avis/{contact}/merci */
    public function merci(Request $requete): Response
    {
        $client = $this->client();
        $droit = AvisService::depuisBase()->droit($client, (string) $requete->param('contact'));
        $avis = $droit['contact']['avis'] ?? null;
        if ($avis === null) {
            return $this->refus($droit['refus'] ?? 'pas_de_droit');
        }
        $heure = Session::flash('avis_publie') ?? \App\Core\Format::heure($avis['cree_le']);
        return Response::html(View::rendre('client/avis-merci', [
            'titre' => 'Merci pour votre avis', 'sansBarre' => true, 'sansPied' => true, 'css' => ['css/pages/fiche.css', 'css/pages/avis.css'],
            'contact' => $droit['contact'], 'avis' => $avis, 'heure' => $heure,
        ], 'layouts/client'));
    }

    /** @param array<string, mixed> $contact @param array<string, mixed> $donnees */
    private function page(array $contact, array $donnees): Response
    {
        return Response::html(View::rendre('client/avis', $donnees + [
            'titre' => 'Votre avis sur ' . $contact['prenom'], 'sansBarre' => true, 'sansPied' => true, 'classeCorps' => 'page-formulaire',
            'css' => ['css/pages/avis.css'], 'js' => ['js/pages/avis.js'],
            'contact' => $contact, 'etiquettes' => AvisService::depuisBase()->etiquettes(),
        ], 'layouts/client'));
    }

    /** Pas de droit d'avis : on dit pourquoi, on ne casse rien (texte à valider, non dessiné). */
    private function refus(string $raison): Response
    {
        if ($raison === 'inconnu') {
            throw HttpException::introuvable();
        }
        Session::flash('message', $raison === 'delai' ? 'Le délai de 7 jours pour donner un avis est passé.' : 'Vous pouvez donner un avis après avoir contacté ce mécano.');
        return Response::redirection('/');
    }

    private function client(): int
    {
        $client = Session::get('client_id');
        if (!is_int($client)) {
            throw HttpException::introuvable();
        }
        return $client;
    }
}
