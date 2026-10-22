<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Identifiants publics (RG-C8) : 26 caractères, triables dans le temps, impossibles à deviner.
 */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function generer(?int $millisecondes = null): string
    {
        $temps = $millisecondes ?? (int) floor(microtime(true) * 1000);
        $partieTemps = '';
        for ($i = 0; $i < 10; $i++) {
            $partieTemps = self::ALPHABET[$temps % 32] . $partieTemps;
            $temps = intdiv($temps, 32);
        }
        $aleatoire = '';
        foreach (str_split(random_bytes(16)) as $octet) {
            $aleatoire .= self::ALPHABET[ord($octet) % 32];
        }
        return $partieTemps . $aleatoire;
    }

    public static function estValide(string $valeur): bool
    {
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $valeur);
    }
}
