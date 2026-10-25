<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminComptesService;
use App\Services\AdminService;

/** A6 · Comptes de l’équipe admin et profil personnel. */
final class AdminComptesController
{
    public function liste(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        if (!$admin['est_principal']) {
            throw HttpException::interdit();
        }
        return $this->page('administrateurs', 'Administrateurs', [
            'liste' => AdminComptesService::depuisBase()->administrateurs($admin['id']),
            'message' => Session::flash('message'),
            'motDePasseTemporaire' => Session::flash('mot_de_passe_temporaire'),
        ], 'administrateurs');
    }

    public function creer(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $resultat = AdminComptesService::depuisBase()->creer($admin['id'], $requete->tout(), $requete->ip());
        if ($resultat['ok']) {
            Session::flash('message', 'Compte administrateur créé. Copiez le mot de passe temporaire ci-dessous.');
            Session::flash('mot_de_passe_temporaire', $resultat['mot_de_passe_temporaire']);
        } else {
            Session::flash('message', $this->messageErreur($resultat));
        }
        return Response::redirection('/admin/administrateurs');
    }

    public function modifier(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $resultat = AdminComptesService::depuisBase()->modifier($admin['id'], $requete->tout(), $requete->ip());
        Session::flash('message', $resultat['ok'] ? 'Compte administrateur mis à jour.' : $this->messageErreur($resultat));
        return Response::redirection('/admin/administrateurs');
    }

    public function changerActif(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $id = filter_var($requete->input('id', ''), FILTER_VALIDATE_INT);
        $valeur = (string) $requete->input('actif', '');
        $resultat = ($id === false || !in_array($valeur, ['0', '1'], true))
            ? ['ok' => false, 'erreur' => 'compte_introuvable']
            : AdminComptesService::depuisBase()->changerActif($admin['id'], (int) $id, $valeur === '1', $requete->ip());
        $message = $resultat['ok']
            ? ($valeur === '1' ? 'Accès réactivé.' : 'Accès désactivé.')
            : $this->messageErreur($resultat);
        Session::flash('message', $message);
        return Response::redirection('/admin/administrateurs');
    }

    public function reinitialiserMotDePasse(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $id = filter_var($requete->input('id', ''), FILTER_VALIDATE_INT);
        $resultat = $id === false
            ? ['ok' => false, 'erreur' => 'compte_introuvable']
            : AdminComptesService::depuisBase()->reinitialiserMotDePasse($admin['id'], (int) $id, $requete->ip());
        if ($resultat['ok']) {
            Session::flash('message', 'Mot de passe temporaire créé. Copiez-le ci-dessous et transmettez-le au membre de l’équipe.');
            Session::flash('mot_de_passe_temporaire', $resultat['mot_de_passe_temporaire']);
        } else {
            Session::flash('message', $this->messageErreur($resultat));
        }
        return Response::redirection('/admin/administrateurs');
    }

    public function monCompte(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $compte = AdminComptesService::depuisBase()->monCompte($admin['id']);
        if ($compte === null) {
            throw HttpException::interdit();
        }
        return $this->page('mon-compte', 'Mon compte', [
            'compte' => $compte, 'message' => Session::flash('message'),
        ], 'mon-compte');
    }

    public function enregistrerMonCompte(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $resultat = AdminComptesService::depuisBase()->modifierMonCompte($admin['id'], $requete->tout(), $requete->ip());
        Session::flash('message', $resultat['ok'] ? 'Votre compte a été mis à jour.' : $this->messageErreur($resultat));
        return Response::redirection('/admin/mon-compte');
    }

    public function motDePasse(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $compte = AdminComptesService::depuisBase()->monCompte($admin['id']);
        if ($compte === null) {
            throw HttpException::interdit();
        }
        return $this->page('mot-de-passe-admin', 'Mot de passe', [
            'force' => $admin['mot_de_passe_a_changer'], 'aUnMotDePasse' => $compte['a_un_mot_de_passe'], 'message' => Session::flash('message'),
        ], 'mon-compte');
    }

    public function enregistrerMotDePasse(Request $requete): Response
    {
        $admin = $this->adminConnecte();
        $resultat = AdminComptesService::depuisBase()->changerMotDePasse(
            $admin['id'],
            (string) $requete->input('mot_de_passe_actuel', ''),
            (string) $requete->input('mot_de_passe', ''),
            (string) $requete->input('confirmation', ''),
            $requete->ip()
        );
        Session::flash('message', $resultat['ok'] ? 'Votre mot de passe a été modifié.' : $this->messageErreur($resultat));
        if ($resultat['ok']) {
            return Response::redirection('/admin/mon-compte');
        }
        return Response::redirection('/admin/mon-compte/mot-de-passe');
    }

    /** @param array<string, mixed> $donnees */
    private function page(string $vue, string $titre, array $donnees, string $actif): Response
    {
        $admin = $this->adminConnecte();
        $service = AdminService::depuisBase();
        return Response::html(View::rendre('admin/' . $vue, $donnees + [
            'titre' => $titre, 'actif' => $actif, 'compteurs' => $service->compteurs(), 'admin' => $admin['nom_complet'],
            'sansBarre' => true, 'sansPied' => true, 'css' => ['css/pages/admin.css', 'css/pages/admin-comptes-admin.css'],
        ], 'layouts/admin'))->avecEntete('Cache-Control', 'no-store');
    }

    /** @return array{id: int, prenom: string, nom: string, nom_complet: string, filigrane: string, est_principal: bool, mot_de_passe_a_changer: bool} */
    private function adminConnecte(): array
    {
        $id = Session::get('admin_id');
        $admin = is_int($id) ? AdminService::depuisBase()->admin($id) : null;
        if ($admin === null) {
            throw HttpException::interdit();
        }
        return $admin;
    }

    /** @param array{ok: bool, erreur?: string, erreurs?: array<string, string>} $resultat */
    private function messageErreur(array $resultat): string
    {
        if (isset($resultat['erreurs']) && $resultat['erreurs'] !== []) {
            return (string) reset($resultat['erreurs']);
        }
        return match ($resultat['erreur'] ?? '') {
            'permission' => 'Seul le compte principal peut gérer les autres comptes.',
            'email_utilise' => 'Cette adresse e-mail est déjà liée à un compte.',
            'confirmation' => 'Les deux nouveaux mots de passe ne correspondent pas.',
            'mot_de_passe_actuel' => 'Le mot de passe actuel ne correspond pas.',
            'mot_de_passe_identique' => 'Choisissez un mot de passe différent de l’actuel.',
            'compte_interdit' => 'Cette action ne peut pas être faite sur votre propre compte.',
            default => 'Cette action n’a pas pu être effectuée. Vérifiez les informations puis réessayez.',
        };
    }
}
