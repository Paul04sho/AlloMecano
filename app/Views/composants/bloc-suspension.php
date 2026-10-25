<?php
/**
 * Compte suspendu (Figma 113:967) : bloc bitume, filet erreur de 4 px à gauche, cadenas et « Compte suspendu depuis le 2 octobre », durée en Titre/2, motif cité tel que l'équipe l'a écrit (RG-D6).
 * @var string $depuis   « 2 octobre »
 * @var string $duree    « En pause pour 7 jours. »
 * @var string $motif
 * @var string $suite    « Jusqu'au 9 octobre, vous n'apparaissez plus dans les recherches. Votre compte se réactive tout seul ensuite. »
 */
?>
<section class="bloc-suspension sur-sombre" aria-labelledby="suspension-titre">
  <p class="bloc-suspension__rubrique t-etiquette"><?= icone('cadenas', 20) ?><span>Compte suspendu<br>depuis le <?= e($depuis) ?></span></p>
  <h1 class="t-titre-2" id="suspension-titre"><?= e($duree) ?></h1>
  <p class="bloc-suspension__etiquette t-etiquette">Motif</p>
  <p class="t-base"><?= e($motif) ?></p>
  <p class="t-base"><?= e($suite) ?></p>
</section>
