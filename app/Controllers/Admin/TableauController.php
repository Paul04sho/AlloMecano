<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AccueilService;
use App\Services\AdminService;
use App\Services\DossierService;

/** A0 · Tableau de bord (Figma 164:14) et A1 · File des dossiers (167:88). Réservé aux admins (ExigerAdmin). */
final class TableauController
{
    /** GET /admin */
    public function tableau(Request $requete): Response
    {
        $admin = AdminService::depuisBase();
        $dossiers = DossierService::depuisBase();
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this->page('admin/tableau-de-bord', 'tableau', 'Tableau de bord', [
            'maintenant' => $maintenant, 'indicateurs' => $admin->indicateurs($maintenant), 'anciens' => $dossiers->plusAnciens(5, $maintenant),
            'enAttente' => $dossiers->decompte()['en_attente'], 'communes' => AccueilService::depuisBase()->communes(), 'relations' => $admin->misesEnRelation($maintenant),
        ]);
    }

    /** GET /admin/dossiers?statut=&q=&ordre=&page= */
    public function dossiers(Request $requete): Response
    {
        $service = DossierService::depuisBase();
        $statut = (string) $requete->query('statut', 'en_attente');
        $statut = isset(DossierService::ONGLETS[$statut]) ? $statut : 'en_attente';
        $recherche = mb_substr(trim((string) $requete->query('q', '')), 0, 80);
        $ordre = $requete->query('ordre') === 'asc' ? 'asc' : 'desc';
        return $this->page('admin/dossiers', 'dossiers', 'File des dossiers', [
            'statut' => $statut, 'recherche' => $recherche, 'ordre' => $ordre, 'decompte' => $service->decompte(),
            'file' => $service->file($statut, $recherche, (int) $requete->query('page', 1), $ordre),
        ], ['js/pages/admin-dossiers.js']);
    }

    /** GET /admin/dossiers/{id}/portrait : la photo du mécano en 80 px pour la file (jamais une pièce d'identité) */
    public function portrait(Request $requete): Response
    {
        $f = AdminService::depuisBase()->portraitDossier((string) $requete->param('id'));
        $racine = realpath(dirname(__DIR__, 3) . '/storage/pieces');
        $chemin = $f !== null && $racine !== false ? realpath($racine . '/' . $f['fichier']) : false;
        if ($chemin === false || !str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR)) {
            throw HttpException::introuvable();
        }
        $img = match ($f['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($chemin),
            'image/png' => @imagecreatefrompng($chemin),
            'image/webp' => @imagecreatefromwebp($chemin),
            default => false,
        };
        if ($img === false) {
            throw HttpException::introuvable();
        }
        $l = imagesx($img);
        $h = imagesy($img);
        $cote = min($l, $h);
        $mini = imagecreatetruecolor(80, 80); // carré centré sur le haut du visage
        imagecopyresampled($mini, $img, 0, 0, intdiv($l - $cote, 2), (int) (($h - $cote) * 0.2), 80, 80, $cote, $cote);
        ob_start();
        imagejpeg($mini, null, 80);
        return (new Response((string) ob_get_clean(), 200))->avecEntete('Content-Type', 'image/jpeg')->avecEntete('Cache-Control', 'private, max-age=300');
    }

    /**
     * @param array<string, mixed> $donnees
     * @param list<string> $js
     */
    private function page(string $vue, string $actif, string $titre, array $donnees, array $js = []): Response
    {
        $admin = AdminService::depuisBase();
        $moi = $admin->admin((int) Session::get('admin_id'));
        return Response::html(View::rendre($vue, $donnees + [
            'titre' => $titre, 'actif' => $actif, 'compteurs' => $admin->compteurs(), 'admin' => $moi['nom_complet'] ?? '', 'sansBarre' => true, 'sansPied' => true,
            'css' => ['css/pages/admin.css'], 'js' => $js,
        ], 'layouts/admin'));
    }
}
