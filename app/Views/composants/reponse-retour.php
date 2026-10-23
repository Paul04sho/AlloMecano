<?php
/**
 * Réponse de « Avez-vous pu le joindre ? » (Figma 82:1413) : 64 de haut, bord 1,5 encre, libellé en gras, chevron.
 * @var string $contact public_id du contact
 * @var string $issue   arrive | pas_entendus | pas_reponse
 * @var string $libelle
 * @var bool $compacte  C5c « terminé » (270:1930) : 60 de haut, bord 2, libellé en 15
 */
?>
<form method="post" action="/contact/<?= e($contact) ?>/issue" class="reponse-retour__formulaire" data-reponse-retour>
  <?= csrf_champ() ?>
  <button type="submit" class="reponse-retour<?= !empty($compacte) ? ' reponse-retour--compacte' : '' ?>" name="issue" value="<?= e($issue) ?>"><span class="<?= !empty($compacte) ? 't-label' : 't-gras' ?>"><?= e($libelle) ?></span><?= icone('chevron', 20) ?></button>
</form>
