<?php
/**
 * Pied de page (Figma 56:636 mobile / 56:670 desktop) : bitume, logo, slogan, liens d'information,
 * mention de mise en relation, choix du thème (Clair · Sombre · Auto), copyright.
 * Les numéros d'urgence sont dans la section « Urgences » des pages (composant urgences).
 */
$theme = $_COOKIE['theme'] ?? null;
$actif = in_array($theme, ['clair', 'sombre'], true) ? $theme : 'systeme';
?>
<footer class="pied sur-sombre">
  <div class="pied__interieur">
    <div class="pied__haut">
      <div class="pied__marque">
        <a class="pied__logo" href="/" aria-label="allomécano, accueil"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a>
        <p class="pied__slogan t-titre-2">Le mécano vérifié le plus proche.</p>
      </div>
      <nav class="pied__liens" aria-label="Informations">
        <a href="/a-propos">À propos</a>
        <a href="/contact">Contact</a>
        <a href="/pro/devenir">Devenir mécano</a>
        <a href="/cgu">CGU</a>
        <a href="/confidentialite">Confidentialité</a>
      </nav>
    </div>
    <hr class="pied__filet">
    <div class="pied__bas">
      <p class="pied__mention t-petit">allomécano met en relation. Le prix se discute avec le mécanicien.</p>
      <div class="pied__theme" role="group" aria-label="Thème d’affichage">
        <?php foreach (['clair' => 'Clair', 'sombre' => 'Sombre', 'systeme' => 'Auto'] as $valeur => $libelle): ?>
          <button type="button" data-theme-choix="<?= e($valeur) ?>" aria-pressed="<?= $valeur === $actif ? 'true' : 'false' ?>"><?= e($libelle) ?></button>
        <?php endforeach ?>
      </div>
      <p class="pied__copyright t-etiquette">© <?= date('Y') ?> allomécano · Abidjan</p>
    </div>
  </div>
</footer>
