<?php
/**
 * Carte (Fond de carte, Figma 56:736) : Leaflet + tuiles Protomaps servies par nous (PMTiles), couleurs carte/*
 * (claires ou sombres selon le thème). Le script de page l'initialise (js/composants/carte.js).
 * Charger aussi vendor/leaflet-1.9.4/leaflet.css, vendor/leaflet-1.9.4/leaflet.js et
 * vendor/protomaps-leaflet-5.1.0/protomaps-leaflet.js (Assets::css / Assets::js).
 * @var float $lat
 * @var float $lon
 * @var int $zoom
 * @var string $libelle   description pour les lecteurs d'écran
 * @var ?string $classe
 */
use App\Core\Env;

$source = (string) Env::get('CARTE_SOURCE', '/assets/carte/abidjan.pmtiles');
?>
<div class="carte<?= !empty($classe) ? ' ' . e($classe) : '' ?>" data-carte
     data-lat="<?= e(number_format((float) $lat, 6, '.', '')) ?>" data-lon="<?= e(number_format((float) $lon, 6, '.', '')) ?>"
     data-zoom="<?= (int) ($zoom ?? 16) ?>" data-source="<?= e($source) ?>" role="application" aria-label="<?= e($libelle) ?>">
  <span class="carte__attribution t-micro">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></span>
</div>
