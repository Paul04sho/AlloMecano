<?php
/**
 * En-tête d'une section de « Mon profil » (Figma 120:1195) : la rubrique en Mono/Étiquette et, à droite, le crayon (48 × 48, bord 1,5 px) qui ouvre la modification.
 * @var string $libelle   « Photo et identité »
 * @var string $href      /pro/profil/identite
 * @var ?string $suite    complément du libellé : « · 1 / 3 »
 */
$suite ??= null;
?>
<div class="entete-section">
  <h2 class="t-etiquette t-secondaire"><?= e($libelle) ?><?= $suite !== null ? ' ' . e($suite) : '' ?></h2>
  <a class="entete-section__crayon" href="<?= e($href) ?>" aria-label="Modifier <?= e(mb_strtolower($libelle, 'UTF-8')) ?><?= $suite !== null ? ' ' . e($suite) : '' ?>"><?= icone('crayon', 24) ?></a>
</div>
