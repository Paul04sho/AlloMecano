<?php
/**
 * Écrou de 16 du fil de suivi (Figma 110:909 · 110:919 · 110:931) : fait = plein, en cours = plein avec le voyant gris, à venir = contour.
 * @var string $etat  fait | en_cours | a_venir
 */
?>
<svg class="ecrou-etape ecrou-etape--<?= e(str_replace('_', '-', $etat)) ?>" width="16" height="19" viewBox="0 0 16 19" aria-hidden="true" focusable="false">
  <path class="ecrou-etape__ecrou" d="M8 0.4L15.4 4.7V13.8L8 18.1L0.6 13.8V4.7L8 0.4Z"/>
  <?php if ($etat === 'en_cours'): ?><circle class="ecrou-etape__voyant" cx="8" cy="9.25" r="3"/><?php endif ?>
</svg>
