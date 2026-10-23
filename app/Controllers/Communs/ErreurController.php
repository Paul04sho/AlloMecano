<?php

declare(strict_types=1);

namespace App\Controllers\Communs;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** Pages d'erreur : X6 « Route barrée » (404) et variantes. Réponse JSON pour l'API. */
final class ErreurController
{
    public static function depuisException(Request $requete, int $statut, string $message = ''): Response
    {
        if ($requete->veutJson()) {
            return Response::json(['message' => $message !== '' ? $message : self::titre($statut)], $statut);
        }
        $html = View::rendre('communs/erreur', [
            'titre' => self::titre($statut),
            'statut' => $statut,
            'message' => $message,
            'css' => ['css/pages/erreur.css'],
        ], 'layouts/client');
        return Response::html($html, $statut);
    }

    private static function titre(int $statut): string
    {
        return match ($statut) {
            404 => 'Route barrée.',
            403 => 'Accès réservé.',
            405 => 'Route barrée.',
            419 => 'La page a expiré.',
            default => 'Panne de notre côté.',
        };
    }
}
