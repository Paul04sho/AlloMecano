<?php /** Barre client (Figma 56:322 / desktop 56:337) : logo à gauche, « Espace pro » à droite. Pas de menu burger. */ ?>
<header class="barre">
  <a class="barre__logo" href="/" aria-label="allomécano, accueil"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a>
  <nav class="barre__liens" aria-label="Navigation principale">
    <a href="/pro">Espace pro</a>
  </nav>
</header>
