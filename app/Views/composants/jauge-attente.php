<?php
/**
 * Jauge d'attente (Figma 164:99) : « ATTENTE 31 H / 48 » et huit segments, un par tranche de 6 h. Au-delà de l'alerte (36 h), les segments pleins passent en erreur.
 * @var array{heures: int, delai: int, segments: int, total: int, alerte: bool} $jauge
 * @var ?int $largeur   largeur en px (172 dans A0, 164 dans A1) ; pleine largeur si absente
 */
?>
<div class="jauge-attente<?= $jauge['alerte'] ? ' jauge-attente--alerte' : '' ?>"<?= !empty($largeur) ? ' style="width:' . (int) $largeur . 'px"' : '' ?>>
  <p class="t-etiquette">Attente <?= (int) $jauge['heures'] ?> h / <?= (int) $jauge['delai'] ?><?= $jauge['alerte'] ? '<span class="sr-only"> : délai bientôt dépassé</span>' : '' ?></p>
  <div class="jauge-attente__segments" aria-hidden="true">
    <?php for ($i = 1; $i <= $jauge['total']; $i++): ?><i<?= $i <= $jauge['segments'] ? ' class="plein"' : '' ?>></i><?php endfor ?>
  </div>
</div>
