<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminService;
use App\Services\ExamenDossierService;
use App\Services\FiligranePiece;

/** A2 · Examen d'un dossier (Figma 168:166) : visionneuse filigranée, pièce par pièce, décision. Réservé aux admins (ExigerAdmin). */
final class DossierController
{
    /** GET /admin/dossiers/{id} */
    public function voir(Request $requete): Response
    {
        $service = ExamenDossierService::depuisBase();
        $d = $service->dossier((string) $requete->param('id'));
        if ($d === null) {
            throw HttpException::introuvable();
        }
        $admin = AdminService::depuisBase();
        $moi = $admin->admin((int) Session::get('admin_id'));
        $service->ouvrir((int) $moi['id'], $d, $requete->ip());
        $d = $service->dossier($d['public_id']) ?? $d; // la consultation de la photo de profil et l'ouverture vont au journal pendant le chargement de la page
        return Response::html(View::rendre('admin/dossier', [
            'titre' => 'Dossier ' . $d['nom_complet'], 'actif' => 'dossiers', 'compteurs' => $admin->compteurs(), 'admin' => $moi['nom_complet'], 'sansBarre' => true, 'sansPied' => true,
            'css' => ['css/pages/admin.css', 'css/pages/admin-dossier.css'], 'js' => ['js/pages/admin-dossier.js'],
            'dossier' => $d, 'position' => $service->position($d['public_id']), 'journal' => $service->journal($d), 'filigrane' => $this->texteFiligrane($moi['filigrane']),
            'saisie' => ['piece' => $this->decisionsActuelles($d), 'motif' => $this->motifsActuels($d), 'precision' => []],
        ], 'layouts/admin'));
    }

    /** GET /admin/dossiers/{id}/pieces/{type} : la pièce avec le filigrane de l'admin ; la consultation est inscrite au journal (RG-P6) */
    public function piece(Request $requete): Response
    {
        $admin = AdminService::depuisBase()->admin((int) Session::get('admin_id'));
        $f = ExamenDossierService::depuisBase()->consulter((int) $admin['id'], (string) $requete->param('id'), (string) $requete->param('type'), $requete->ip());
        $jpeg = $f !== null ? FiligranePiece::rendre($f['chemin'], $this->texteFiligrane($admin['filigrane']), $requete->param('type') === 'portrait' ? 0.45 : 0.3) : null;
        if ($jpeg === null) {
            throw HttpException::introuvable();
        }
        return (new Response($jpeg, 200))->avecEntete('Content-Type', 'image/jpeg')->avecEntete('Content-Disposition', 'inline; filename="piece.jpg"')
            ->avecEntete('Cache-Control', 'no-store, private')->avecEntete('X-Content-Type-Options', 'nosniff');
    }

    /** GET /admin/dossiers/{id}/apercu?... : le message exact envoyé au mécano pour les pièces à reprendre (168:403) */
    public function apercu(Request $requete): Response
    {
        $d = ExamenDossierService::depuisBase()->dossier((string) $requete->param('id'));
        if ($d === null) {
            throw HttpException::introuvable();
        }
        $reprises = [];
        foreach ((array) $requete->query('piece', []) as $type => $v) {
            if ($v === 'a_reprendre' && isset(ExamenDossierService::NOMS[$type])) {
                $reprises[$type] = ['motif' => (string) ($requete->query('motif')[$type] ?? ''), 'precision' => (string) ($requete->query('precision')[$type] ?? '')];
            }
        }
        return Response::json(['message' => ExamenDossierService::message($d['prenom'], $reprises)]);
    }

    /** POST /admin/dossiers/{id}/decision */
    public function decider(Request $requete): Response
    {
        $id = (string) $requete->param('id');
        $r = ExamenDossierService::depuisBase()->decider((int) Session::get('admin_id'), $id, $requete->tout(), $requete->ip());
        if (!$r['ok']) {
            Session::flash('message', $r['erreur']);
            return Response::redirection('/admin/dossiers/' . rawurlencode($id));
        }
        Session::flash('message', ['valide' => 'Dossier validé : le poinçon apparaît sur la fiche.', 'a_reprendre' => 'Dossier renvoyé pour correction : le mécano est prévenu.', 'refuse' => 'Dossier refusé.'][$r['issue']]);
        return Response::redirection('/admin/dossiers');
    }

    private function texteFiligrane(string $nom): string
    {
        return 'ALLOMECANO - VERIFICATION - ' . strtoupper(Format::ascii($nom)) . ' - ' . gmdate('d/m - H:i');
    }

    /** @param array<string, mixed> $d @return array<string, string> */
    private function decisionsActuelles(array $d): array
    {
        $r = [];
        foreach ($d['pieces'] as $type => $p) {
            if ($p !== null && $p['statut'] === 'validee') {
                $r[$type] = 'conforme';
            } elseif ($p !== null && $p['statut'] === 'a_reprendre') {
                $r[$type] = 'a_reprendre';
            }
        }
        return $r;
    }

    /** @param array<string, mixed> $d @return array<string, string> */
    private function motifsActuels(array $d): array
    {
        $inverse = array_flip(ExamenDossierService::MOTIFS);
        $r = [];
        foreach ($d['pieces'] as $type => $p) {
            if ($p !== null && $p['statut'] === 'a_reprendre' && $p['motif'] !== null) {
                $r[$type] = $inverse[trim(explode(' · ', (string) $p['motif'])[0])] ?? 'autre';
            }
        }
        return $r;
    }
}
