<?php
/**
 * « Vous êtes ici » S2 (Figma 49:130) : disque bitume Ø24 avec liseré béton, voyant jaune Ø11.
 * Le halo de précision (rayon = précision GPS réelle) est tracé par la carte (cercle Leaflet).
 * Recherche (C4b) : halo jaune 25 % scale 1 → 3, opacité .45 → 0, 1600 ms en boucle (classe --recherche).
 * @var bool $recherche
 */ ?>
<span class="vous-etes-ici<?= !empty($recherche) ? ' vous-etes-ici--recherche' : '' ?>" role="img" aria-label="Vous êtes ici">
  <span class="vous-etes-ici__radar" aria-hidden="true"></span>
  <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" overflow="visible">
    <circle class="vous-etes-ici__disque" cx="12" cy="12" r="10.8"/>
    <circle class="vous-etes-ici__voyant" cx="12" cy="12" r="5.5"/>
  </svg>
</span>
