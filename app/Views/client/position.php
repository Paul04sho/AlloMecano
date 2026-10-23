<?php
/**
 * C2a · Localisation GPS (Figma 66:437) et son état « recherche » (198:1963).
 * Le script demande la position, affiche la précision qui s'affine, puis envoie le point en POST (jamais dans l'URL).
 * @var bool $horsAbidjan
 * @var ?string $erreur
 */
use App\Core\Format;

$aide = 'Votre téléphone va demander l’autorisation : appuyez sur Autoriser';
?>
<div class="parcours-page position-gps" data-position-gps>
  <?= composant('barre-parcours', ['etape' => 1, 'total' => 3, 'retour' => '/', 'annuler' => '/']) ?>

  <div class="parcours-page__contenu">
    <h1 class="t-titre-1"><?= e(Format::typo('Où êtes-vous ?')) ?></h1>

    <div class="position-gps__figure" aria-hidden="true">
      <span class="position-gps__halo" data-halo></span>
      <?= composant('ecrou-repere', ['type' => 'figure', 'voyant' => false]) ?>
    </div>

    <p class="t-base">On cherche les mécanos autour de vous. Votre position sert seulement à cette recherche, on ne la garde pas.</p>

    <p class="position-gps__aide t-etiquette t-secondaire js-seul" data-aide><?= e(Format::typo($aide)) ?></p>
    <p class="position-gps__precision t-donnee t-secondaire" data-precision aria-live="polite" hidden></p>

    <div class="position-gps__note" data-note-refus <?= $erreur === null ? 'hidden' : '' ?>>
      <?= composant('note', ['type' => 'info', 'message' => $erreur !== null ? e($erreur) : '<strong>Localisation bloquée.</strong> ' . e(Format::typo('Pas grave : choisissez votre commune, ça marche aussi.'))]) ?>
    </div>
    <?php if ($horsAbidjan): ?>
      <div class="position-gps__note">
        <?= composant('note', ['type' => 'info', 'message' => e(Format::typo('Vous semblez être hors d’Abidjan : choisissez votre commune.'))]) ?>
      </div>
    <?php endif ?>
  </div>

  <form class="parcours-page__actions" method="post" action="/position" data-formulaire-gps>
    <?= csrf_champ() ?>
    <input type="hidden" name="lat" value="">
    <input type="hidden" name="lon" value="">
    <input type="hidden" name="precision" value="">
    <?php // Un seul jaune : si la localisation a échoué ou si on est hors d'Abidjan, « Choisir ma commune » prend la place ?>
    <?= composant('bouton', ['libelle' => 'Utiliser ma position', 'icone' => 'viseur', 'hauteur' => 64, 'plein' => true, 'chargement' => 'Localisation…',
        'type' => $horsAbidjan || $erreur !== null ? 'contour' : 'principal',
        'attrs' => ['data-utiliser-gps' => '', 'class' => 'js-seul']]) ?>
    <?= composant('bouton', ['libelle' => 'Choisir ma commune', 'icone' => 'repere', 'type' => $horsAbidjan || $erreur !== null ? 'principal' : 'contour', 'plein' => true,
        'href' => '/position/quartier', 'attrs' => ['data-choisir-commune' => '']]) ?>
  </form>
</div>
