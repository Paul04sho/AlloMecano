<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AdminService;
use App\Services\JournalAdminService;
use App\Services\StatistiquesService;

/** A5 · Statistiques (Figma 180:631) : entonnoir de la recherche à l'avis, trois indicateurs, export CSV. Réservé aux admins (ExigerAdmin). */
final class StatistiquesController
{
    /** GET /admin/statistiques?periode=7|30 */
    public function voir(Request $requete): Response
    {
        $admin = AdminService::depuisBase();
        $moi = $admin->admin((int) Session::get('admin_id'));
        return Response::html(View::rendre('admin/statistiques', [
            'stats' => StatistiquesService::depuisBase()->tout(StatistiquesService::periode($requete->query('periode', 30))),
            'titre' => 'Statistiques', 'actif' => 'statistiques', 'compteurs' => $admin->compteurs(), 'admin' => $moi['nom_complet'] ?? '', 'sansBarre' => true, 'sansPied' => true,
            'css' => ['css/pages/admin.css', 'css/pages/admin-statistiques.css'], 'js' => [],
        ], 'layouts/admin'));
    }

    /** GET /admin/statistiques/export?periode=7|30 : ce que l'écran montre, en CSV ; l'export est journalisé (RG-C5) */
    public function exporter(Request $requete): Response
    {
        $periode = StatistiquesService::periode($requete->query('periode', 30));
        $stats = StatistiquesService::depuisBase()->tout($periode);
        $admin = (int) Session::get('admin_id');
        JournalAdminService::depuisBase()->noter($admin, 'exporter_statistiques', 'admin', $admin, ['periode' => $periode], $requete->ip());
        return (new Response(StatistiquesService::csv($stats), 200))->avecEntete('Content-Type', 'text/csv; charset=utf-8')
            ->avecEntete('Content-Disposition', 'attachment; filename="statistiques-allomecano-' . $stats['au']->format('Y-m-d') . '-' . $periode . 'j.csv"')
            ->avecEntete('Cache-Control', 'no-store, private');
    }
}
