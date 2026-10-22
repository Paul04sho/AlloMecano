<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Toute requête qui modifie quelque chose doit porter le jeton CSRF :
 * champ caché _csrf (formulaires) ou en-tête X-CSRF (fetch côté JS, lu dans <meta name="csrf">).
 */
final class VerifierCsrf implements Middleware
{
    public function traiter(Request $requete, callable $suite): Response
    {
        if (in_array($requete->methode, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $jeton = $requete->entete('X-CSRF') ?? $requete->input('_csrf');
            if (!Csrf::valide(is_string($jeton) ? $jeton : null)) {
                throw new HttpException(419, 'La page a expiré. Rechargez-la et recommencez.');
            }
        }
        return $suite($requete);
    }
}
