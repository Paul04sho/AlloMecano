<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gabarits PHP. Une vue peut définir des blocs (debut/fin) que le layout affiche avec bloc().
 *
 *   View::rendre('client/accueil', ['titre' => '…'], 'layouts/client');
 */
final class View
{
    private const DOSSIER = __DIR__ . '/../Views/';

    /** @var array<string, string> */
    private static array $blocs = [];
    /** @var list<string> */
    private static array $pile = [];

    /** @param array<string, mixed> $donnees */
    public static function rendre(string $vue, array $donnees = [], ?string $layout = null): string
    {
        $contenu = self::inclure($vue, $donnees);
        if ($layout === null) {
            return $contenu;
        }
        self::$blocs['contenu'] = $contenu;
        $html = self::inclure($layout, $donnees);
        self::$blocs = [];
        return $html;
    }

    /** @param array<string, mixed> $donnees */
    public static function inclure(string $vue, array $donnees = []): string
    {
        $fichier = self::DOSSIER . $vue . '.php';
        if (!is_file($fichier)) {
            throw new \RuntimeException("Vue introuvable : $vue");
        }
        extract($donnees, EXTR_SKIP);
        ob_start();
        try {
            require $fichier;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function debut(string $nom): void
    {
        self::$pile[] = $nom;
        ob_start();
    }

    public static function fin(): void
    {
        $nom = array_pop(self::$pile) ?? throw new \LogicException('fin() sans debut()');
        self::$blocs[$nom] = (string) ob_get_clean(); // un layout peut ré-envelopper « contenu »
    }

    public static function bloc(string $nom, string $defaut = ''): string
    {
        return self::$blocs[$nom] ?? $defaut;
    }
}
