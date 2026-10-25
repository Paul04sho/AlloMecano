<?php
/**
 * Un tarif indicatif (Figma 120:1393) : le libellé à gauche, le montant en Borne/L avec « FCFA » à droite. « Inclus » quand le déplacement ne se paie pas.
 * @var string $libelle
 * @var ?int $montant
 * @var bool $inclus
 */
use App\Core\Format;

$inclus ??= false;
?>
<div class="ligne-tarif">
  <p class="t-base"><?= e($libelle) ?></p>
  <?php if ($inclus): ?><p class="ligne-tarif__borne"><span class="t-borne-l">Inclus</span></p>
  <?php elseif ($montant !== null): ?><p class="ligne-tarif__borne"><span class="t-borne-l"><?= e(Format::nombre($montant)) ?></span><span class="t-etiquette">FCFA</span></p>
  <?php else: ?><p class="t-petit t-secondaire">Pas indiqué</p><?php endif ?>
</div>
