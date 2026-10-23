<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }
        session_name('am_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::enProduction(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $cle, mixed $defaut = null): mixed
    {
        return $_SESSION[$cle] ?? $defaut;
    }

    public static function set(string $cle, mixed $valeur): void
    {
        $_SESSION[$cle] = $valeur;
    }

    public static function oublier(string $cle): void
    {
        unset($_SESSION[$cle]);
    }

    /** Message affiché une seule fois (toast après redirection). */
    public static function flash(string $cle, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$cle] = $message;
            return null;
        }
        $valeur = $_SESSION['_flash'][$cle] ?? null;
        unset($_SESSION['_flash'][$cle]);
        return $valeur;
    }

    /** À appeler après une connexion réussie (anti fixation de session). */
    public static function regenerer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function detruire(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
