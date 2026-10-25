<?php
/**
 * Interrupteur de disponibilité (Figma 54:641) : bloc pleine largeur sans coupe, on le touche. role=switch, aria-checked.
 * Indisponible : bitume, bouton principal « Me rendre disponible » (met aussi la position à jour). Disponible : vert, voyant à halo,
 * « Disponible depuis 07:32 », bouton contour « Passer indisponible ». Fondu croisé 200 ms, vibration 30 ms à chaque changement (JS).
 * @var string $etat       disponible | indisponible
 * @var ?string $depuis    « 07:32 » : heure de début de la session (RG-D1)
 * @var ?string $zone      « 10 km autour de Riviera 3 » : ce que voient les clients
 * @var bool $demo         exemple dessiné dans une page d'information : non interactif
 * @var string $bouton     principal | contour : le bouton « Me rendre disponible » est jaune sur M2, en contour sur M0
 * @var array<string, string> $attrs  attributs en plus sur le bouton (data-*)
 */
$demo ??= false;
$depuis ??= null;
$zone ??= null;
$bouton ??= 'principal';
$attrs ??= [];
$dispo = $etat === 'disponible';
$attrs += ['role' => 'switch', 'aria-checked' => $dispo ? 'true' : 'false', 'data-dispo' => ''];
?>
<div class="interrupteur-dispo sur-sombre interrupteur-dispo--<?= $dispo ? 'disponible' : 'indisponible' ?>"<?= $demo ? ' inert' : '' ?>>
  <p class="interrupteur-dispo__etat t-etiquette">
    <?php if ($dispo): ?><?= composant('voyant', ['etat' => 'disponible']) ?><span>Visible à <?= e($zone ?? '') ?></span><?php else: ?>Vous êtes indisponible<?php endif ?>
  </p>
  <p class="t-titre-2"><?= $dispo ? 'Disponible depuis ' . e($depuis ?? '') : 'Les clients ne vous voient pas.' ?></p>
  <?= composant('bouton', [
      'libelle' => $dispo ? 'Passer indisponible' : 'Me rendre disponible',
      'type' => $dispo || $bouton === 'contour' ? 'contour-beton' : 'principal', 'hauteur' => 56, 'plein' => true,
      'icone' => $dispo || $bouton === 'principal' ? 'viseur' : null, 'attrs' => $attrs,
  ]) ?>
</div>
