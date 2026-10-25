<?php
/**
 * Indicateur du tableau de bord (Figma 164:65) : un grand nombre en Borne XL, son libellé en Mono, et facultativement un voyant d'état.
 * @var string|int $valeur
 * @var ?string $unite      « % » (Mono, à côté du nombre)
 * @var string $libelle
 * @var ?string $etat       dispo | erreur : voyant et texte coloré sous le nombre (le libellé passe dans la ligne du voyant)
 */
$etat ??= null;
?>
<div class="indicateur">
  <p class="indicateur__valeur t-borne-xl"><?= e((string) $valeur) ?><?php if (!empty($unite)): ?><span class="t-etiquette"><?= e($unite) ?></span><?php endif ?></p>
  <?php if ($etat === null): ?>
    <p class="t-etiquette t-secondaire"><?= e($libelle) ?></p>
  <?php else: ?>
    <?php if (!empty($titre)): ?><p class="t-etiquette t-secondaire"><?= e($titre) ?></p><?php endif ?>
    <p class="indicateur__etat indicateur__etat--<?= e($etat) ?> t-etiquette"><?= composant('voyant', ['etat' => $etat === 'dispo' ? 'disponible' : 'erreur']) ?><span><?= e($libelle) ?></span></p>
  <?php endif ?>
</div>
