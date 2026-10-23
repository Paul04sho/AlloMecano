<?php
/**
 * Écrou-repère grand format (C2a) : l'écrou du logo, pointe en bas.
 *   figure (W96, Figma 66:457) : trou vide, posé sur le marquage ; suit l'encre (s'inverse en sombre)
 *   carte  (W44, Figma 67:585) : bitume, liseré béton 2 px + Ombre/repère, voyant allumé ; reste au centre
 *                                de la carte pendant qu'elle glisse dessous
 * @var string $type    figure | carte
 * @var bool $voyant    voyant allumé dans le trou (« On vous a trouvé »)
 * @var ?string $classe
 */
$type ??= 'figure';
$largeur = $type === 'carte' ? 44 : 96;
$voyant ??= $type === 'carte';
?>
<span class="ecrou-repere ecrou-repere--<?= e($type) ?><?= !empty($classe) ? ' ' . e($classe) : '' ?>" aria-hidden="true">
  <svg viewBox="0 0 96 141.335" width="<?= $largeur ?>" height="<?= round($largeur * 141.335 / 96, 2) ?>" overflow="visible">
    <path class="ecrou-repere__ecrou" d="M47.0301 0.559999C47.6303 0.21347 48.3697 0.213471 48.9699 0.56L95.0301 27.1528C95.6303 27.4993 96 28.1398 96 28.8328V82.4417C96 82.8921 95.8433 83.3285 95.5566 83.676L49.4965 139.521C48.7206 140.462 47.2794 140.462 46.5035 139.521L0.443353 83.676C0.156746 83.3285 0 82.8921 0 82.4417V28.8328C0 28.1398 0.369742 27.4993 0.969948 27.1528L47.0301 0.559999Z"/>
    <circle class="ecrou-repere__trou" cx="48" cy="55.4256" r="21"/>
    <circle class="ecrou-repere__voyant" cx="48" cy="55.4256" r="14.64"<?= $voyant ? '' : ' hidden' ?>/>
  </svg>
</span>
