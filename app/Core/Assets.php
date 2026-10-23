<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Feuilles de style et scripts. En dev : un fichier par composant (lisible).
 * En prod : un seul fichier par groupe, construit par scripts/construire-assets.php.
 */
final class Assets
{
    /** Ordre d'empilement du CSS commun à toutes les pages. */
    public const CSS_COMMUN = [
        'css/fonts.css',
        'css/tokens.css',
        'css/base.css',
        'css/composants/bouton.css',
        'css/composants/champ.css',
        'css/composants/signes.css',
        'css/composants/selecteurs.css',
        'css/composants/navigation.css',
        'css/composants/classement.css',
        'css/composants/blocs.css',
    ];

    public const JS_COMMUN = [
        'js/core/app.js',
    ];

    /** @param list<string> $enPlus feuilles propres à la page (css/pages/…) */
    public static function css(array $enPlus = []): string
    {
        $fichiers = Env::enProduction() && is_file(RACINE . '/public/assets/build/commun.css')
            ? ['build/commun.css']
            : self::CSS_COMMUN;
        $html = '';
        foreach ([...$fichiers, ...$enPlus] as $f) {
            $html .= '<link rel="stylesheet" href="' . e(asset($f)) . '">' . "\n";
        }
        return $html;
    }

    /** @param list<string> $enPlus modules propres à la page (js/pages/…) */
    public static function js(array $enPlus = []): string
    {
        $html = '';
        foreach ([...self::JS_COMMUN, ...$enPlus] as $f) {
            $html .= '<script type="module" src="' . e(asset($f)) . '"></script>' . "\n";
        }
        return $html;
    }
}
