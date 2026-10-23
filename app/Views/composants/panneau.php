<?php
/**
 * Panneau coupé L (Figma 67:619) : feuille surface posée sur la carte, coupe à 30° en haut à gauche,
 * Ombre/feuille vers le haut.
 * @var string $contenu HTML déjà échappé par l'appelant
 * @var ?string $classe
 * @var ?string $etiquette nom de la région pour les lecteurs d'écran
 */ ?>
<section class="panneau<?= !empty($classe) ? ' ' . e($classe) : '' ?>"<?= !empty($etiquette) ? ' aria-label="' . e($etiquette) . '"' : '' ?>>
  <div class="panneau__interieur"><?= $contenu ?></div>
</section>
