<?php
/**
 * Indicateur de statistiques (Figma 180:751) : libellé en Mono, grand nombre en Borne XL avec son unité, ligne de repère en Mono, explication en Corps/Petit.
 * @var string $titre      « Délai moyen de validation »
 * @var string|int $valeur  « 19 » (ou « — » quand il n'y a rien à compter)
 * @var ?string $unite      « h », « % », « mécanos »
 * @var string $repere      « Objectif : moins de 48 h · 26 h le mois dernier »
 * @var string $explication
 */
?>
<div class="indicateur-stat">
  <p class="t-etiquette t-secondaire"><?= e($titre) ?></p>
  <p class="indicateur-stat__valeur t-borne-xl"><?= e((string) $valeur) ?><?php if (!empty($unite)): ?><span class="t-etiquette"><?= e($unite) ?></span><?php endif ?></p>
  <p class="t-etiquette"><?= e($repere) ?></p>
  <p class="t-petit t-secondaire"><?= e($explication) ?></p>
</div>
