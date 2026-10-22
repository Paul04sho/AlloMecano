<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function jeton(): string
    {
        $jeton = Session::get('_csrf');
        if (!is_string($jeton)) {
            $jeton = bin2hex(random_bytes(32));
            Session::set('_csrf', $jeton);
        }
        return $jeton;
    }

    public static function valide(?string $jeton): bool
    {
        $attendu = Session::get('_csrf');
        return is_string($attendu) && is_string($jeton) && hash_equals($attendu, $jeton);
    }

    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::jeton(), ENT_QUOTES) . '">';
    }
}
