<?php
/**
 * Règle vivante (Figma 130:178) : une règle du mot de passe qui se coche au fur et à mesure de la saisie.
 * Case de 20 : vide (filet encre) ou faite (fond encre, coche béton). Annoncée aux lecteurs d'écran par aria-live sur la liste.
 * @var string $regle    clé lue par le JS : longueur | chiffre | identiques
 * @var string $libelle
 * @var bool $faite
 */
$faite ??= false;
?>
<li class="regle-vivante<?= $faite ? ' regle-vivante--faite' : '' ?>" data-regle="<?= e($regle) ?>">
  <span class="regle-vivante__case" aria-hidden="true"><?= icone('coche', 16) ?></span>
  <span class="t-petit"><?= e($libelle) ?></span>
  <span class="sr-only" data-regle-etat><?= $faite ? ' : fait' : ' : à faire' ?></span>
</li>
