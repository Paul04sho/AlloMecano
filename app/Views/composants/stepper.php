<?php
/**
 * Stepper (Figma 54:500) : boutons − et + de 56, valeur en Borne/XL avec son unité. Un vrai champ nombre : on peut aussi taper, et sans JavaScript il marche encore.
 * @var string $nom
 * @var int $valeur
 * @var int $min
 * @var int $max
 * @var string $unite      « ans »
 * @var string $libelle    ce qu'on compte, pour les lecteurs d'écran (« Années d'expérience »)
 */
$min ??= 0;
$max ??= 99;
?>
<div class="stepper" data-stepper>
  <button type="button" class="stepper__bouton" data-pas="-1" aria-label="Une année de moins" hidden><?= icone('moins') ?></button>
  <label class="stepper__valeur">
    <span class="sr-only"><?= e($libelle) ?></span>
    <input class="t-borne-xl" type="number" inputmode="numeric" name="<?= e($nom) ?>" value="<?= (int) $valeur ?>" min="<?= (int) $min ?>" max="<?= (int) $max ?>" step="1" data-stepper-champ>
    <span class="t-etiquette"><?= e($unite) ?></span>
  </label>
  <button type="button" class="stepper__bouton" data-pas="1" aria-label="Une année de plus" hidden><?= icone('plus') ?></button>
</div>
