<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ReinitialisationService;

/** X2 · Mot de passe oublié (Figma 129:112 à 131:190) en 3 étapes : l'e-mail, « regardez vos e-mails », le nouveau mot de passe (RG-C3). */
final class MotDePasseController
{
    /** GET /mot-de-passe : étape 1 */
    public function email(Request $requete): Response
    {
        return $this->page('email', ['email' => (string) Session::get('mdp_email', '')]);
    }

    /** POST /mot-de-passe */
    public function envoyer(Request $requete): Response
    {
        $email = mb_strtolower(trim((string) $requete->input('email', '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->page('email', ['email' => $email], ['email' => $email === '' ? 'Entrez votre e-mail.' : 'Cette adresse e-mail ne semble pas complète.']);
        }
        return $this->expedier($email);
    }

    /** GET /mot-de-passe/envoye : étape 2 */
    public function envoye(Request $requete): Response
    {
        $email = (string) Session::get('mdp_email', '');
        if ($email === '') {
            return Response::redirection('/mot-de-passe');
        }
        $attente = max(0, ReinitialisationService::RENVOI_S - (time() - (int) Session::get('mdp_envoye_le', 0)));
        return $this->page('envoye', ['email_masque' => ReinitialisationService::masquer($email), 'attente' => $attente]);
    }

    /** POST /mot-de-passe/renvoyer : « Renvoyer » (pas avant 60 s) */
    public function renvoyer(Request $requete): Response
    {
        $email = (string) Session::get('mdp_email', '');
        if ($email === '') {
            return Response::redirection('/mot-de-passe');
        }
        if (time() - (int) Session::get('mdp_envoye_le', 0) < ReinitialisationService::RENVOI_S) {
            return Response::redirection('/mot-de-passe/envoye');
        }
        return $this->expedier($email);
    }

    /** GET /mot-de-passe/nouveau/{jeton} : étape 3, ou « Ce lien a expiré » */
    public function nouveau(Request $requete): Response
    {
        $jeton = (string) $requete->param('jeton');
        $v = ReinitialisationService::depuisBase()->verifier($jeton);
        if ($v['etat'] === 'valide') {
            return $this->page('nouveau', ['jeton' => $jeton, 'email_masque' => $v['email_masque']]);
        }
        if ($v['etat'] === 'expire') {
            return $this->page('expire', ['jeton' => $jeton, 'email_masque' => $v['email_masque'], 'envoye_le' => $v['envoye_le']]);
        }
        Session::flash('message', 'Ce lien n’est plus valable. Demandez-en un nouveau.'); // texte à valider : non dessiné dans Figma
        return Response::redirection('/mot-de-passe');
    }

    /** POST /mot-de-passe/nouveau/{jeton} */
    public function enregistrer(Request $requete): Response
    {
        $jeton = (string) $requete->param('jeton');
        $service = ReinitialisationService::depuisBase();
        $v = $service->verifier($jeton);
        if ($v['etat'] !== 'valide') {
            return Response::redirection('/mot-de-passe/nouveau/' . rawurlencode($jeton));
        }
        $erreurs = $service->valider((string) $requete->input('mot_de_passe', ''), (string) $requete->input('confirmation', ''));
        if ($erreurs !== [] || !$service->changer($jeton, (string) $requete->input('mot_de_passe', ''))) {
            return $this->page('nouveau', ['jeton' => $jeton, 'email_masque' => $v['email_masque']], $erreurs);
        }
        return Response::redirection('/mot-de-passe/termine');
    }

    /** GET /mot-de-passe/termine : « C'est fait. » */
    public function termine(Request $requete): Response
    {
        return $this->page('termine', []);
    }

    /** POST /mot-de-passe/nouveau/{jeton}/renvoyer : « Recevoir un nouveau lien » depuis un lien expiré */
    public function renvoyerDepuisLien(Request $requete): Response
    {
        $r = ReinitialisationService::depuisBase()->renvoyerDepuis((string) $requete->param('jeton'));
        if ($r === null) {
            return Response::redirection('/mot-de-passe');
        }
        $v = ReinitialisationService::depuisBase()->verifier((string) $requete->param('jeton'));
        Session::set('mdp_email', (string) ($v['email'] ?? ''));
        Session::set('mdp_envoye_le', time());
        return Response::redirection('/mot-de-passe/envoye');
    }

    private function expedier(string $email): Response
    {
        ReinitialisationService::depuisBase()->demander($email); // la réponse est la même que l'adresse ait un compte ou non
        Session::set('mdp_email', $email);
        Session::set('mdp_envoye_le', time());
        return Response::redirection('/mot-de-passe/envoye');
    }

    /** @param array<string, mixed> $donnees @param array<string, string> $erreurs */
    private function page(string $etape, array $donnees, array $erreurs = []): Response
    {
        return Response::html(View::rendre('communs/mot-de-passe', [
            'titre' => 'Mot de passe oublié', 'sansBarre' => true, 'sansPied' => true, 'css' => ['css/pages/connexion.css'], 'js' => ['js/pages/mot-de-passe.js'],
            'etape' => $etape, 'infos' => $donnees, 'erreurs' => $erreurs,
        ], 'layouts/client'));
    }
}
