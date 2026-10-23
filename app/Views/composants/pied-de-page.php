<?php
/** Pied de page (Figma 56:636 / 56:670) : bitume, logo blanc, slogan, liens d'information, numéros d'urgence. */
use App\Services\Parametres;
?>
<footer class="pied sur-sombre filet-haut">
  <div class="pied__interieur">
    <a class="pied__logo" href="/" aria-label="allomécano, accueil"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a>
    <p class="t-titre-2">Le mécano vérifié le plus proche.</p>

    <nav class="pied__liens" aria-label="Informations">
      <a href="/a-propos">À propos</a>
      <a href="/contact">Contact</a>
      <a href="/pro/devenir">Devenir mécano</a>
      <a href="/cgu">CGU</a>
      <a href="/confidentialite">Confidentialité</a>
    </nav>

    <!-- LES BOUTONS DE THÈME REMPLACENT LES NUMÉROS D'URGENCE ICI -->
    <div class="pied__theme-selecteur" role="group" aria-label="Sélecteur de thème visuel">
      <button class="accueil__theme-btn<?= ($_COOKIE['theme'] ?? 'systeme') === 'clair' ? ' accueil__theme-btn--actif' : '' ?>" data-theme="clair" type="button">
        <span class="t-etiquette">Clair</span>
      </button>
      <button class="accueil__theme-btn<?= ($_COOKIE['theme'] ?? 'systeme') === 'sombre' ? ' accueil__theme-btn--actif' : '' ?>" data-theme="sombre" type="button">
        <span class="t-etiquette">Sombre</span>
      </button>
      <button class="accueil__theme-btn<?= !isset($_COOKIE['theme']) || $_COOKIE['theme'] === 'systeme' ? ' accueil__theme-btn--actif' : '' ?>" data-theme="systeme" type="button">
        <span class="t-etiquette">Système</span>
      </button>
    </div>

    <p class="pied__mentions">allomécano met en relation. Le prix se discute directement avec le mécanicien. <br> <br> © <?= date('Y') ?> Dev Eyes · Abidjan</p>
  </div>
</footer>
