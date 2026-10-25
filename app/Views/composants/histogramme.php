<?php
/**
 * Histogramme sur 30 jours (Figma 164:308) : une barre par jour, la dernière (aujourd'hui, en cours) seulement en contour, une ligne de base.
 * Chaque barre porte son nombre dans un titre et pour les lecteurs d'écran.
 * @var list<array{jour: string, nombre: int}> $jours
 * @var string $resume   « 30 derniers jours : 1 462 mises en relation » (lu par les lecteurs d'écran)
 */
$max = max(1, ...array_column($jours, 'nombre') ?: [1]);
$dernier = count($jours) - 1;
?>
<div class="histogramme" role="img" aria-label="<?= e($resume) ?>">
  <?php foreach ($jours as $i => $j): ?>
    <i class="histogramme__barre<?= $i === $dernier ? ' histogramme__barre--en-cours' : '' ?>" style="height:<?= round(100 * $j['nombre'] / $max, 1) ?>%" title="<?= e($j['jour'] . ' : ' . $j['nombre']) ?>"></i>
  <?php endforeach ?>
</div>
