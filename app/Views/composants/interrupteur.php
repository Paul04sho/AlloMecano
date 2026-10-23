<?php
/**
 * Interrupteur « Lien de ma position » (Figma 54:686) : 36 × 20, bitume, pastille béton à droite quand il est actif.
 * @var string $id
 * @var string $libelle
 * @var bool $actif
 * @var array<string, string> $attrs  data-* en plus
 */
$attrs ??= [];
$html = '';
foreach ($attrs as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<label class="interrupteur" for="<?= e($id) ?>">
  <input class="interrupteur__champ" type="checkbox" role="switch" id="<?= e($id) ?>"<?= !empty($actif) ? ' checked' : '' ?><?= $html ?>>
  <span class="interrupteur__piste" aria-hidden="true"></span>
  <span class="interrupteur__libelle"><?= e($libelle) ?></span>
</label>
