<?php

declare(strict_types=1);

/** Le layout client fournit déjà <main id="contenu"> : ne pas imbriquer un second <main>. */
$texte = static fn (string $valeur): string => e(fr_typo($valeur));
?>
<section class="position" aria-labelledby="position-titre">
  <?= composant('barre-parcours', [
      'etape' => 1,
      'total' => 3,
      'retour' => '/',
      'annuler' => '/',
  ]) ?>

  <div class="position__corps">
    <h1 id="position-titre" class="t-titre-1"><?= $texte('Où êtes-vous ?') ?></h1>

    <div class="position__illustration" aria-hidden="true">
      <div class="position__separateur"></div>
      <svg class="position__repere" viewBox="0 0 180 260" focusable="false" xmlns="http://www.w3.org/2000/svg">
        <path d="M90 0 180 52V160L90 260 0 160V52Z" fill="var(--encre)"/>
        <circle cx="90" cy="104" r="39" fill="var(--surface)"/>
      </svg>
    </div>

    <div class="position__explications">
      <p class="t-base position__texte-principal"><?= $texte('On cherche les mécanos autour de vous. Votre position sert seulement à cette recherche, on ne la garde pas.') ?></p>
      <p class="t-etiquette position__instruction"><?= $texte('Votre téléphone va demander l’autorisation : appuyez sur autoriser') ?></p>
      <p class="position__statut t-petit" data-position-statut role="status" aria-live="polite" hidden></p>
    </div>
  </div>

  <div class="position__actions" role="group" aria-label="<?= $texte('Actions de localisation') ?>">
    <?= composant('bouton', [
        'libelle' => 'Utiliser ma position',
        'icone' => 'viseur',
        'type' => 'principal',
        'hauteur' => 64,
        'plein' => true,
        'chargement' => 'Localisation…',
        'attrs' => ['id' => 'position-gps', 'data-position-gps' => ''],
    ]) ?>

    <?= composant('bouton', [
        'libelle' => 'Choisir ma commune',
        'icone' => 'repere',
        'type' => 'contour',
        'href' => '/position/quartier',
        'hauteur' => 56,
        'plein' => true,
    ]) ?>
  </div>
</section>
