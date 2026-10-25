<?php
/**
 * Interrupteur de disponibilité (Figma 54:641) : bloc pleine largeur sans coupe, on le touche. role=switch, aria-checked.
 * Indisponible : bitume, bouton principal « Me rendre disponible » (met aussi la position à jour). Disponible : vert, voyant à halo,
 * « Disponible depuis 07:32 », bouton contour « Passer indisponible ». Fondu croisé 200 ms, vibration 30 ms à chaque changement (JS).
 * Verrouillé (M2a, dossier en vérification) : creux et cadenas, « Disponible après validation », pas de bouton (RG-D1, RG-D6).
 * En intervention (RG-D4) : bitume, « Les clients ne vous voient plus. » et « Je suis libre » (pas dessiné dans Figma).
 * @var string $etat       disponible | indisponible | verrouille | intervention
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
if ($etat === 'verrouille'): ?>
<div class="interrupteur-dispo interrupteur-dispo--verrouille">
  <p class="interrupteur-dispo__etat t-etiquette"><?= icone('cadenas', 18) ?><span>Disponible après validation</span></p>
  <p class="t-titre-2">Les clients ne vous voient pas encore.</p>
</div>
<?php return; endif;
if ($etat === 'intervention'): // RG-D4 : « J'y vais » l'a masqué ; « Je suis libre » le remet en ligne, ou il l'est tout seul après 2 h ?>
<div class="interrupteur-dispo sur-sombre interrupteur-dispo--intervention" data-interrupteur>
  <p class="interrupteur-dispo__etat t-etiquette"><?= composant('voyant', ['etat' => 'indisponible']) ?><span>En intervention</span></p>
  <p class="t-titre-2">Les clients ne vous voient plus.</p>
  <?= composant('bouton', ['libelle' => 'Je suis libre', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'icone' => 'coche', 'chargement' => 'Un instant…', 'attrs' => ['data-libre' => '']]) ?>
</div>
<?php return; endif ?>
<div class="interrupteur-dispo sur-sombre interrupteur-dispo--<?= $dispo ? 'disponible' : 'indisponible' ?>"<?= $demo ? ' inert' : '' ?> data-interrupteur>
  <p class="interrupteur-dispo__etat t-etiquette">
    <?php if ($dispo): ?><?= composant('voyant', ['etat' => 'disponible']) ?><span>Visible à <?= e($zone ?? '') ?></span><?php else: ?>Vous êtes indisponible<?php endif ?>
  </p>
  <p class="t-titre-2"><?= $dispo ? 'Disponible depuis ' . e($depuis ?? '') : 'Les clients ne vous voient pas.' ?></p>
  <?= composant('bouton', [
      'libelle' => $dispo ? 'Passer indisponible' : 'Me rendre disponible',
      'type' => $dispo || $bouton === 'contour' ? 'contour-beton' : 'principal', 'hauteur' => 56, 'plein' => true,
      'icone' => !$dispo && $bouton === 'principal' ? 'viseur' : null, 'attrs' => $attrs,
      'chargement' => $dispo ? 'Passage en cours…' : 'Position en cours…',
  ]) ?>
</div>
