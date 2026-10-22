<?php
/**
 * Prépare la mise en ligne : concatène le CSS commun en un seul fichier (public/assets/build/commun.css),
 * en retirant commentaires et espaces inutiles. Le CSS des pages reste séparé (chargé seulement où il sert).
 * Usage : php scripts/construire-assets.php
 */
declare(strict_types=1);

use App\Core\Assets;

require dirname(__DIR__) . '/vendor/autoload.php';

$racine = dirname(__DIR__) . '/public/assets/';
$css = '';
foreach (Assets::CSS_COMMUN as $f) {
    $contenu = (string) file_get_contents($racine . $f);
    // les url() relatives de fonts.css pointent vers ../fonts : on garde le même niveau (build/ est à côté de css/)
    $css .= "/* $f */\n" . $contenu . "\n";
}
$min = preg_replace(['#/\*.*?\*/#s', '/\s+/', '/\s*([{};,>])\s*/', '/;}/'], ['', ' ', '$1', '}'], $css) ?? $css;
@mkdir($racine . 'build', 0775, true);
file_put_contents($racine . 'build/commun.css', trim($min) . "\n");
printf("build/commun.css : %d Ko (au lieu de %d Ko)\n", strlen($min) / 1024, strlen($css) / 1024);
