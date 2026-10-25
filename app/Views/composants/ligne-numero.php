<?php
/**
 * Un numéro de la fiche (Figma 120:1444) : pictogramme de 24, le moyen en Corps/Label et le numéro en Mono/Donnée.
 * @var string $icone     telephone | whatsapp
 * @var string $libelle   « Téléphone »
 * @var string $numero    « +225 07 07 12 34 56 »
 */
?>
<div class="ligne-numero"><?= icone($icone, 24) ?><div><p class="t-label"><?= e($libelle) ?></p><p class="t-donnee"><?= e($numero) ?></p></div></div>
