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
    <span class="t-etiquette">Sélecteurs · Figma 54:293 · 54:391 · voyants de bord 48:214 (allumés) · 54:467 · 54:451 · 54:499</span>
    <div class="systeme__grille">
      <?= composant('choix-vehicule', ['nom' => 'vehicule', 'vehicules' => ['voiture' => 'Voiture', 'moto' => 'Moto', 'camion' => 'Camion'], 'valeur' => 'voiture', 'legende' => 'Votre véhicule']) ?>
      <?= composant('panneau-voyants', ['nom' => 'panne', 'valeur' => 'batterie', 'pannes' => array_map(fn ($c, $l) => ['code' => $c, 'libelle' => $l, 'danger' => $c === 'choc_accident'],
          ['ne_demarre_pas', 'batterie', 'surchauffe', 'pneu_creve', 'huile_fuite', 'freins', 'choc_accident', 'bruit_fumee', 'je_ne_sais_pas'],
          ['Ne démarre pas', 'Batterie', 'Surchauffe', 'Pneu crevé', 'Huile ou fuite', 'Freins', 'Choc ou accident', 'Bruit ou fumée', 'Je ne sais pas'])]) ?>
      <div class="systeme__ligne systeme__sombre">
        <?php foreach (['ne_demarre_pas', 'batterie', 'surchauffe', 'huile_fuite', 'pneu_creve', 'freins', 'choc_accident', 'bruit_fumee', 'je_ne_sais_pas'] as $c): ?><?= composant('voyant-de-bord', ['code' => $c, 'allume' => true]) ?><?php endforeach ?>
      </div>
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
      <?php foreach (['disponible', 'indisponible', 'plus_disponible', 'brouillon', 'en_attente', 'valide', 'a_reprendre', 'refuse', 'suspendu'] as $s): ?><?= composant('statut', ['statut' => $s]) ?><?php endforeach ?>
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
    <span class="t-etiquette">Repère carte · Figma 49:32 (rang · numéroté · sélectionné)</span>
    <div class="systeme__ligne systeme__reperes">
      <?= composant('repere-carte', ['type' => 'rang', 'rang' => 1]) ?>
      <?= composant('repere-carte', ['type' => 'numerote', 'rang' => 1]) ?>
      <?= composant('repere-carte', ['type' => 'selectionne', 'visage' => asset('img/accueil/koffi-rond-104.webp'), 'etiquette' => '1 · 1,2 km']) ?>
      <?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Ligne de classement · Figma 54:251 (compacte, avec et sans colonne borne)</span>
    <div class="systeme__grille">
      <?= composant('ligne-classement', ['href' => '#', 'nom' => 'Koffi Yao', 'portrait' => asset('img/accueil/koffi-rond-104.webp'), 'rang' => 1, 'distanceM' => 1200, 'specialites' => ['Électricité auto', 'Batterie', 'Démarreur'], 'specialitePanne' => 'Batterie', 'note' => 4.8, 'avis' => 37, 'quartier' => 'Riviera 3']) ?>
      <?= composant('ligne-classement', ['nom' => 'Adama Traoré', 'portrait' => asset('img/accueil/adama-rond-104.webp'), 'specialites' => ['Mécanique générale', 'Moto'], 'note' => 4.6, 'avis' => 21]) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Ligne de classement étendue · Figma 60:409 · autre rive · squelette (C4b 73:942)</span>
    <div class="systeme__grille">
      <?= composant('ligne-classement', ['format' => 'etendue', 'href' => '#', 'nom' => 'Koffi Yao', 'portrait' => ['src' => '/medias/demo/koffi-160.webp', 'srcset' => '/medias/demo/koffi-160.webp 160w, /medias/demo/koffi-360.webp 360w'], 'rang' => 1, 'distanceM' => 1200, 'specialites' => ['Électricité auto', 'Batterie', 'Démarreur'], 'specialitePanne' => 'Batterie', 'note' => 4.8, 'avis' => 37, 'quartier' => 'Riviera 3', 'rubrique' => 'Le plus proche', 'actions' => composant('bouton', ['libelle' => 'Appeler Koffi', 'icone' => 'telephone', 'plein' => true]) . composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire', 'hauteur' => 56])]) ?>
      <div>
        <?= composant('ligne-classement', ['href' => '#', 'nom' => 'Serge Kouamé', 'portrait' => '/medias/demo/serge-160.webp', 'rang' => 4, 'distanceM' => 3900, 'autreRive' => true, 'specialites' => ['Mécanique générale'], 'note' => 4.5, 'avis' => 12, 'quartier' => 'Treichville']) ?>
        <?= composant('ligne-classement', ['format' => 'squelette', 'largeurs' => [150, 214]]) ?>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Vous êtes ici · Figma 49:130 (repos · recherche C4b)</span>
    <div class="systeme__ligne systeme__reperes">
      <?= composant('vous-etes-ici') ?>
      <?= composant('vous-etes-ici', ['recherche' => true]) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Ligne commune · Figma 56:734 (disponibles · aucun · bientôt)</span>
    <div class="systeme__grille">
      <div class="filet-haut">
        <?= composant('ligne-commune', ['nom' => 'Cocody', 'slug' => 'cocody', 'etat' => 'disponibles', 'nombre' => 18]) ?>
        <?= composant('ligne-commune', ['nom' => 'Attécoubé', 'slug' => 'attecoube', 'etat' => 'aucun', 'nombre' => 0]) ?>
        <?= composant('ligne-commune', ['nom' => 'Songon', 'slug' => 'songon', 'etat' => 'bientot', 'nombre' => 0]) ?>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Photo et légende-repère · Figma 49:254 · Spécimen CNI · Figma 56:705</span>
    <div class="systeme__grille">
      <?= composant('photo', ['image' => 'img/accueil/img-01', 'largeurs' => [780, 1264], 'sizes' => '420px', 'largeur' => 1264, 'hauteur' => 848, 'alt' => 'À Adjamé, un mécanicien répare un taxi.', 'legende' => 'Adjamé · 10:12']) ?>
      <?= composant('photo', ['alt' => 'Photo à venir.', 'legende' => 'Marcory · 09:41', 'classe' => 'systeme__a-produire', 'aProduire' => ['titre' => 'Photo à produire · IMG-07', 'texte' => 'Emplacement réservé']]) ?>
      <?= composant('specimen-cni') ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Segment de formulaire · Figma 54:499 · Menu de panne · D0 154:92</span>
    <form class="systeme__grille" onsubmit="return false">
      <span class="champ__label" id="systeme-vehicule">Votre véhicule</span>
      <?= composant('segment', ['nom' => 'vehicule-demo', 'options' => ['voiture' => 'Voiture', 'moto' => 'Moto', 'camion' => 'Camion'], 'decritPar' => 'systeme-vehicule']) ?>
      <?= composant('menu-panne', ['nom' => 'panne-demo', 'label' => 'Votre panne', 'valeur' => 'batterie', 'pannes' => array_map(fn ($c, $l) => ['code' => $c, 'libelle' => $l, 'icone' => 'voyant-' . str_replace('_', '-', $c)], ['ne_demarre_pas', 'batterie', 'surchauffe', 'je_ne_sais_pas'], ['Ne démarre pas', 'Batterie', 'Surchauffe', 'Je ne sais pas'])]) ?>
    </form>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Urgences · C1 58:282 · D0 154:401 (numéros lus dans parametres)</span>
    <?= composant('urgences') ?>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Écrou-repère grand format · C2a 66:457 (figure) · 67:585 (carte) · bulle 67:588</span>
    <div class="systeme__ligne systeme__reperes">
      <?= composant('ecrou-repere', ['type' => 'figure']) ?>
      <?= composant('ecrou-repere', ['type' => 'figure', 'voyant' => true]) ?>
      <span class="systeme__carte-fond"><?= composant('ecrou-repere', ['type' => 'carte']) ?></span>
      <?= composant('bulle', ['texte' => 'Déplacez la carte pour ajuster']) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Ligne de choix · Figma 54:467 (radio · case · lien) · Récapitulatif · C2b 68:660</span>
    <div class="systeme__grille">
      <div class="lignes-choix" role="radiogroup" aria-label="Démonstration">
        <?= composant('ligne-choix', ['nom' => 'demo-quartier', 'valeur' => 'r2', 'libelle' => 'Riviera 2', 'coche' => true]) ?>
        <?= composant('ligne-choix', ['nom' => 'demo-quartier', 'valeur' => 'r3', 'libelle' => 'Riviera 3']) ?>
        <?= composant('ligne-choix', ['nom' => 'demo-cgu', 'valeur' => '1', 'type' => 'checkbox', 'libelle' => 'J’accepte les conditions d’utilisation']) ?>
        <a class="ligne-choix ligne-choix--lien" href="#"><span>Cocody</span><?= icone('chevron', 20) ?></a>
      </div>
      <?= composant('recap', ['etiquette' => 'Ce qu’on donnera au mécano', 'texte' => 'Riviera 2 · près de la pharmacie des Jardins']) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Fiche mécano · portrait 76:1212 · bandeau de preuve 76:1219 · bornes 76:1235 · statut « plus disponible » 78:1322</span>
    <div class="systeme__grille systeme__fiche">
      <div>
        <?= composant('portrait-fiche', ['photo' => ['src' => '/medias/demo/koffi-360.webp', 'srcset' => '/medias/demo/koffi-360.webp 360w, /medias/demo/koffi-604.webp 604w', 'couleur' => '#6f665b', 'cadrage' => '50% 30%'], 'nom' => 'Koffi Yao', 'mode' => 'plein', 'retour' => '#', 'puce' => '1,2 km · Riviera 3']) ?>
        <?= composant('bandeau-preuve', ['date' => '12 mars 2026']) ?>
      </div>
      <?= composant('bornes', ['bornes' => [['valeur' => '4,8', 'libelle' => '37 avis'], ['valeur' => '12', 'libelle' => 'ans de métier'], ['valeur' => '5 000', 'unite' => 'FCFA', 'libelle' => 'diagnostic dès']]]) ?>
      <?= composant('bornes', ['unitesAParts' => true, 'bornes' => [['valeur' => '4,8', 'libelle' => '37 avis'], ['valeur' => '12', 'libelle' => 'ans de métier'], ['valeur' => '5 000', 'unite' => 'FCFA', 'libelle' => 'diagnostic dès']]]) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Bloc « Ce qu’il faut lui dire » 54:675 · interrupteur 54:686 · avis 54:674 (étoiles bitume en clair, jaunes en sombre)</span>
    <div class="systeme__grille">
      <?= composant('bloc-message', ['idInterrupteur' => 'position-demo', 'message' => ['sans' => 'Bonjour Koffi, je vous contacte via allomécano. Je suis en panne à Riviera 2, près de la pharmacie des Jardins. Voiture, batterie.', 'position' => "Ma position\u{202F}: https://osm.org/?mlat=5.3571&mlon=-3.9672"]]) ?>
      <div>
        <?= composant('avis', ['note' => 5, 'commentaire' => 'Arrivé en 20 minutes, batterie changée sur place. Il a annoncé le prix avant de commencer.', 'prenom' => 'Aminata', 'panne' => 'Batterie', 'depuis' => 'il y a 3 jours', 'reponse' => 'Merci Aminata, bonne route !', 'prenomMecano' => 'Koffi']) ?>
        <?= composant('avis', ['note' => 4, 'commentaire' => 'Sérieux, ponctuel, et il connaît bien son métier.', 'prenom' => 'Un client', 'panne' => 'Démarreur', 'depuis' => 'il y a 2 semaines']) ?>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Contact · numéro révélé 159:858 (QR code maison, numéro fictif · branché en partie 6)</span>
    <div class="systeme__grille">
      <?= composant('contact-revele', ['prenom' => 'Koffi', 'telephone' => '+2250707123456', 'lienWhatsApp' => '#']) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Retour d’appel · réponse 82:1413 · conseils 86:1588 · lien à icône 84:1505 · bandeau « Avez-vous pu joindre ? » (RG-I4)</span>
    <div class="systeme__grille">
      <div class="retour__reponses">
        <?= composant('reponse-retour', ['contact' => '01JDEMOC00100000000000001', 'issue' => 'arrive', 'libelle' => 'Oui, il arrive']) ?>
        <?= composant('reponse-retour', ['contact' => '01JDEMOC00100000000000001', 'issue' => 'pas_entendus', 'libelle' => 'Oui, mais on ne s’est pas entendus']) ?>
        <?= composant('reponse-retour', ['contact' => '01JDEMOC00100000000000001', 'issue' => 'pas_reponse', 'libelle' => 'Non, pas de réponse']) ?>
      </div>
      <?= composant('conseils', ['conseils' => ['Convenez du prix avant qu’il commence.', 'Ne payez rien d’avance.', 'Restez visible, feux de détresse allumés.']]) ?>
      <div class="systeme__ligne">
        <button type="button" class="lien-icone t-gras"><?= icone('telephone', 20) ?><span>Réessayer Koffi</span></button>
        <a class="lien-icone t-gras" href="#"><?= icone('liste', 20) ?><span>Revoir la liste des mécanos</span></a>
      </div>
      <?= composant('bandeau-retour', ['public_id' => '01JDEMOC00100000000000001', 'prenom' => 'Koffi']) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Votre avis · barre de formulaire 87:1577 · étoiles de saisie 87:1600 · étiquette à cocher 87:1625</span>
    <div class="systeme__grille">
      <?= composant('barre-formulaire', ['icone' => 'fermer', 'retour' => '#', 'rubrique' => 'Votre avis', 'action' => 'Plus tard']) ?>
      <?= composant('etoiles-saisie', ['nom' => 'demo-note', 'valeur' => 4]) ?>
      <div class="etiquettes-choix">
        <label class="etiquette-choix etiquette-choix--44"><input type="checkbox" checked><span><?= icone('coche', 16) ?>Rapide</span></label>
        <label class="etiquette-choix etiquette-choix--44"><input type="checkbox"><span><?= icone('coche', 16) ?>Prix clair</span></label>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Signalement · photo facultative 210:1812 · fil de suivi 90:1681</span>
    <div class="systeme__grille">
      <?= composant('champ-photo', ['nom' => 'demo-photo', 'label' => 'Une photo (facultatif)', 'aide' => 'Allégée avant l’envoi. La position enregistrée dans la photo est retirée.']) ?>
      <?= composant('frise-suivi', ['etapes' => [
          ['etat' => 'fait', 'titre' => 'Signalement reçu', 'detail' => '21 octobre · 10:41'],
          ['etat' => 'en_cours', 'titre' => 'Lu par une personne de l’équipe', 'detail' => 'Sous 24 h · avant le 22 octobre, 10:41'],
          ['etat' => 'a_venir', 'titre' => 'On vous rappelle si besoin', 'detail' => 'Au +225 07 07 12 34 56'],
      ]]) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Mot de passe oublié · barre de parcours « MOT DE PASSE · 1 / 3 » 129:113 · terminé 134:204 · règles vivantes 130:178</span>
    <div class="systeme__grille">
      <?= composant('barre-parcours', ['etape' => 1, 'total' => 3, 'rubrique' => 'Mot de passe', 'retour' => '#', 'annuler' => '#']) ?>
      <?= composant('barre-parcours', ['etape' => 3, 'total' => 3, 'rubrique' => 'Mot de passe', 'termine' => true]) ?>
      <ul class="mdp__regles" role="list">
        <?= composant('regle-vivante', ['regle' => 'demo-1', 'libelle' => '8 caractères au moins', 'faite' => true]) ?>
        <?= composant('regle-vivante', ['regle' => 'demo-2', 'libelle' => 'Au moins 1 chiffre']) ?>
      </ul>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Administration · rail 56:470 · indicateur 164:65 · jauge d’attente 164:99 · pastilles de pièces 167:204 · barres par commune 164:238 · histogramme 164:308</span>
    <div class="systeme__grille systeme__admin">
      <div class="indicateurs">
        <?= composant('indicateur', ['valeur' => 12, 'libelle' => 'Dossiers en attente · le plus ancien : 31 h']) ?>
        <?= composant('indicateur', ['valeur' => 3, 'titre' => 'Signalements ouverts', 'libelle' => 'Dont 1 critique', 'etat' => 'erreur']) ?>
        <?= composant('indicateur', ['valeur' => 84, 'libelle' => 'Disponibles maintenant', 'etat' => 'dispo']) ?>
        <?= composant('indicateur', ['valeur' => 71, 'unite' => '%', 'libelle' => 'Clients qui ont pu joindre leur mécano (7 j)']) ?>
      </div>
      <?= composant('jauge-attente', ['jauge' => ['heures' => 31, 'delai' => 48, 'segments' => 5, 'total' => 8, 'alerte' => false], 'largeur' => 172]) ?>
      <?= composant('jauge-attente', ['jauge' => ['heures' => 40, 'delai' => 48, 'segments' => 6, 'total' => 8, 'alerte' => true], 'largeur' => 172]) ?>
      <?= composant('pastilles-pieces', ['pieces' => ['cni_recto' => 'envoyee', 'cni_verso' => 'envoyee', 'permis' => 'a_reprendre', 'diplome' => 'envoyee', 'portrait' => 'manquante', 'atelier' => 'facultative']]) ?>
      <?= composant('pastilles-pieces', ['pieces' => [], 'legende' => true]) ?>
      <?= composant('barres-communes', ['communes' => [['nom' => 'Cocody', 'etat' => 'disponibles', 'nombre' => 18], ['nom' => 'Yopougon', 'etat' => 'disponibles', 'nombre' => 14], ['nom' => 'Attécoubé', 'etat' => 'aucun', 'nombre' => 0], ['nom' => 'Songon', 'etat' => 'bientot', 'nombre' => 0]]]) ?>
      <?= composant('histogramme', ['jours' => array_map(static fn (int $i): array => ['jour' => 'J' . $i, 'nombre' => 30 + (int) (20 * sin($i))], range(1, 30)), 'resume' => 'Exemple de 30 jours']) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Examen d’un dossier · écrou dossier 50:89 · visionneuse 168:224 · ligne de pièce 168:290 · touche 168:508</span>
    <div class="systeme__grille systeme__admin">
      <div class="systeme__ligne">
        <?= composant('ecrou-dossier', ['pieces' => ['cni_recto' => 'envoyee', 'cni_verso' => 'envoyee', 'permis' => 'a_reprendre', 'diplome' => 'envoyee', 'portrait' => 'envoyee', 'atelier' => 'facultative'], 'taille' => 24]) ?>
        <?= composant('ecrou-dossier', ['pieces' => ['cni_recto' => 'envoyee', 'cni_verso' => 'envoyee', 'permis' => 'envoyee', 'diplome' => 'envoyee', 'portrait' => 'envoyee', 'atelier' => 'envoyee'], 'valide' => true, 'taille' => 96]) ?>
        <?= composant('ecrou-dossier', ['pieces' => ['cni_recto' => 'envoyee', 'cni_verso' => 'manquante', 'permis' => 'manquante', 'diplome' => 'manquante', 'portrait' => 'envoyee', 'atelier' => 'facultative'], 'taille' => 96]) ?>
      </div>
      <ul class="dossier__lignes" role="list">
        <?= composant('ligne-piece', ['type' => 'cni_recto', 'piece' => ['id' => 1], 'mecano' => '01JDEMO0000000000000000001', 'decision' => 'conforme', 'affichee' => true, 'etat' => 'Affichée']) ?>
        <?= composant('ligne-piece', ['type' => 'permis', 'piece' => ['id' => 2], 'mecano' => '01JDEMO0000000000000000001', 'decision' => 'a_reprendre', 'etat' => 'Consultée · 17:05']) ?>
        <?= composant('ligne-piece', ['type' => 'atelier', 'piece' => null, 'mecano' => '01JDEMO0000000000000000001', 'etat' => '']) ?>
      </ul>
      <span class="touche t-etiquette">J / K</span>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Espace pro · barre mécano 107:679 · onglets mécano 56:469 · interrupteur de disponibilité 54:641 · écrou légendé 92:154 · accordéon 92:251 · engagement 92:225</span>
    <div class="systeme__grille systeme__pro">
      <?= composant('barre-mecano', []) ?>
      <div class="systeme__onglets"><?= composant('onglets-mecano', ['actif' => 'statut', 'demandes' => 2]) ?></div>
      <div class="systeme__onglets"><?= composant('onglets-mecano', ['actif' => 'avis']) ?></div>
      <?= composant('interrupteur-dispo', ['etat' => 'indisponible', 'demo' => true]) ?>
      <?= composant('interrupteur-dispo', ['etat' => 'disponible', 'demo' => true, 'zone' => "10\u{00A0}km autour de Riviera 3", 'depuis' => '07:32']) ?>
      <?= composant('ecrou-legende', []) ?>
      <div>
        <?= composant('accordeon', ['question' => 'Combien ça coûte ?', 'reponse' => 'Rien. L’inscription est gratuite, et les appels des clients aussi.']) ?>
        <?= composant('accordeon', ['question' => 'Je n’ai pas de diplôme, je peux m’inscrire ?', 'reponse' => 'Oui. Une attestation signée par un patron d’atelier suffit.', 'ouvert' => true]) ?>
      </div>
      <ul class="devenir__engagements" role="list">
        <?= composant('ligne-engagement', ['titre' => 'La ponctualité', 'texte' => 'Vous dites quand vous arrivez. En retard, vous prévenez.']) ?>
      </ul>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Tableau de bord mécano · en-tête 107:695 · interrupteur verrouillé 110:947 · bornes du jour 107:738 · demande 107:759 · étape du suivi 110:907 · écrou 16 110:909 · verdict 112:930 · compte suspendu 113:967 · ligne d’action 110:966 · envoi d’une photo 215:1477</span>
    <div class="systeme__grille systeme__pro">
      <?= composant('en-tete-pro', ['prenom' => 'Koffi', 'ligne' => 'Électricité auto · Riviera 3', 'avatar' => null, 'verifie' => true]) ?>
      <?= composant('interrupteur-dispo', ['etat' => 'verrouille']) ?>
      <?= composant('bornes-jour', ['bornes' => [['valeur' => '4', 'unite' => 'Appels', 'libelle' => 'vous ont appelé'], ['valeur' => '18', 'unite' => 'Vues', 'libelle' => 'ont vu votre fiche'], ['valeur' => '4,8', 'unite' => 'sur 5', 'libelle' => '37 avis']]]) ?>
      <ul class="tableau__lignes" role="list">
        <?= composant('ligne-demande', ['demande' => ['heure' => '10:12', 'client' => 'Aminata', 'resume' => 'Batterie · Voiture', 'code_panne' => 'batterie', 'distance' => "1,4\u{00A0}km", 'telephone' => '+2250700000001']]) ?>
        <?= composant('ligne-demande', ['demande' => ['heure' => '09:47', 'client' => 'Un client', 'resume' => 'Pneu crevé · Moto', 'code_panne' => 'pneu_creve', 'distance' => "2,6\u{00A0}km", 'telephone' => null]]) ?>
      </ul>
      <ol class="tableau__etapes" role="list">
        <?= composant('etape-suivi', ['etat' => 'fait', 'date' => '20 octobre, 10:14', 'titre' => 'Dossier envoyé', 'texte' => '5 pièces reçues. La photo de l’atelier est facultative.']) ?>
        <?= composant('etape-suivi', ['etat' => 'en_cours', 'date' => '21 octobre, 08:30 · en cours', 'titre' => 'En vérification', 'texte' => 'Une personne compare votre photo à votre pièce d’identité.', 'echeance' => 'Réponse avant le 22 octobre, 10:14']) ?>
        <?= composant('etape-suivi', ['etat' => 'a_venir', 'date' => 'À venir', 'titre' => 'Validé', 'texte' => 'Vous pourrez vous rendre disponible et apparaître aux clients.', 'derniere' => true]) ?>
      </ol>
      <ul class="reprendre__verdicts" role="list">
        <?= composant('ligne-verdict', ['type' => 'cni_recto', 'libelle' => 'CNI recto', 'etat' => 'conforme']) ?>
        <?= composant('ligne-verdict', ['type' => 'portrait', 'libelle' => 'Photo de vous', 'etat' => 'a_reprendre', 'motif' => 'Visage flou. Reprenez la photo en plein jour, de face, sans lunettes de soleil.']) ?>
      </ul>
      <?= composant('bloc-suspension', ['depuis' => '2 octobre', 'duree' => 'En pause pour 7 jours.', 'motif' => 'Trois clients ont signalé que vous n’êtes pas venu après avoir confirmé l’intervention.', 'suite' => 'Jusqu’au 9 octobre, vous n’apparaissez plus dans les recherches. Votre compte se réactive tout seul ensuite.']) ?>
      <ul class="tableau__actions" role="list">
        <?= composant('ligne-action', ['libelle' => 'Voir ma fiche comme un client', 'icone' => 'oeil', 'href' => '#']) ?>
        <?= composant('ligne-action', ['libelle' => 'Lire les règles de la communauté', 'icone' => 'fichier', 'href' => '#']) ?>
      </ul>
      <?= composant('reprise-piece', ['type' => 'demo', 'libelle' => 'Ajouter la photo de mon atelier', 'icone' => 'camera', 'action' => '#']) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Mon profil · en-tête de section 120:1195 · étiquette de spécialité 54:445 · emplacement photo 120:1426 · tarif 120:1393 · numéro 120:1444 · ligne de compte 120:1461 · thème 120:1472 · mini-carte de zone 120:1260</span>
    <div class="systeme__grille systeme__pro">
      <?= composant('entete-section', ['libelle' => 'Photos de l’atelier', 'suite' => '· 1 / 3', 'href' => '#']) ?>
      <div class="profil__etiquettes"><?= composant('etiquette-lecture', ['libelle' => 'Électricité auto']) ?><?= composant('etiquette-lecture', ['libelle' => 'Batterie']) ?></div>
      <div class="profil__emplacements"><?= composant('emplacement-photo', ['href' => '#']) ?><?= composant('emplacement-photo', ['href' => '#']) ?></div>
      <div class="profil__section">
        <?= composant('ligne-tarif', ['libelle' => 'Diagnostic, dès', 'montant' => 5000]) ?>
        <?= composant('ligne-tarif', ['libelle' => 'Déplacement', 'montant' => null, 'inclus' => true]) ?>
        <?= composant('ligne-numero', ['icone' => 'telephone', 'libelle' => 'Téléphone', 'numero' => '+225 07 07 12 34 56']) ?>
        <?= composant('ligne-numero', ['icone' => 'whatsapp', 'libelle' => 'WhatsApp', 'numero' => '+225 05 44 21 08 17']) ?>
      </div>
      <ul class="profil__lignes" role="list">
        <?= composant('ligne-compte', ['icone' => 'cadenas', 'titre' => 'Mot de passe', 'sous' => 'Modifié le 2 octobre 2026', 'href' => '#']) ?>
        <?= composant('ligne-compte', ['icone' => 'retour', 'titre' => 'Se déconnecter', 'action' => '/deconnexion']) ?>
      </ul>
      <?= composant('segment-theme', []) ?>
      <div class="appel-pro appel-pro--sombre sur-sombre systeme__appel-sombre">
        <?= composant('carte-zone', ['lat' => 5.3652, 'lon' => -3.9478, 'rayon' => 10, 'visage' => asset('img/accueil/koffi-rond-104.webp')]) ?>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Demandes et avis du mécano · titre de jour 116:989 · demande 116:991 · note globale 118:1080 · barre d’étoiles 118:1089 · actions sous un avis 118:1145</span>
    <div class="systeme__grille systeme__pro">
      <ul class="liste-demandes" role="list">
        <?= composant('jour-demandes', ['libelle' => 'Aujourd’hui · 4']) ?>
        <?= composant('ligne-contact', ['demande' => ['heure' => '10:12', 'client' => 'Aminata', 'resume' => 'Batterie · Voiture', 'code_panne' => 'batterie', 'canal' => 'Appel', 'lieu' => 'Riviera 2', 'distance' => "1,4\u{00A0}km", 'retour' => 'non_joint', 'principal' => true, 'rappel' => '+2250700000001', 'masque' => null]]) ?>
        <?= composant('ligne-contact', ['demande' => ['heure' => '09:47', 'client' => 'Un client', 'resume' => 'Pneu crevé · Moto', 'code_panne' => 'pneu_creve', 'canal' => 'WhatsApp', 'lieu' => 'Riviera 4', 'distance' => "2,6\u{00A0}km", 'retour' => 'joint', 'principal' => false, 'rappel' => '+2250700000002', 'masque' => null]]) ?>
        <?= composant('jour-demandes', ['libelle' => 'Lundi 19 octobre · 2 · numéros masqués']) ?>
        <?= composant('ligne-contact', ['demande' => ['heure' => '09:05', 'client' => 'Un client', 'resume' => 'Batterie · Voiture', 'code_panne' => 'batterie', 'canal' => 'Appel', 'lieu' => 'Riviera 3', 'distance' => "0,6\u{00A0}km", 'retour' => 'joint', 'principal' => false, 'rappel' => null, 'masque' => '07 •• •• 34 56']]) ?>
      </ul>
      <?= composant('note-globale', ['note' => '4,8', 'nombre' => '37 avis', 'depuis' => 'Depuis votre validation, le 12 mars 2026.']) ?>
      <div class="repartition-avis">
        <?= composant('barre-etoiles', ['etoiles' => 5, 'nombre' => 31, 'total' => 37]) ?>
        <?= composant('barre-etoiles', ['etoiles' => 4, 'nombre' => 5, 'total' => 37]) ?>
        <?= composant('barre-etoiles', ['etoiles' => 2, 'nombre' => 0, 'total' => 37]) ?>
      </div>
      <?= composant('actions-avis', ['contact' => '01JDEMOC001000000000000002', 'repondu' => false, 'ouvert' => false]) ?>
      <?= composant('actions-avis', ['contact' => '01JDEMOC001000000000000003', 'repondu' => true, 'ouvert' => false]) ?>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Appels du mécano · réglage 272:1813 · nœud 272:1814 · 272:1821 · 272:1836 · en intervention (RG-D4) · intervention 272:1754 · mini-carte du client 271:1500 · commande Itinéraire 271:1802</span>
    <div class="systeme__grille systeme__pro">
      <ol class="activer__reglages" role="list">
        <?= composant('reglage-appel', ['cle' => 'demo-1', 'etat' => 'done', 'titre' => 'Installer allomécano sur l’écran d’accueil', 'texte' => 'Pour ouvrir l’app d’un geste.', 'fait' => 'Fait · icône ajoutée', 'libelle' => 'Installer', 'icone' => 'installer']) ?>
        <?= composant('reglage-appel', ['cle' => 'demo-2', 'etat' => 'now', 'titre' => 'Autoriser les notifications', 'texte' => 'Sans ça, votre téléphone ne sonne pas quand l’app est fermée.', 'fait' => 'Fait', 'libelle' => 'Autoriser', 'icone' => 'cloche']) ?>
        <?= composant('reglage-appel', ['cle' => 'demo-3', 'etat' => 'todo', 'titre' => 'Faire un essai de sonnerie', 'texte' => 'On fait sonner votre téléphone pour vérifier.', 'fait' => 'Fait', 'libelle' => 'Faire sonner mon téléphone', 'icone' => 'haut-parleur']) ?>
      </ol>
      <?= composant('interrupteur-dispo', ['etat' => 'intervention', 'demo' => true]) ?>
      <dl class="appel-pro__intervention">
        <?= composant('ligne-intervention', ['libelle' => 'Lieu', 'valeur' => 'Riviera 2, pharmacie des Jardins']) ?>
        <?= composant('ligne-intervention', ['libelle' => 'Distance', 'valeur' => "1,2\u{00A0}km à vol d’oiseau"]) ?>
      </dl>
      <div class="appel-pro appel-pro--sombre sur-sombre systeme__appel-sombre">
        <?= composant('carte-client', ['lat' => 5.3655, 'lon' => -3.954, 'precision' => 18, 'legende' => "Aminata · 1,2\u{00A0}km", 'libelle' => 'Position approximative du client (exemple)']) ?>
        <div class="appel__commandes"><?= composant('commande-appel', ['icone' => 'itineraire', 'libelle' => 'Itinéraire', 'href' => '#']) ?></div>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Inscription mécano · brouillon 95:169 · étiquette de choix 54:451 · stepper 54:500 · curseur de rayon 97:318 · pochette de pièce 54:603 · guide photo 102:624 · récapitulatif 104:637 · étiquette Vérifié 49:175</span>
    <div class="systeme__grille systeme__admin">
      <?= composant('parcours-brouillon', ['heure' => '10:14', 'pieces' => ['cni_recto' => 'envoyee', 'cni_verso' => 'envoyee', 'permis' => 'manquante', 'diplome' => 'manquante', 'portrait' => 'envoyee', 'atelier' => 'facultative']]) ?>
      <div class="etiquettes-choix">
        <?= composant('etiquette-choix', ['nom' => 'demo', 'valeur' => '1', 'libelle' => 'Mécanique générale', 'coche' => true]) ?>
        <?= composant('etiquette-choix', ['nom' => 'demo', 'valeur' => '2', 'libelle' => 'Diesel']) ?>
      </div>
      <?= composant('stepper', ['nom' => 'demo-annees', 'valeur' => 12, 'min' => 0, 'max' => 60, 'unite' => 'ans', 'libelle' => 'Années d’expérience']) ?>
      <?= composant('curseur-rayon', ['nom' => 'demo-rayon', 'valeur' => 5, 'rayons' => [2, 5, 10, 20]]) ?>
      <ul class="pieces__pochettes" role="list">
        <?= composant('pochette-piece', ['type' => 'cni_recto', 'libelle' => 'CNI recto', 'etat' => 'conforme', 'action' => '#']) ?>
        <?= composant('pochette-piece', ['type' => 'cni_verso', 'libelle' => 'CNI verso', 'etat' => 'envoyee', 'taille' => 386 * 1024, 'action' => '#']) ?>
        <?= composant('pochette-piece', ['type' => 'permis', 'libelle' => 'Permis de conduire', 'etat' => 'envoi', 'action' => '#']) ?>
        <?= composant('pochette-piece', ['type' => 'diplome', 'libelle' => 'Diplôme ou attestation', 'etat' => 'manquante', 'action' => '#']) ?>
        <?= composant('pochette-piece', ['type' => 'portrait', 'libelle' => 'Photo de vous', 'etat' => 'a_reprendre', 'motif' => 'La photo est floue : le visage n’est pas net.', 'action' => '#']) ?>
      </ul>
      <?= composant('guide-photo', []) ?>
      <?= composant('bloc-recap', ['rubrique' => 'Zone et tarifs', 'href' => '#', 'lignes' => [['style' => 'titre', 'texte' => 'Marcory, Zone 4 · jusqu’à 5 km']], 'tarifs' => [['libelle' => 'Diagnostic, à partir de', 'valeur' => '5 000'], ['libelle' => 'Déplacement', 'valeur' => 'Inclus', 'unite' => '']]]) ?>
      <div class="systeme__ligne"><?= composant('etiquette-verifie', ['etat' => 'verifie']) ?><?= composant('etiquette-verifie', ['etat' => 'attente']) ?></div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Signalements · onglets 179:607 · alerte critique en retard 179:614 · ligne de signalement 179:642 · 179:654 · 179:666</span>
    <div class="systeme__grille systeme__admin">
      <?= composant('onglets-admin', ['nom' => 'Exemple d’onglets', 'onglets' => [['libelle' => 'Ouverts · 3', 'href' => '#', 'actif' => true], ['libelle' => 'Classés · 30 jours', 'href' => '#', 'actif' => false]]]) ?>
      <?= composant('bande-alerte', ['texte' => '1 critique non traité depuis plus de 2 h']) ?>
      <ul class="signalements__lignes" role="list">
        <?= composant('ligne-signalement', ['href' => '#', 'actif' => true, 'signalement' => ['public_id' => 'a', 'numero' => 'SIG-2026-0187', 'gravite' => 'critique', 'titre' => 'Comportement agressif ou menaçant', 'mecano' => 'Koffi Yao', 'quartier' => 'Riviera 3', 'quand' => "il y a 2\u{00A0}h\u{00A0}40"]]) ?>
        <?= composant('ligne-signalement', ['href' => '#', 'signalement' => ['public_id' => 'b', 'numero' => 'SIG-2026-0186', 'gravite' => 'important', 'titre' => 'Prix très différent de ce qui était annoncé', 'mecano' => 'Bakary Sanogo', 'quartier' => 'Port-Bouët', 'quand' => "il y a 5\u{00A0}h"]]) ?>
        <?= composant('ligne-signalement', ['href' => '#', 'signalement' => ['public_id' => 'c', 'numero' => 'SIG-2026-0184', 'gravite' => 'normal', 'titre' => 'Autre', 'mecano' => 'Serge Kouassi', 'quartier' => 'Marcory Biétry', 'quand' => 'hier · 19:12']]) ?>
      </ul>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Statistiques · étape de l’entonnoir 180:706 · indicateur 180:751</span>
    <div class="systeme__grille systeme__admin">
      <table class="entonnoir">
        <tbody>
          <?= composant('ligne-entonnoir', ['periode' => 30, 'autre' => 7, 'etape' => ['titre' => 'Recherches', 'sous' => 'Position et panne envoyées', 'valeur' => 3420, 'autre' => 812, 'conversion' => 'base', 'largeur' => 100.0]]) ?>
          <?= composant('ligne-entonnoir', ['periode' => 30, 'autre' => 7, 'etape' => ['titre' => 'Contacts', 'sous' => 'Appel ou WhatsApp lancé', 'valeur' => 1462, 'autre' => 351, 'conversion' => "43\u{202F}% des recherches", 'largeur' => 42.75]]) ?>
        </tbody>
      </table>
      <div class="stats__indicateurs">
        <?= composant('indicateur-stat', ['titre' => 'Délai moyen de validation', 'valeur' => 19, 'unite' => 'h', 'repere' => "Objectif : moins de 48\u{00A0}h · 26\u{00A0}h le mois dernier", 'explication' => "Du dossier envoyé à la décision de l’équipe. Le plus long ce mois-ci : 41\u{00A0}h."]) ?>
        <?= composant('indicateur-stat', ['titre' => 'Taux de reproposition', 'valeur' => 29, 'unite' => '%', 'repere' => '123 clients sur 424 sans réponse', 'explication' => 'Clients qui n’ont pas pu joindre leur mécano et ont appelé celui qu’on leur a proposé ensuite.']) ?>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Appel intégré · anneau 269:1827 / 269:1873 · commande 269:1849 / 269:1891 · état 269:1837 / 269:1881 / 270:1853 · repère carte W44 270:1862</span>
    <div class="systeme__grille systeme__appel">
      <div class="appel appel--sombre sur-sombre systeme__appel-sombre">
        <?= composant('anneau-appel', ['portrait' => ['src' => '/medias/demo/koffi-360.webp', 'srcset' => '/medias/demo/koffi-360.webp 360w', 'couleur' => '#6f665b', 'cadrage' => '50% 30%'], 'nom' => 'Koffi Yao', 'phase' => 'sonne']) ?>
        <div class="appel__identite">
          <?= composant('etat-appel', ['voyant' => 'recherche', 'texte' => 'Ça sonne chez Koffi', 'chrono' => '0:12']) ?>
          <?= composant('etat-appel', ['texte' => 'En ligne', 'chrono' => '01:24']) ?>
        </div>
        <div class="appel__commandes">
          <?= composant('commande-appel', ['icone' => 'micro', 'libelle' => 'Micro']) ?>
          <?= composant('commande-appel', ['icone' => 'micro-barre', 'libelle' => 'Micro coupé', 'actif' => true]) ?>
        </div>
      </div>
      <div class="systeme__ligne">
        <?= composant('etat-appel', ['voyant' => 'indisponible', 'texte' => '30 s sans réponse']) ?>
        <?= composant('etat-appel', ['voyant' => 'erreur', 'texte' => 'Connexion impossible']) ?>
        <?= composant('repere-carte', ['type' => 'numerote', 'rang' => 2, 'largeur' => 44]) ?>
      </div>
      <div class="appel__reponses">
        <?= composant('reponse-retour', ['contact' => '01JDEMOC00100000000000001', 'issue' => 'arrive', 'libelle' => 'Il m’a dit qu’il arrive', 'compacte' => true]) ?>
      </div>
    </div>
  </section>

  <section class="rubrique">
    <span class="t-etiquette">Panneau coupé L · Figma 67:619</span>
    <div class="systeme__carte-fond systeme__panneau">
      <?= composant('panneau', ['contenu' => '<p class="t-titre-2">Riviera 2, Cocody</p><p class="t-donnee t-secondaire">5,3571° N · 3,9672° O · ± 18 M</p>']) ?>
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
