<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminService;
use App\Services\SignalementAdminService;

/** A4 · Signalements (Figma 179:555) : liste par gravité, détail, classement sans suite. La sanction passe par la suspension d'A3. Réservé aux admins (ExigerAdmin). */
final class SignalementAdminController
{
    /** GET /admin/signalements?onglet=ouverts|classes&s=<signalement>&classer=1 */
    public function liste(Request $requete): Response
    {
        $service = SignalementAdminService::depuisBase();
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $onglet = $requete->query('onglet') === 'classes' ? 'classes' : 'ouverts';
        $liste = $service->liste($onglet, $maintenant);
        $choisi = (string) $requete->query('s', '');
        if (!in_array($choisi, array_column($liste, 'public_id'), true)) {
            $choisi = (string) ($liste[0]['public_id'] ?? '');
        }
        $detail = $choisi !== '' ? $service->detail($choisi, $maintenant) : null;
        if ($detail !== null && $detail['statut'] === 'nouveau') { // RG-S2 : lu, donc journalisé
            $service->marquerLu((int) Session::get('admin_id'), $choisi, $requete->ip());
        }
        return $this->page('admin/signalements', 'Signalements', [
            'onglet' => $onglet, 'liste' => $liste, 'choisi' => $choisi, 'detail' => $detail, 'decompte' => $service->decompte($maintenant), 'maintenant' => $maintenant,
            'dialogue' => $detail !== null && $detail['ouvert'] && $requete->query('classer') !== null ? 'classer' : null,
            'numero' => Session::flash('numero_client'), 'numeroRef' => Session::flash('numero_client_ref'),
        ], ['js/pages/admin-signalements.js']);
    }

    /** POST /admin/signalements/{id}/classer : « Classer sans suite », note obligatoire */
    public function classer(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        $r = SignalementAdminService::depuisBase()->classer((int) Session::get('admin_id'), $id, (string) $requete->input('note', ''), $requete->ip());
        Session::flash('message', $r['ok'] ? 'Signalement classé sans suite.' : $r['erreur']);
        return Response::redirection('/admin/signalements' . ($r['ok'] ? '' : '?s=' . rawurlencode($id) . '&classer=1'));
    }

    /** GET /admin/signalements/{id}/photo : la photo jointe par le client (RG-S3), consultation journalisée */
    public function photo(Request $requete): Response
    {
        $f = SignalementAdminService::depuisBase()->photo((int) Session::get('admin_id'), (string) $requete->param('id'), $requete->ip());
        $octets = $f !== null ? file_get_contents($f['chemin']) : false;
        if ($octets === false) {
            throw HttpException::introuvable();
        }
        return (new Response($octets, 200))->avecEntete('Content-Type', 'image/jpeg')->avecEntete('Content-Disposition', 'inline; filename="signalement.jpg"')
            ->avecEntete('Cache-Control', 'no-store, private')->avecEntete('X-Content-Type-Options', 'nosniff');
    }

    /** @param array<string, mixed> $donnees @param list<string> $js */
    private function page(string $vue, string $titre, array $donnees, array $js = []): Response
    {
        $admin = AdminService::depuisBase();
        $moi = $admin->admin((int) Session::get('admin_id'));
        return Response::html(View::rendre($vue, $donnees + [
            'titre' => $titre, 'actif' => 'signalements', 'compteurs' => $admin->compteurs(), 'admin' => $moi['nom_complet'] ?? '', 'sansBarre' => true, 'sansPied' => true,
            'css' => ['css/pages/admin.css', 'css/pages/admin-signalements.css'], 'js' => $js,
        ], 'layouts/admin'));
    }
}
