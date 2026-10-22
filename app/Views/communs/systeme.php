<?php
/** Page Système : vitrine des composants, à comparer avec la page Système du Figma (40:96). */
$couleurs = ['surface', 'surface-creuse', 'surface-elevee', 'encre', 'encre-2', 'filet', 'bord-champ', 'hachure', 'balise', 'balise-presse', 'dispo', 'dispo-texte', 'erreur', 'erreur-texte', 'socle', 'bitume', 'beton', 'panneau', 'gris-voyant', 'carte-sol', 'carte-route', 'carte-eau'];
$textes = [
    't-display-xl' => 'Display/XL', 't-display-l' => 'Display/L', 't-titre-1' => 'Titre/1', 't-titre-2' => 'Titre/2', 't-titre-3' => 'Titre/3',
    't-borne-xl' => 'Borne/XL', 't-borne-l' => 'Borne/L', 't-borne-heure' => 'Borne/Heure', 't-bouton' => 'Bouton',
    't-lead' => 'Corps/Lead', 't-base' => 'Corps/Base', 't-gras' => 'Corps/Gras', 't-petit' => 'Corps/Petit', 't-micro' => 'Corps/Micro',
    't-label' => 'Label', 't-citation' => 'Citation', 't-etiquette' => 'Mono/Étiquette', 't-donnee' => 'Mono/Donnée',
];
$icones = json_decode((string) file_get_contents(RACINE . '/scripts/icones.json'), true);
?>
<div class="systeme conteneur">
  <header class="rubrique systeme__entete">
    <span class="t-etiquette">Design system · page Système du Figma</span>
    <h1 class="t-display-l">Système</h1>
    <p class="t-lead t-secondaire">Chaque composant ici doit être identique au Figma. En cas d’écart, le Figma fait foi.</p>
    <div class="segment" role="group" aria-label="Thème">
      <button type="button" data-theme-choix="clair">Clair</button>
      <button type="button" data-theme-choix="sombre">Sombre</button>
      <button type="button" data-theme-choix="systeme">Système</button>
    </div>
  </header>

  <section class="rubrique">
    <span class="t-etiquette">Couleurs · variables Figma</span>
    <div class="systeme__nuancier">
      <?php foreach ($couleurs as $c): ?>
        <div class="systeme__nuance"><i style="background:var(--<?= e($c) ?>)"></i><span class="t-donnee"><?= e($c) ?></span></div>
      <?php endforeach ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Typographie · styles Mobile/* (Desktop/* au-delà de 1024 px)</span>
    <div class="pile-16">
      <?php foreach ($textes as $classe => $nom): ?>
        <div class="systeme__texte filet-bas"><span class="t-donnee t-secondaire"><?= e($nom) ?></span><span class="<?= e($classe) ?>"><?= str_starts_with($classe, 't-borne') ? '1,2' : 'Le mécano vérifié le plus proche' ?></span></div>
      <?php endforeach ?>
      <p><?= borne('2,4', 'km', 'xl') ?> &nbsp; <?= borne('5 000', 'FCFA', 'l') ?></p>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Boutons · Figma 53:148</span>
    <div class="systeme__grille">
      <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'hauteur' => 64]) ?>
      <?= composant('bouton', ['libelle' => 'Appeler Koffi', 'icone' => 'telephone', 'chargement' => 'Appel…', 'attrs' => ['data-demo-chargement' => '1']]) ?>
      <?= composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire']) ?>
      <?= composant('bouton', ['libelle' => 'Choisir ma commune', 'icone' => 'repere', 'type' => 'contour']) ?>
      <?= composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'recherche', 'desactive' => true]) ?>
      <?= composant('bouton', ['libelle' => 'Supprimer mon compte', 'type' => 'danger']) ?>
      <div class="sur-sombre systeme__sombre">
        <?= composant('bouton', ['libelle' => 'Décrocher', 'icone' => 'telephone', 'hauteur' => 64, 'plein' => true]) ?>
        <?= composant('bouton', ['libelle' => 'Refuser', 'icone' => 'fermer', 'type' => 'contour-beton', 'plein' => true]) ?>
      </div>
      <p><a class="bouton-lien" href="#"><?= icone('liste', 20) ?>Revoir la liste des mécanos</a></p>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Champs · Figma 53:230</span>
    <form class="systeme__grille" onsubmit="return false">
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'valeur' => 'Aminata', 'autocomplete' => 'given-name']) ?>
      <?= composant('champ', ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'aide' => 'Gardé sur ce téléphone seulement.']) ?>
      <?= composant('champ', ['nom' => 'telephone2', 'label' => 'Téléphone', 'type' => 'tel', 'valeur' => '07 07 12 34', 'erreur' => 'Il manque 2 chiffres : 10 chiffres après +225.']) ?>
      <?= composant('champ', ['nom' => 'mdp', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'new-password']) ?>
      <?= composant('champ', ['nom' => 'tarif', 'label' => 'Diagnostic dès', 'type' => 'number', 'suffixe' => 'FCFA', 'inputmode' => 'numeric']) ?>
      <?= composant('champ', ['nom' => 'precision', 'label' => 'Précisez si vous voulez (facultatif)', 'type' => 'textarea', 'exemple' => 'ex. ça fait clic quand je tourne la clé', 'max' => 300]) ?>
    </form>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Sélecteurs · Figma 54:293 · 54:391 · 54:467 · 54:451 · 54:499</span>
    <div class="systeme__grille">
      <div class="vehicules" role="radiogroup" aria-label="Votre véhicule">
        <?php foreach (['voiture' => 'Voiture', 'moto' => 'Moto', 'camion' => 'Camion'] as $code => $libelle): ?>
          <label class="tuile-vehicule"><input type="radio" name="vehicule" value="<?= $code ?>" <?= $code === 'voiture' ? 'checked' : '' ?>><span><?= icone('vehicule-' . $code, 40) ?><?= e($libelle) ?></span></label>
        <?php endforeach ?>
      </div>
      <fieldset class="panneau-voyants" style="border:0;margin:0">
        <legend class="t-etiquette">Qu’est-ce qui s’allume&#8239;?</legend>
        <div class="voyants">
          <?php foreach (['ne-demarre-pas' => 'Ne démarre pas', 'batterie' => 'Batterie', 'surchauffe' => 'Surchauffe', 'pneu-creve' => 'Pneu crevé', 'huile-fuite' => 'Huile ou fuite', 'freins' => 'Freins', 'choc-accident' => 'Choc ou accident', 'bruit-fumee' => 'Bruit ou fumée', 'je-ne-sais-pas' => 'Je ne sais pas'] as $code => $libelle): ?>
            <label class="tuile-voyant<?= $code === 'je-ne-sais-pas' ? ' tuile-voyant--large' : '' ?>"><input type="radio" name="panne" value="<?= $code ?>" <?= $code === 'batterie' ? 'checked' : '' ?>><span><?= icone('voyant-' . $code, 32) ?><?= e($libelle) ?></span></label>
          <?php endforeach ?>
        </div>
      </fieldset>
      <div class="lignes-choix">
        <label class="ligne-choix"><input type="checkbox" name="cgu">J’accepte les conditions d’utilisation</label>
        <label class="ligne-choix"><input type="radio" name="r" checked>Voiture</label>
        <label class="ligne-choix"><input type="radio" name="r">Moto</label>
      </div>
      <div class="etiquettes-choix">
        <?php foreach (['Électricité auto', 'Batterie', 'Démarreur', 'Alternateur', 'Freins'] as $i => $s): ?>
          <label class="etiquette-choix"><input type="checkbox" <?= $i < 2 ? 'checked' : '' ?>><span><?= e($s) ?></span></label>
        <?php endforeach ?>
      </div>
      <div class="segment" role="group" aria-label="Affichage"><button type="button" aria-pressed="true"><?= icone('carte', 20) ?>Carte</button><button type="button" aria-pressed="false"><?= icone('liste', 20) ?>Liste</button></div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Signes · voyant (49:144) · poinçon (49:166) · statut (53:406)</span>
    <div class="systeme__ligne">
      <?php foreach (['disponible', 'indisponible', 'erreur', 'recherche'] as $v): ?><?= composant('voyant', ['etat' => $v]) ?><?php endforeach ?>
      <?php foreach ([14, 18, 22, 48] as $t): ?><?= composant('poincon', ['taille' => $t]) ?><?php endforeach ?>
      <span class="etiquette-verifie"><?= composant('poincon', ['taille' => 14]) ?>Vérifié</span>
    </div>
    <div class="systeme__ligne">
      <?php foreach (['disponible', 'indisponible', 'brouillon', 'en_attente', 'valide', 'a_reprendre', 'refuse', 'suspendu'] as $s): ?><?= composant('statut', ['statut' => $s]) ?><?php endforeach ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Notes · Figma 53:446 · toast 53:447</span>
    <div class="systeme__grille">
      <?= composant('note', ['type' => 'info', 'message' => 'Gardez les données mobiles activées.']) ?>
      <?= composant('note', ['type' => 'securite', 'message' => 'Garez-vous à l’abri, feux de détresse allumés, et restez derrière la glissière.']) ?>
      <?= composant('note', ['type' => 'erreur', 'message' => 'La recherche n’a pas abouti. Vérifiez le réseau.']) ?>
      <?= composant('note', ['type' => 'danger', 'message' => '<strong>Quelqu’un est blessé&#8239;?</strong> Appelez le 185 d’abord.', 'actions' => composant('bouton', ['libelle' => 'Appeler le 185', 'icone' => 'telephone', 'type' => 'contour', 'href' => 'tel:185'])]) ?>
      <p><button type="button" class="bouton bouton--contour" data-demo-toast><span class="bouton__libelle">Afficher un toast</span></button></p>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Icônes · sprite généré depuis le Figma (<?= count($icones['24']) + count($icones['32']) + count($icones['40']) ?>)</span>
    <div class="systeme__icones">
      <?php foreach (['24' => 24, '32' => 32, '40' => 40] as $groupe => $taille): foreach ($icones[$groupe] as $nom => $_): ?>
        <figure><?= icone($nom, $taille) ?><figcaption class="t-micro"><?= e($nom) ?></figcaption></figure>
      <?php endforeach; endforeach ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Barre de parcours · Figma 53:374</span>
    <?= composant('barre-parcours', ['etape' => 2, 'total' => 3, 'retour' => '#', 'annuler' => '#']) ?>
  </section>
</div>
<?= composant('socle', [
    'info' => 'Appel direct · le prix se discute avec lui',
    'actions' => composant('bouton', ['libelle' => 'Appeler Koffi', 'icone' => 'telephone']) . composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire']),
]) ?>
