<?php
/**
 * Onglets d'administration (Figma 179:607 · 180:683) : deux liens de même largeur dans un cadre de 1,5 px, l'actif sur fond inverse.
 * @var list<array{libelle: string, href: string, actif: bool}> $onglets
 * @var string $nom      nom du groupe pour les lecteurs d'écran
 * @var ?int $largeur    largeur en px (pleine largeur sinon)
 */
?>
<nav class="onglets-admin" aria-label="<?= e($nom) ?>"<?= !empty($largeur) ? ' style="width:' . (int) $largeur . 'px"' : '' ?>>
  <?php foreach ($onglets as $o): ?>
    <a href="<?= e($o['href']) ?>"<?= $o['actif'] ? ' aria-current="page"' : '' ?>><?= e($o['libelle']) ?></a>
  <?php endforeach ?>
</nav>
