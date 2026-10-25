<?php
/**
 * Ligne d'engagement (Figma 92:225) : poinçon de 18, titre en Corps/Gras, explication en Corps/Petit, filet dessous.
 * @var string $titre
 * @var string $texte
 */
use App\Core\Format;

?>
<li class="ligne-engagement">
  <?= composant('poincon', ['taille' => 18]) ?>
  <div><p class="t-gras"><?= e($titre) ?></p><p class="t-petit t-secondaire"><?= e(Format::typo($texte)) ?></p></div>
</li>
