<?php
/**
 * Barre de répartition (Figma 118:1089) : « 5 ★ », une piste de 8 avec la barre bitume proportionnelle, le nombre d'avis à droite (gris quand il est nul).
 * @var int $etoiles   1 à 5
 * @var int $nombre
 * @var int $total     nombre d'avis publiés : la barre pleine est celle de tous les avis
 */
$part = $total > 0 ? round($nombre / $total * 100, 2) : 0;
?>
<div class="barre-etoiles<?= $nombre === 0 ? ' barre-etoiles--vide' : '' ?>">
  <span class="barre-etoiles__etoiles t-donnee"><span><?= (int) $etoiles ?></span><?= icone('etoile', 14) ?><span class="sr-only"> étoiles</span></span>
  <span class="barre-etoiles__piste" role="img" aria-label="<?= (int) $nombre ?> avis sur <?= (int) $total ?>"><?php if ($nombre > 0): ?><span class="barre-etoiles__barre" style="width:<?= $part ?>%"></span><?php endif ?></span>
  <span class="barre-etoiles__nombre t-donnee"><?= (int) $nombre ?></span>
</div>
