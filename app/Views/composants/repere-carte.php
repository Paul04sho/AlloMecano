<?php
/**
 * Repère carte S1 (Figma 49:32) : écrou-repère bitume, ancre = pointe.
 *   rang        W28, sans liseré : dans les lignes de résultat
 *   numerote    W36, liseré béton 2 px + ombre : marqueur Leaflet
 *   selectionne W56, visage du mécano dans le trou + étiquette « 1 · 1,2 KM »
 * @var string $type
 * @var int|string $rang
 * @var ?string $visage    URL du portrait WebP 80 × 80 (sélectionné)
 * @var ?string $etiquette « 1 · 1,2 KM » (sélectionné)
 * @var ?int $largeur   largeur en pixels si elle diffère du type (W44 pour le mécano suivant de C5c, 270:1862)
 */
$type ??= 'numerote';
$largeur = $largeur ?? ['rang' => 28, 'numerote' => 36, 'selectionne' => 56][$type] ?? 36;
// Le trou est un peu plus grand sur le sélectionné (visage de 38 sur 56) que sur les autres (17 sur 28)
$rayon = ['rang' => 8.5, 'numerote' => 8.17, 'selectionne' => 9.5][$type] ?? 8.17;
?>
<span class="repere repere--<?= e($type) ?>" style="--l:<?= $largeur ?>px" aria-hidden="true">
  <svg class="repere__ecrou" viewBox="0 0 28 40.815" width="<?= $largeur ?>" height="<?= round($largeur * 40.815 / 28, 2) ?>" overflow="visible">
    <path d="M13.51 0.14C13.8367 -0.0466667 14.1633 -0.0466667 14.49 0.14L27.51 7.66C27.8367 7.84667 28 8.13 28 8.51V23.54C28 23.92 27.8367 24.2067 27.51 24.4L18.04 29.96C17.72 30.1533 17.5 30.43 17.38 30.79L14.18 40.55C14.06 40.9033 13.94 40.9033 13.82 40.55L10.62 30.79C10.5 30.43 10.28 30.1533 9.96 29.96L0.49 24.4C0.163333 24.2067 0 23.92 0 23.54V8.51C0 8.13 0.163333 7.84667 0.49 7.66L13.51 0.14Z"/>
    <circle cx="14" cy="16.17" r="<?= $rayon ?>"/>
  </svg>
  <?php if ($type === 'selectionne'): ?>
    <?php if (!empty($visage)): ?><img class="repere__visage" src="<?= e($visage) ?>" width="38" height="38" alt=""><?php endif ?>
    <?php if (!empty($etiquette)): ?><span class="repere__etiquette t-etiquette"><?= e($etiquette) ?></span><?php endif ?>
  <?php else: ?>
    <span class="repere__rang"><?= e((string) $rang) ?></span>
  <?php endif ?>
</span>
