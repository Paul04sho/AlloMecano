<?php
/**
 * Fiche technique en bornes (Figma 76:1235 mobile, 158:856 panneau D1) : Borne/L (Archivo 900), libellé Mono, filets.
 * Les chiffres se comptent de 0 à la valeur en 520 ms (moment 5).
 * @var list<array{valeur: string, libelle: string, unite?: ?string, compte?: ?float}> $bornes
 * @var bool $unitesAParts  panneau D1 : l'unité (FCFA) se pose à côté du chiffre, au lieu d'ouvrir le libellé
 */
$unitesAParts ??= false;
?>
<div class="bornes-fiche<?= $unitesAParts ? ' bornes-fiche--panneau' : '' ?>" role="group" aria-label="Chiffres clés">
  <?php foreach ($bornes as $b): ?>
    <div class="bornes-fiche__borne">
      <p class="bornes-fiche__valeur t-borne-l"><span<?= isset($b['compte']) ? ' data-compte="' . e((string) $b['compte']) . '"' : '' ?>><?= e($b['valeur']) ?></span><?php if ($unitesAParts && !empty($b['unite'])): ?><span class="bornes-fiche__unite t-etiquette"><?= e($b['unite']) ?></span><?php endif ?></p>
      <p class="bornes-fiche__libelle t-etiquette t-secondaire"><?= !$unitesAParts && !empty($b['unite']) ? e($b['unite']) . ' · ' : '' ?><?= e($b['libelle']) ?></p>
    </div>
  <?php endforeach ?>
</div>
