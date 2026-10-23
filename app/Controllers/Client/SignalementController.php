<?php

declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ClientService;
use App\Services\SignalementService;

/** C7 · Signaler (Figma 89:1612) et « Signalement envoyé » (90:1660). Seulement après un contact avec ce mécano, une fois par contact (RG-S1). */
final class SignalementController
{
    /** GET /signaler/{slug}?contact=… */
    public function formulaire(Request $requete): Response
    {
        $client = $this->client();
        $r = SignalementService::depuisBase()->cible($client, (string) $requete->param('slug'), $this->contactDuLien($requete));
        if (!isset($r['cible'])) {
            return $this->refus($r['refus'], (string) $requete->param('slug'));
        }
        $moi = ClientService::depuisBase()->contact($client);
        return $this->page($r['cible'], ['erreurs' => [], 'saisie' => ['motif' => '', 'description' => '', 'prenom' => $moi['prenom'] ?? '', 'telephone' => Format::telephoneLocal($moi['telephone'])]]);
    }

    /** POST /signaler/{slug} */
    public function envoyer(Request $requete): Response
    {
        $client = $this->client();
        $slug = (string) $requete->param('slug');
        $service = SignalementService::depuisBase();
        $lien = $this->contactDuLien($requete);
        $r = $service->cible($client, $slug, $lien);
        if (!isset($r['cible'])) {
            return $this->refus($r['refus'], $slug);
        }
        $saisie = ['motif' => (string) $requete->input('motif', ''), 'description' => (string) $requete->input('description', ''),
            'prenom' => (string) $requete->input('prenom', ''), 'telephone' => (string) $requete->input('telephone', '')];
        $v = $service->valider($saisie);
        $erreurs = $v['erreurs'];
        $photo = $requete->fichier('photo');
        if ($photo !== null && ($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $erreurs['photo'] = 'La photo n’a pas pu être envoyée. Essayez-en une autre, ou envoyez sans photo.';
        }
        if ($erreurs === []) {
            $envoi = $service->enregistrer($client, $slug, $lien, $v['propre'], $photo !== null ? (string) $photo['tmp_name'] : null);
            if (isset($envoi['public_id'])) {
                Session::flash('signalement_envoye', gmdate('H:i'));
                return Response::redirection('/signalement/' . rawurlencode($envoi['public_id']));
            }
            if ($envoi['erreur'] !== 'photo') {
                return $this->refus($envoi['erreur'], $slug);
            }
            $erreurs['photo'] = 'Ce fichier n’est pas une photo. Choisissez une image JPEG, PNG ou WebP.';
        }
        return $this->page($r['cible'], ['erreurs' => $erreurs, 'saisie' => $saisie]);
    }

    /** GET /signalement/{id} */
    public function envoye(Request $requete): Response
    {
        $s = SignalementService::depuisBase()->envoye($this->client(), (string) $requete->param('id'));
        if ($s === null) {
            throw HttpException::introuvable();
        }
        return Response::html(View::rendre('client/signalement-envoye', [
            'titre' => 'Signalement envoyé', 'sansBarre' => true, 'sansPied' => true, 'classeCorps' => 'page-formulaire', 'css' => ['css/pages/avis.css', 'css/pages/signalement.css'],
            'signalement' => $s, 'heure' => Session::flash('signalement_envoye') ?? Format::heure($s['cree_le']),
        ], 'layouts/client'));
    }

    /** @param array<string, mixed> $cible @param array<string, mixed> $donnees */
    private function page(array $cible, array $donnees): Response
    {
        return Response::html(View::rendre('client/signaler', $donnees + [
            'titre' => 'Signaler ' . $cible['nom_complet'], 'sansBarre' => true, 'sansPied' => true, 'classeCorps' => 'page-formulaire',
            'css' => ['css/pages/avis.css', 'css/pages/signalement.css'], 'js' => ['js/pages/signalement.js'], 'cible' => $cible,
        ], 'layouts/client'));
    }

    private function contactDuLien(Request $requete): ?string
    {
        $c = (string) ($requete->input('contact') ?? $requete->query('contact', ''));
        return preg_match('/^[0-9A-Z]{26}$/', $c) === 1 ? $c : null;
    }

    /** Pas de signalement possible : on dit pourquoi (textes à valider, non dessinés). */
    private function refus(string $raison, string $slug): Response
    {
        if ($raison === 'inconnu') {
            throw HttpException::introuvable();
        }
        Session::flash('message', $raison === 'deja' ? 'Vous avez déjà signalé ce mécano pour cet appel.' : 'Vous pouvez signaler un mécano après l’avoir contacté.');
        return Response::redirection('/mecano/' . rawurlencode($slug));
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
