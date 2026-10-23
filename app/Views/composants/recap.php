<?php
/**
 * Récapitulatif (C2b, Figma 68:660) : surface creuse, filet bitume de 4 à gauche, étiquette Mono + texte gras.
 * Mis à jour en direct par le script de page : aria-live.
 * @var string $etiquette « CE QU'ON DONNERA AU MÉCANO »
 * @var string $texte
 * @var array<string,string> $attrs
 */
$html = '';
foreach (array_filter($attrs ?? [], fn ($v) => $v !== null) as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<div class="recap"<?= $html ?>>
  <p class="t-etiquette t-secondaire"><?= e($etiquette) ?></p>
  <p class="t-gras recap__texte" aria-live="polite"><?= e($texte) ?></p>
</div>
