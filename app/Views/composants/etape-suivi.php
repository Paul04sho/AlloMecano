<?php
/**
 * Étape du fil de suivi du dossier (Figma 110:907) : l'écrou de 16 et un filet vertical en tirets, puis la date en Mono, le titre en Titre/3 et une phrase.
 * @var string $etat      fait | en_cours | a_venir
 * @var string $date      « 20 octobre, 10:14 » (en cours : « 21 octobre, 08:30 · en cours » ; à venir : « À venir »)
 * @var string $titre
 * @var string $texte
 * @var ?string $echeance « Réponse avant le 22 octobre, 10:14 » (en cours)
 * @var bool $derniere    pas de filet sous la dernière étape
 */
$echeance ??= null;
$derniere ??= false;
?>
<li class="etape-suivi etape-suivi--<?= e(str_replace('_', '-', $etat)) ?>">
  <div class="etape-suivi__repere"><?= composant('ecrou-etape', ['etat' => $etat]) ?><?php if (!$derniere): ?><span class="etape-suivi__filet" aria-hidden="true"></span><?php endif ?></div>
  <div class="etape-suivi__texte">
    <p class="t-donnee t-secondaire"><?= e($date) ?></p>
    <p class="t-titre-3"><?= e($titre) ?></p>
    <p class="t-petit t-secondaire"><?= e($texte) ?></p>
    <?php if ($echeance !== null): ?><p class="etape-suivi__echeance t-etiquette"><?= e($echeance) ?></p><?php endif ?>
  </div>
</li>
