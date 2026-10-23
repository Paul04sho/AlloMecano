<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class PageController
{
    /** C1 · Accueil client mobile-first et D0 · variante desktop responsive. */
    public function accueil(Request $requete): Response
    {
        return Response::html(View::rendre('client/accueil', [
            'titre' => 'En panne ? Le mécano vérifié le plus proche',
            'description' => 'En panne à Abidjan ? Trouvez un mécanicien vérifié et disponible près de vous, puis contactez-le directement.',
            'css' => ['css/pages/accueil.css'],
        ], 'layouts/client'));
    }
}
