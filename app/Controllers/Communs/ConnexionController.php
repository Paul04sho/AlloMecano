<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use App\Services\GoogleAuthService;

/** X1 · Connexion (Figma 127:69 · 128:69) : mécano (téléphone ou e-mail) et admin (e-mail), mot de passe ou Google (RG-C1, RG-C2, RG-C5). */
final class ConnexionController
{
    /** GET /connexion */
    public function formulaire(Request $requete): Response
    {
        if (Session::get('admin_id') !== null) {
            return Response::redirection('/admin');
        }
        if (Session::get('mecano_id') !== null) {
            return Response::redirection('/pro');
        }
        return $this->page(['identifiant' => '', 'suivant' => $this->suivant((string) $requete->query('suivant', ''))], null);
    }

    /** POST /connexion */
    public function connecter(Request $requete): Response
    {
        $saisie = ['identifiant' => trim((string) $requete->input('identifiant', '')), 'suivant' => $this->suivant((string) $requete->input('suivant', ''))];
        $mdp = (string) $requete->input('mot_de_passe', '');
        $erreurs = [];
        if ($saisie['identifiant'] === '') {
            $erreurs['identifiant'] = 'Entrez votre téléphone ou votre e-mail.';
        }
        if ($mdp === '' && $erreurs === []) {
            return $this->page($saisie, 'Entrez votre mot de passe.');
        }
        if ($erreurs !== []) {
            return $this->page($saisie, null, $erreurs);
        }
        $r = AuthService::depuisBase()->connecter($saisie['identifiant'], $mdp, $requete->ip());
        if ($r['ok']) {
            return $this->ouvrir($r['profil'], $r['id'], $saisie['suivant']);
        }
        if ($r['erreur'] === 'identifiant') {
            return $this->page($saisie, null, ['identifiant' => 'Ce n’est ni un e-mail, ni un téléphone à 10 chiffres.']);
        }
        if ($r['erreur'] === 'bloque') {
            $minutes = max(1, (int) ceil(($r['jusqua']->getTimestamp() - time()) / 60)); // texte à valider : non dessiné dans Figma
            return $this->page($saisie, 'Trop d’essais. Réessayez dans ' . min($minutes, AuthService::FENETRE_MIN) . ' minute' . ($minutes > 1 ? 's' : '') . ', ou changez de mot de passe.');
        }
        $n = (int) $r['restant']; // RG-C2 : « Encore 4 essais avant un blocage de 15 minutes. »
        return $this->page($saisie, 'Ce mot de passe ne correspond pas. Encore ' . $n . ' essai' . ($n > 1 ? 's' : '') . ' avant un blocage de ' . AuthService::FENETRE_MIN . ' minutes.');
    }

    /** POST /deconnexion */
    public function deconnecter(Request $requete): Response
    {
        Session::detruire();
        return Response::redirection('/connexion');
    }

    /** GET /connexion/google */
    public function google(Request $requete): Response
    {
        if (!GoogleAuthService::actif()) {
            Session::flash('message', 'La connexion avec Google n’est pas disponible pour le moment.');
            return Response::redirection('/connexion');
        }
        $etat = bin2hex(random_bytes(16));
        Session::set('google_etat', $etat);
        Session::set('google_inscription', $requete->query('inscription') !== null); // « Continuer avec Google » de M1a : un compte inconnu s'inscrit
        return Response::redirection(GoogleAuthService::adresseAutorisation($etat));
    }

    /** GET /connexion/google/retour */
    public function googleRetour(Request $requete): Response
    {
        $attendu = (string) Session::get('google_etat', '');
        Session::oublier('google_etat');
        $code = (string) $requete->query('code', '');
        $profil = $attendu !== '' && hash_equals($attendu, (string) $requete->query('state', '')) && $code !== '' ? GoogleAuthService::profilDepuisCode($code) : null;
        $compte = $profil !== null ? AuthService::depuisBase()->connecterGoogle($profil['sub'], $profil['email']) : null;
        $inscription = (bool) Session::get('google_inscription', false);
        Session::oublier('google_inscription');
        if ($compte === null && $profil !== null && $inscription) { // M1a : Google a dit qui l'on est, il reste prénom, nom et téléphone à dire
            Session::set('google_profil', ['sub' => $profil['sub'], 'email' => $profil['email']]);
            return Response::redirection('/pro/inscription/compte');
        }
        if ($compte === null) {
            Session::flash('message', 'Ce compte Google n’est lié à aucun compte allomécano.'); // texte à valider : non dessiné dans Figma
            return Response::redirection('/connexion');
        }
        return $this->ouvrir($compte['profil'], $compte['id'], '');
    }

    private function ouvrir(string $profil, int $id, string $suivant): Response
    {
        Session::regenerer();
        Session::oublier('mecano_id');
        Session::oublier('admin_id');
        Session::set($profil === 'admin' ? 'admin_id' : 'mecano_id', $id);
        $prefixe = $profil === 'admin' ? '/admin' : '/pro';
        return Response::redirection($suivant !== '' && str_starts_with($suivant, $prefixe) ? $suivant : $prefixe);
    }

    /** Seulement un chemin de ce site (jamais une adresse extérieure). */
    private function suivant(string $valeur): string
    {
        return preg_match('#^/(?!/)[A-Za-z0-9/_\-?=&%.]*$#', $valeur) === 1 ? $valeur : '';
    }

    /** @param array<string, string> $saisie @param array<string, string> $erreurs */
    private function page(array $saisie, ?string $erreur, array $erreurs = []): Response
    {
        return Response::html(View::rendre('communs/connexion', [
            'titre' => 'Espace pro', 'sansPied' => true, 'sansEspacePro' => true, 'css' => ['css/pages/connexion.css'],
            'saisie' => $saisie, 'erreur' => $erreur, 'erreurs' => $erreurs, 'google' => GoogleAuthService::actif(),
        ], 'layouts/client'));
    }
}
