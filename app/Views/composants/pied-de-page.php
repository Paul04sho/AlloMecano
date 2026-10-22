<?php
/** Pied de page (Figma 56:636 / 56:670) : bitume, logo blanc, slogan, liens d'information, numéros d'urgence. */
use App\Services\Parametres;
?>
<footer class="pied sur-sombre">
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
    <p class="pied__urgence t-donnee">
      <span>Urgence&#8239;:</span>
      <a href="tel:<?= e(Parametres::texte('urgence.samu', '185')) ?>">SAMU <?= e(Parametres::texte('urgence.samu', '185')) ?></a>
      <a href="tel:<?= e(Parametres::texte('urgence.pompiers', '180')) ?>">Pompiers <?= e(Parametres::texte('urgence.pompiers', '180')) ?></a>
      <a href="tel:<?= e(Parametres::texte('urgence.police', '170')) ?>">Police <?= e(Parametres::texte('urgence.police', '170')) ?></a>
    </p>
    <p class="pied__mentions">allomécano met en relation. Le prix se discute directement avec le mécanicien. © <?= date('Y') ?> Dev Eyes · Abidjan</p>
  </div>
</footer>
