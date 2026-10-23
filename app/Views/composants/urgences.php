<?php
/**
 * Urgences (C1 58:282 · D0 154:401) : surface creuse, titre, trois numéros en Borne soulignée.
 * Numéros lus dans la table parametres (urgence.samu, urgence.pompiers, urgence.police).
 */
use App\Core\Format;
use App\Services\Parametres;

$numeros = [
    'SAMU' => Parametres::texte('urgence.samu', '185'),
    'Pompiers' => Parametres::texte('urgence.pompiers', '180'),
    'Police' => Parametres::texte('urgence.police', '170'),
];
?>
<section class="urgences" aria-labelledby="urgences-titre">
  <h2 class="urgences__titre t-titre-3" id="urgences-titre"><?= e(Format::typo('Blessé ou en danger ? Appelez d’abord les secours.')) ?></h2>
  <ul class="urgences__numeros" role="list">
    <?php foreach ($numeros as $service => $numero): ?>
      <li><a href="tel:<?= e($numero) ?>"><span class="urgences__numero"><?= e($numero) ?></span><span class="t-etiquette"><?= e($service) ?></span></a></li>
    <?php endforeach ?>
  </ul>
</section>
