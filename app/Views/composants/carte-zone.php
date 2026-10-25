<?php
/**
 * Mini-carte de la zone (Figma 120:1260) : le fond de carte, le cercle du rayon et le repère « Votre atelier » avec le visage du mécano.
 * Le script de page l'initialise (js/pages/pro-profil.js). Charger aussi leaflet.css, leaflet.js et protomaps-leaflet.js (voir le composant carte).
 * @var float $lat
 * @var float $lon
 * @var int $rayon        kilomètres
 * @var ?string $visage   portrait de 38 dans le trou du repère
 */
$visage ??= null;
?>
<div class="carte-zone" data-carte-zone data-rayon="<?= (int) $rayon ?>">
  <?= composant('carte', ['lat' => $lat, 'lon' => $lon, 'zoom' => 12, 'libelle' => 'Votre zone : ' . (int) $rayon . ' km autour de votre atelier', 'classe' => 'carte-zone__carte']) ?>
  <template data-repere><?= composant('repere-carte', ['type' => 'selectionne', 'rang' => '', 'visage' => $visage, 'etiquette' => 'Votre atelier']) ?></template>
</div>
