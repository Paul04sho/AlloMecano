<?php
// Génère public/assets/icones/sprite.svg depuis scripts/icones.json (tracés exportés du Figma, page Système).
// Usage : php scripts/generer-sprite.php
declare(strict_types=1);

$source = json_decode((string) file_get_contents(__DIR__ . '/icones.json'), true, flags: JSON_THROW_ON_ERROR);
$traits = ['24' => '2', '32' => '2.5', '40' => '2.25'];
$symboles = [];
foreach ($source as $taille => $icones) {
    foreach ($icones as $nom => $traces) {
        $chemins = '';
        foreach ($traces as $trace) {
            [$d, $type] = $trace + [1 => 0];
            // 0 = trait seul, 1 = trait + plein, 2 = plein seul
            $attrs = match ($type) { 1 => ' fill="currentColor"', 2 => ' fill="currentColor" stroke="none"', default => '' };
            $chemins .= "<path d=\"{$d}\"{$attrs}/>";
        }
        $symboles[] = sprintf(
            '<symbol id="i-%s" viewBox="0 0 %2$s %2$s" fill="none" stroke="currentColor" stroke-width="%3$s" stroke-linecap="square" stroke-linejoin="bevel">%4$s</symbol>',
            $nom, $taille, $traits[$taille], $chemins
        );
    }
}
// Logo et symbole (public/assets/img) : <use href="sprite.svg#i-logo"> garde currentColor (mode sombre)
foreach (['logo' => 'logo-allomecano.svg', 'symbole' => 'symbole.svg'] as $nom => $fichier) {
    $source = (string) file_get_contents(__DIR__ . '/../public/assets/img/' . $fichier);
    preg_match('/viewBox="([^"]+)"/', $source, $vb);
    $interieur = preg_replace(['/^.*?<svg[^>]*>/s', '/<\/svg>\s*$/s'], '', $source);
    $symboles[] = sprintf('<symbol id="i-%s" viewBox="%s">%s</symbol>', $nom, $vb[1], trim((string) $interieur));
}
$svg = '<svg xmlns="http://www.w3.org/2000/svg" style="display:none">' . "\n" . implode("\n", $symboles) . "\n</svg>\n";
file_put_contents(__DIR__ . '/../public/assets/icones/sprite.svg', $svg);
echo count($symboles) . " symboles écrits\n";
