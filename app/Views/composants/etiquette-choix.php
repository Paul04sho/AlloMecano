<?php
/**
 * Étiquette de choix multiple (Figma 54:451) : 48, bord 1,5 encre, Archivo 700 / 15 ; sélectionnée = fond bitume et coche de 18.
 * Case native cachée : clavier et lecteur d'écran comme une case à cocher.
 * @var string $nom
 * @var string $valeur
 * @var string $libelle
 * @var bool $coche
 * @var array<string, string> $attrs  data-* en plus sur la case
 */
$attrs ??= [];
$html = '';
foreach ($attrs as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<label class="etiquette-choix"><input type="checkbox" name="<?= e($nom) ?>" value="<?= e($valeur) ?>"<?= !empty($coche) ? ' checked' : '' ?><?= $html ?>><span><?= icone('coche', 18) ?><?= e($libelle) ?></span></label>
