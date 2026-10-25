<?php
/**
 * Commande de l'appel (Figma 269:1849) : tuile panneau 88 de haut, icône 24 et libellé en gras. Inversée (béton) quand elle est active,
 * comme « Micro coupé » (269:1891).
 * @var string $icone         nom dans le sprite
 * @var string $libelle
 * @var bool $actif           inversée
 * @var bool $desactive       indisponible sur ce téléphone
 * @var ?string $href         un lien au lieu d'un bouton (« Itinéraire »)
 * @var array<string, string> $attrs  data-* en plus
 */
$attrs ??= [];
$href ??= null;
$html = '';
foreach ($attrs as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<?php if ($href !== null): /* « Itinéraire » (M5, 271:1802) ouvre l'application de cartes : un lien, pas un interrupteur */ ?>
<a class="commande-appel" href="<?= e($href) ?>" target="_blank" rel="noopener"<?= $html ?>>
  <?= icone($icone) ?>
  <span class="t-label" data-libelle><?= e($libelle) ?></span>
</a>
<?php else: ?>
<button type="button" class="commande-appel<?= !empty($actif) ? ' commande-appel--active' : '' ?>"<?= !empty($desactive) ? ' disabled' : '' ?> aria-pressed="<?= !empty($actif) ? 'true' : 'false' ?>"<?= $html ?>>
  <?= icone($icone) ?>
  <span class="t-label" data-libelle><?= e($libelle) ?></span>
</button>
<?php endif ?>
