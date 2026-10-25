<?php
/**
 * Ligne d'action (Figma 110:966) : pictogramme de 24, libellé en Corps/Gras, chevron ; un filet dessous. Toute la ligne est le lien.
 * @var string $libelle
 * @var string $icone
 * @var string $href
 */
?>
<li class="ligne-action"><a href="<?= e($href) ?>"><?= icone($icone, 24) ?><span class="t-gras"><?= e($libelle) ?></span><?= icone('chevron', 20) ?></a></li>
