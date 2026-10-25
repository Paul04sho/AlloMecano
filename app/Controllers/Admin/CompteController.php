<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminService;
use App\Services\CompteService;
use App\Services\SignalementAdminService;

/** A3 · Comptes (Figma 175:310) et suspension d'un compte (178:432). Réservé aux admins (ExigerAdmin). */
final class CompteController
{
    /** GET /admin/comptes?onglet=&q=&page=&compte=&suspendre=1&supprimer=1 */
    public function liste(Request $requete): Response
    {
        $service = CompteService::depuisBase();
        $onglet = $requete->query('onglet') === 'clients' ? 'clients' : 'mecaniciens';
        $recherche = mb_substr(trim((string) $requete->query('q', '')), 0, 80);
        $page = (int) $requete->query('page', 1);
        $liste = $onglet === 'clients' ? $service->clients($recherche, $page) : $service->mecaniciens($recherche, $page);
        $fiche = $onglet === 'mecaniciens' && $requete->query('compte') !== null ? $service->fiche((string) $requete->query('compte')) : null;
        return $this->page('admin/comptes', 'Comptes', [
            'onglet' => $onglet, 'recherche' => $recherche, 'liste' => $liste, 'decompte' => $service->decompte(), 'fiche' => $fiche,
            'dialogue' => $fiche !== null ? ($requete->query('suspendre') !== null ? 'suspendre' : ($requete->query('supprimer') !== null ? 'supprimer' : null)) : null,
            'numero' => Session::flash('numero_client'), 'numeroRef' => Session::flash('numero_client_ref'),
            'signalement' => preg_match('/^[0-9A-Z]{26}$/', (string) $requete->query('signalement', '')) === 1 ? (string) $requete->query('signalement') : null, // la suspension traite ce signalement (A4)
        ], ['js/pages/admin-comptes.js']);
    }

    /** GET /admin/comptes/{id}/apercu?motif=&duree= : le message exact de la suspension (178:689) */
    public function apercu(Request $requete): Response
    {
        $fiche = CompteService::depuisBase()->fiche((string) $requete->param('id'));
        if ($fiche === null) {
            throw HttpException::introuvable();
        }
        return Response::json(['message' => CompteService::messageSuspension($fiche['prenom'], (string) $requete->query('motif', ''), (string) $requete->query('duree', 'indefinie'))]);
    }

    /** POST /admin/comptes/{id}/suspendre */
    public function suspendre(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        $admin = (int) Session::get('admin_id');
        $motif = (string) $requete->input('motif', '');
        $signalement = (string) $requete->input('signalement', '');
        $r = CompteService::depuisBase()->suspendre($admin, $id, $motif, (string) $requete->input('duree', ''), $requete->ip());
        if ($r['ok'] && $signalement !== '' && SignalementAdminService::depuisBase()->sanctionner($admin, $signalement, trim($motif), $requete->ip())) { // RG-S2 : la sanction clôt le signalement
            Session::flash('message', 'Compte suspendu : le signalement est classé en sanction.');
            return Response::redirection('/admin/signalements');
        }
        Session::flash('message', $r['ok'] ? 'Compte suspendu : sa fiche a quitté les résultats.' : $r['erreur']);
        return Response::redirection('/admin/comptes?compte=' . rawurlencode($id) . ($r['ok'] ? '' : '&suspendre=1' . ($signalement !== '' ? '&signalement=' . rawurlencode($signalement) : '')));
    }

    /** POST /admin/comptes/{id}/lever */
    public function lever(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        $r = CompteService::depuisBase()->lever((int) Session::get('admin_id'), $id, $requete->ip());
        Session::flash('message', $r['ok'] ? 'Suspension levée : le mécanicien est prévenu.' : $r['erreur']);
        return Response::redirection('/admin/comptes?compte=' . rawurlencode($id));
    }

    /** POST /admin/comptes/{id}/supprimer */
    public function supprimer(Request $requete): Response
    {
        $r = CompteService::depuisBase()->supprimer((int) Session::get('admin_id'), (string) $requete->param('id'), $requete->ip());
        Session::flash('message', $r['ok'] ? 'Compte supprimé : ses données personnelles et ses pièces sont effacées.' : (string) $r['erreur']);
        return Response::redirection('/admin/comptes');
    }

    /** POST /admin/clients/{ref}/numero : l'affichage d'un numéro de client est journalisé (RG-C5) */
    public function numero(Request $requete): Response
    {
        $ref = (string) $requete->param('ref');
        $numero = CompteService::depuisBase()->afficherNumero((int) Session::get('admin_id'), $ref, $requete->ip());
        if ($numero === null) {
            Session::flash('message', 'Ce numéro est introuvable.');
        } else {
            Session::flash('numero_client', $numero);
            Session::flash('numero_client_ref', $ref);
        }
        $retour = (string) $requete->input('retour', '');
        if (str_starts_with($retour, '/admin/signalements')) { // l'affichage depuis un signalement (A4) y revient
            return Response::redirection($retour);
        }
        return Response::redirection('/admin/comptes?onglet=clients' . ($requete->input('q') ? '&q=' . rawurlencode((string) $requete->input('q')) : '') . ($requete->input('page') ? '&page=' . (int) $requete->input('page') : ''));
    }

    /** @param array<string, mixed> $donnees @param list<string> $js */
    private function page(string $vue, string $titre, array $donnees, array $js = []): Response
    {
        $admin = AdminService::depuisBase();
        $moi = $admin->admin((int) Session::get('admin_id'));
        return Response::html(View::rendre($vue, $donnees + [
            'titre' => $titre, 'actif' => 'comptes', 'compteurs' => $admin->compteurs(), 'admin' => $moi['nom_complet'] ?? '', 'sansBarre' => true, 'sansPied' => true,
            'css' => ['css/pages/admin.css', 'css/pages/admin-comptes.css'], 'js' => $js,
        ], 'layouts/admin'));
    }
}
