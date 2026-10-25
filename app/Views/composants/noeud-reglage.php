<?php
/**
 * Nœud d'un réglage (Figma 272:1814 · 272:1821 · 272:1836) : écrou de 28, fait = plein avec coche, en cours = plein avec voyant jaune, à faire = contour.
 * @var string $etat  done | now | todo
 */
?>
<svg class="noeud-reglage noeud-reglage--<?= e($etat) ?>" width="28" height="28" viewBox="0 0 28 28" aria-hidden="true" focusable="false">
  <?php if ($etat === 'todo'): ?>
    <path class="noeud-reglage__contour" d="M14 2.5L24 8.25V19.75L14 25.5L4 19.75V8.25L14 2.5Z"/>
  <?php else: ?>
    <path class="noeud-reglage__plein" d="M14 1.5L24.8 7.75V20.25L14 26.5L3.2 20.25V7.75L14 1.5Z"/>
    <?php if ($etat === 'done'): ?><path class="noeud-reglage__coche" d="M9 14.3L12.3 17.5L18.9 10.7"/><?php else: ?><circle class="noeud-reglage__voyant" cx="14" cy="14" r="4.4"/><?php endif ?>
  <?php endif ?>
</svg>
