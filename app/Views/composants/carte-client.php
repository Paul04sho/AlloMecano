<?php
/**
 * Mini-carte de la position du client (Figma 271:1500 · 271:1662) : le fond de carte, le repère « A », le cercle de précision et la légende « Aminata · 1,2 km ».
 * Elle ne montre que ce que le client a choisi de partager (RG-A11) et rien après 24 h (RG-P1). Le script de page l'initialise (js/composants/carte.js).
 * Charger aussi leaflet.css, leaflet.js et protomaps-leaflet.js (voir le composant carte).
 * @var float $lat
 * @var float $lon
 * @var ?int $precision   mètres : rayon du cercle
 * @var string $legende   « Aminata · 1,2 km »
 * @var string $libelle   description pour les lecteurs d'écran
 */
?>
<div class="carte-client" data-carte-client data-precision="<?= (int) ($precision ?? 0) ?>">
  <?= composant('carte', ['lat' => $lat, 'lon' => $lon, 'zoom' => 17, 'libelle' => $libelle, 'classe' => 'carte-client__carte']) ?>
  <template data-repere><?= composant('repere-carte', ['type' => 'numerote', 'rang' => 'A']) ?></template>
  <span class="carte-client__legende t-etiquette"><?= e($legende) ?></span>
</div>
