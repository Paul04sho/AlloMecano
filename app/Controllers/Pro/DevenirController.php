<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** M0 · Devenir mécano vérifié (Figma 92:14). Page publique : elle présente l'offre et mène à l'inscription (M1a). */
final class DevenirController
{
    /** GET /pro/devenir */
    public function page(Request $requete): Response
    {
        return Response::html(View::rendre('pro/devenir', [
            'titre' => 'Devenir mécano vérifié',
            'description' => 'Inscription gratuite, dossier vérifié sous 48 h. Les clients autour de vous vous appellent directement, quand vous le décidez.',
            'css' => ['css/pages/pro.css', 'css/pages/pro-devenir.css'],
        ], 'layouts/client'));
    }
}
