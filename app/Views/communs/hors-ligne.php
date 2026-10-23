<?php
/**
 * X7 · Hors ligne (Figma 146:500) : « Internet est coupé, mais le téléphone marche. » Les 3 derniers mécanos appelés (gardés sur l'appareil),
 * un appel direct, les numéros d'urgence, une nouvelle tentative automatique. Servie par le service worker quand le réseau manque.
 */
use App\Core\Format;

$typo = fn (string $t): string => e(Format::typo($t));
?>
<article class="hors-ligne" data-hors-ligne>
  <div class="hors-ligne__corps">
    <p class="hors-ligne__etat t-etiquette t-secondaire"><?= composant('voyant', ['etat' => 'indisponible']) ?><span>Pas de réseau</span></p>
    <h1 class="t-titre-1"><?= $typo('Internet est coupé, mais le téléphone marche.') ?></h1>
    <p class="t-base t-secondaire"><?= $typo('Un appel passe sans données. Voici les mécanos que vous avez consultés.') ?></p>

    <section class="hors-ligne__liste" data-derniers hidden aria-labelledby="derniers-titre">
      <h2 class="t-etiquette t-secondaire" id="derniers-titre" data-derniers-titre>Vos 3 derniers mécanos</h2>
      <ul role="list" data-derniers-lignes></ul>
      <button type="button" class="lien t-gras hors-ligne__effacer" data-effacer>Effacer cet historique</button>
      <a class="bouton bouton--principal bouton--64 bouton--plein" data-appeler-premier href="#"><?= icone('telephone') ?><span class="bouton__libelle" data-appeler-libelle>Appeler</span></a>
      <p class="t-petit t-secondaire">L’appel passe par votre réseau téléphonique, sans internet.</p>
    </section>
    <p class="t-base hors-ligne__vide" data-derniers-vide>Aucun mécano gardé sur cet appareil pour l’instant : dès que le réseau revient, la recherche se relance toute seule.</p>
  </div>

  <?= composant('urgences') ?>
  <p class="hors-ligne__essai t-etiquette t-secondaire" aria-live="polite"><span>Nouvelle tentative automatique…</span><?= composant('voyant', ['etat' => 'recherche']) ?></p>
</article>

<template data-modele-ligne>
  <li class="hors-ligne__ligne">
    <span class="hors-ligne__portrait"><img alt="" width="52" height="52" hidden><?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?></span>
    <span class="hors-ligne__texte"><span class="t-titre-3" data-nom></span><span class="t-donnee" data-telephone></span><span class="t-petit t-secondaire">Vu à <span class="t-donnee" data-heure></span> · peut-être plus disponible</span></span>
    <a class="bouton-icone bouton-icone--contour" data-appel href="#"><?= icone('telephone') ?><span class="sr-only" data-appel-nom></span></a>
  </li>
</template>
