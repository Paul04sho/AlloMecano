<?php
/**
 * Barre d'un formulaire de fin de parcours (Figma 87:1577 « Votre avis », 89:1613 « Signalement ») : fermer ou retour, rubrique Mono au centre, lien d'action à droite.
 * @var string $icone     fermer | retour
 * @var string $retour    adresse du bouton
 * @var string $rubrique  « Votre avis »
 * @var ?string $action   libellé du lien à droite (« Plus tard », « Annuler »)
 * @var ?string $actionHref
 */
?>
<header class="barre-formulaire">
  <a class="bouton-icone" href="<?= e($retour) ?>" aria-label="<?= $icone === 'fermer' ? 'Fermer' : 'Retour' ?>"><?= icone($icone) ?></a>
  <p class="barre-formulaire__rubrique t-etiquette t-secondaire"><?= e($rubrique) ?></p>
  <?php if (!empty($action)): ?><a class="barre-formulaire__action lien t-gras" href="<?= e($actionHref ?? $retour) ?>"><?= e($action) ?></a><?php else: ?><span class="barre-formulaire__action"></span><?php endif ?>
</header>
