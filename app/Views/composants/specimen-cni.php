<?php
/**
 * Spécimen CNI (Figma 56:705), ratio 1,586 : photo en niveaux de gris, lignes remplacées par des barres,
 * « SPÉCIMEN » en travers (plein encre, comme sur la maquette rendue). Ni drapeau, ni armoiries, ni texte inventé. Jamais de fausse carte générée.
 * Dessiné à 300 × 189 et mis à l'échelle par la largeur (186 sur mobile).
 * @var ?string $classe
 */
$barres = [[17, 17, 120, 10], [17, 33, 70, 6], [111, 55, 140, 8], [111, 71, 110, 8], [111, 91, 160, 8], [111, 107, 90, 8], [111, 127, 150, 8], [111, 143, 120, 8], [17, 165, 264, 8]];
?>
<svg class="specimen-cni<?= !empty($classe) ? ' ' . e($classe) : '' ?>" viewBox="0 0 300 189" role="img" aria-label="Spécimen de carte d’identité">
  <rect class="specimen-cni__fond" x=".5" y=".5" width="299" height="188" rx="2"/>
  <image href="<?= e(asset('img/accueil/specimen-portrait-160.webp')) ?>" x="17" y="51" width="78" height="98" preserveAspectRatio="xMidYMax slice"/>
  <?php foreach ($barres as [$x, $y, $l, $h]): ?><rect class="specimen-cni__barre" x="<?= $x ?>" y="<?= $y ?>" width="<?= $l ?>" height="<?= $h ?>" rx="2"/><?php endforeach ?>
  <text class="specimen-cni__mention" x="137" y="118" text-anchor="middle" dominant-baseline="middle" transform="rotate(-30 137 118)">SPÉCIMEN</text>
</svg>
