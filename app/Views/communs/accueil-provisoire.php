<?php /** Accueil provisoire du socle : le chantier A le remplace par C1 / D0. */ ?>
<section class="conteneur pile-24" style="padding-block:48px 72px">
  <span class="t-etiquette t-secondaire">Socle en place · écrans en cours</span>
  <h1 class="t-display-xl">Le mécano vérifié le plus proche.</h1>
  <p class="t-lead t-secondaire" style="max-width:34ch">En panne&#8239;? On vous montre les mécaniciens vérifiés autour de vous, triés par distance.</p>
  <div style="max-width:420px" class="pile-12">
    <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'href' => '/position', 'hauteur' => 64, 'plein' => true]) ?>
    <a class="bouton-lien" href="/systeme">Voir les composants du design system</a>
  </div>
</section>
