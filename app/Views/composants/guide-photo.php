<?php
/**
 * Guide « Réussir votre photo » (Figma 102:624) : un bon exemple et trois contre-exemples en vignettes de 76 (coupe M), une étiquette en Mono dessous.
 * Les contre-exemples ne montrent aucune vraie personne : image réduite, image floutée, pictogramme.
 */
?>
<ul class="guide-photo" role="list">
  <li class="guide-photo__vignette">
    <span class="guide-photo__image guide-photo__image--bon"><img src="<?= e(asset('img/accueil/img-04-302.webp')) ?>" width="76" height="95" alt="" loading="lazy"></span>
    <?= icone('coche', 20) ?><span class="t-etiquette">Oui : de face, en plein jour</span>
  </li>
  <li class="guide-photo__vignette">
    <span class="guide-photo__image guide-photo__image--loin"><img src="<?= e(asset('img/accueil/img-02-302.webp')) ?>" width="36" height="45" alt="" loading="lazy"></span>
    <?= icone('fermer', 20) ?><span class="t-etiquette">Trop loin</span>
  </li>
  <li class="guide-photo__vignette">
    <span class="guide-photo__image guide-photo__image--floue"><img src="<?= e(asset('img/accueil/img-04-302.webp')) ?>" width="76" height="95" alt="" loading="lazy"></span>
    <?= icone('fermer', 20) ?><span class="t-etiquette">Floue</span>
  </li>
  <li class="guide-photo__vignette">
    <span class="guide-photo__image"><img src="<?= e(asset('img/pro/contre-exemple-lunettes.svg')) ?>" width="76" height="76" alt="" loading="lazy"></span>
    <?= icone('fermer', 20) ?><span class="t-etiquette">Lunettes de soleil</span>
  </li>
</ul>
