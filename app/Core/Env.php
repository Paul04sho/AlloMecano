<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lit le fichier .env (clé=valeur, commentaires # autorisés) une seule fois.
 * Les variables d'environnement du serveur gardent la priorité.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $valeurs = [];

    public static function charger(string $fichier): void
    {
        if (!is_file($fichier)) {
            return;
        }
        foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '' || str_starts_with($ligne, '#') || !str_contains($ligne, '=')) {
                continue;
            }
            [$cle, $valeur] = array_map('trim', explode('=', $ligne, 2));
            $valeur = trim(preg_replace('/\s+#.*$/', '', $valeur) ?? '', "\"'");
            self::$valeurs[$cle] = $valeur;
        }
    }

    public static function get(string $cle, ?string $defaut = null): ?string
    {
        $serveur = getenv($cle);
        if ($serveur !== false && $serveur !== '') {
            return $serveur;
        }
        $valeur = self::$valeurs[$cle] ?? null;
        return ($valeur === null || $valeur === '') ? $defaut : $valeur;
    }

    public static function bool(string $cle, bool $defaut = false): bool
    {
        $valeur = self::get($cle);
        return $valeur === null ? $defaut : in_array(strtolower($valeur), ['1', 'true', 'oui', 'on'], true);
    }

    public static function enProduction(): bool
    {
        return self::get('APP_ENV', 'dev') === 'prod';
    }
}
