<?php
/**
 * Barre client : mobile (Figma 56:322) logo 112 + lien « Espace pro », pas de burger ;
 * desktop ≥ 1024 (Figma 56:337) logo 148, trois liens, bouton contour « Espace pro ».
 * @var bool $pro  faux sur la connexion (X1, Figma 127:69) : on est déjà dans l'espace pro, le lien disparaît
 */
$pro ??= true; ?>
<header class="barre">
  <a class="barre__logo" href="/" aria-label="allomécano, accueil"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a>
  <nav class="barre__liens" aria-label="Navigation principale">
    <a class="barre__desktop" href="/#reconstitution">Comment ça marche</a>
    <a class="barre__desktop" href="/pro/devenir">Devenir mécano</a>
    <a class="barre__desktop" href="/a-propos">À propos</a>
    <?php if ($pro): ?><a class="barre__pro" href="/pro">Espace pro</a><?php endif ?>
  </nav>
</header>
