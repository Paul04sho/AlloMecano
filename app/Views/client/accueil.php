<?php

declare(strict_types=1);

$texte = static fn (string $valeur): string => e(fr_typo($valeur));

$profils = [
    [
        'nom' => 'Koffi Yao',
        'specialite' => 'Électricité auto',
        'metier' => 'Moteur · Batterie',
        'note' => '4,8',
        'avis' => '37 avis',
        'variante' => 'koffi',
    ],
    [
        'nom' => 'Adama Traoré',
        'specialite' => 'Auto · Moto',
        'metier' => 'Mécanique générale',
        'note' => '4,9',
        'avis' => '24 avis',
        'variante' => 'adama',
    ],
    [
        'nom' => 'Awa K.',
        'specialite' => 'Pneus',
        'metier' => 'Dépannage · Vulcanisation',
        'note' => '4,7',
        'avis' => '18 avis',
        'variante' => 'awa',
    ],
];

$communes = [
    ['nom' => 'Cocody', 'disponibles' => '18', 'statut' => 'dispo'],
    ['nom' => 'Yopougon', 'disponibles' => '14', 'statut' => 'dispo'],
    ['nom' => 'Marcory', 'disponibles' => '9', 'statut' => 'dispo'],
    ['nom' => 'Abobo', 'disponibles' => '7', 'statut' => 'dispo'],
    ['nom' => 'Attécoubé', 'disponibles' => null, 'statut' => 'bientot'],
    ['nom' => 'Songon', 'disponibles' => null, 'statut' => 'bientot'],
];
?>
<div class="accueil">
  <section class="accueil__hero" aria-labelledby="accueil-titre">
    <div class="accueil__hero-visuel">
      <img src="/medias/photo-hero.png" 
     alt="<?= $texte('Un mécanicien intervient sur une voiture en panne à Abidjan') ?>" 
     class="accueil__scene" 
     width="600" 
     height="360" 
     loading="eager">

      <span class="accueil__repere-photo t-etiquette"><?= $texte('Adjamé · 16:12') ?></span>
    </div>

    <div class="accueil__hero-contenu">
      <p class="t-etiquette accueil__surtitre"><?= $texte('Le bon réflexe en cas de panne') ?></p>
      <h1 id="accueil-titre" class="t-titre-1"><?= $texte('En panne ?') ?><span class="accueil__hero-sous-titre t-titre-2"><?= $texte('Le mécano vérifié le plus proche.') ?></span></h1>
      <p class="t-lead accueil__intro"><?= $texte('Trouvez un mécanicien vérifié près de vous. Vous l’appelez directement.') ?></p>
      <div class="accueil__action">
        <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'href' => '/position', 'hauteur' => 64, 'plein' => true]) ?>
        <p class="t-petit t-secondaire"><?= $texte('Sans compte, gratuit. Vous l’appelez directement.') ?></p>
      </div>
    </div>
  </section>

  <section class="accueil__reconstitution" aria-labelledby="accueil-reconstitution">
    <div class="accueil__conteneur">
      <div class="rubrique">
        <p class="t-etiquette"><?= $texte('Reconstitution') ?></p>
        <h2 id="accueil-reconstitution" class="t-titre-1"><?= $texte('Mardi, 09:41, boulevard VGE.') ?></h2>
      </div>

      <div class="accueil__fil">
        <article class="accueil__etape">
          <div class="accueil__heure t-borne-l"><?= $texte('09:41') ?></div>
          <div class="accueil__etape-corps">
            <div class="accueil__vignette accueil__vignette--route" role="img" aria-label="<?= $texte('Une voiture s’arrête au bord de la route') ?>">
              <span class="accueil__vignette-tag t-etiquette"><?= $texte('Adjamé · VGE') ?></span>
              <span class="accueil__vignette-scene" aria-hidden="true"></span>
            </div>
            <p class="t-petit"><?= $texte('La voiture s’arrête au feu de Marcory. Le conducteur ouvre allomécano.') ?></p>
          </div>
        </article>

        <article class="accueil__etape">
          <div class="accueil__heure t-borne-l"><?= $texte('09:42') ?></div>
          <div class="accueil__etape-corps">
            <div class="accueil__vignette accueil__vignette--mecano" role="img" aria-label="<?= $texte('Un mécanicien rejoint le véhicule en panne') ?>">
              <span class="accueil__vignette-tag t-etiquette"><?= $texte('Koffi Yao') ?></span>
              <span class="accueil__vignette-personne" aria-hidden="true"></span>
            </div>
            <p class="t-gras"><?= $texte('Koffi Yao') ?></p>
            <p class="t-petit t-secondaire"><?= $texte('Électricité auto · Batterie') ?></p>
            <p class="t-micro accueil__note"><?= icone('etoile', 16) ?> <strong><?= $texte('4,8') ?></strong> · <?= $texte('37 avis') ?></p>
            <p class="t-petit"><?= $texte('Koffi est à 1,2 km. Elle l’appelle.') ?></p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section class="accueil__verification" id="verification" aria-labelledby="accueil-verification">
    <div class="accueil__conteneur accueil__verification-grille">
      <div class="accueil__verification-visuel">
        <div class="accueil__identite" role="img" aria-label="<?= $texte('Portrait et document d’identité contrôlés par l’équipe') ?>">
          <!-- PORTRAIT DU MÉCANICIEN -->
          <div class="accueil__identite-portrait">
            <img src="/medias/koffi.png" 
                 alt="<?= $texte('Photo de présentation du mécanicien vérifié') ?>" 
                 class="accueil__portrait-image"
                 style="width: 100%; height: 100%; object-fit: cover;">
          </div>

          <!-- SPÉCIMEN DE LA CNI -->
          <div class="accueil__identite-document">
            <img src="/medias/specimen-cni.png" 
                 alt="<?= $texte('Spécimen de la pièce d’identité contrôlée') ?>" 
                 class="accueil__document-cni"
                 style="width: 100%; height: 100%; object-fit: contain;">
          </div>
        </div>
      </div>

      <div class="accueil__verification-contenu">
        <div class="rubrique">
          <p class="t-etiquette"><?= $texte('Vérification') ?></p>
          <h2 id="accueil-verification" class="t-titre-1"><?= $texte('On a vérifié son visage, pas seulement son numéro.') ?></h2>
        </div>
        <ul class="accueil__preuves" role="list">
          <li><?= icone('coche', 20) ?><span><?= $texte('Pièce d’identité contrôlée') ?></span></li>
          <li><?= icone('coche', 20) ?><span><?= $texte('Permis de conduire') ?></span></li>
          <li><?= icone('coche', 20) ?><span><?= $texte('Diplôme ou attestation de métier') ?></span></li>
          <li><?= icone('coche', 20) ?><span><?= $texte('Un avis après chaque intervention') ?></span></li>
        </ul>
        <a class="bouton-lien accueil__lien-verification" href="#verification-details"><?= $texte('Comment on vérifie') ?> <?= icone('chevron', 18) ?></a>
        <p id="verification-details" class="t-petit accueil__details-verification"><?= $texte('Chaque profil est examiné par notre équipe avant d’être présenté aux automobilistes.') ?></p>
      </div>
    </div>
  </section>

  <section class="accueil__mecanos" aria-labelledby="accueil-mecanos">
    <div class="accueil__conteneur">
      <div class="rubrique">
        <p class="t-etiquette"><?= $texte('Ils réparent près de vous') ?></p>
        <h2 id="accueil-mecanos" class="t-titre-1"><?= $texte('Des visages, pas des numéros.') ?></h2>
      </div>

      <div class="accueil__profils">
        <?php foreach ($profils as $profil): ?>
          <article class="accueil__profil">
            <!-- PORTRAIT ILLUSTRÉ DYNAMIQUE -->
            <div class="accueil__portrait" style="position: relative; display: block; overflow: hidden; padding: 0;">
              <!-- L'image est forcée à prendre toute la boîte avec un positionnement absolu -->
                <img src="/medias/<?= e($profil['variante']) ?>.png"
                alt="<?= $texte('Portrait illustré de ' . $profil['nom']) ?>"
                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
            </div>

            <div class="accueil__profil-infos">
              <h3 class="t-label"><?= $texte($profil['nom']) ?></h3>
              <p class="t-micro t-secondaire"><?= $texte($profil['specialite']) ?></p>
              <p class="t-micro t-secondaire"><?= $texte($profil['metier']) ?></p>
              <p class="accueil__note t-micro"><?= icone('etoile', 16) ?> <strong><?= $texte($profil['note']) ?></strong> · <?= $texte($profil['avis']) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="accueil__communes" id="communes" aria-labelledby="accueil-communes">
    <div class="accueil__conteneur accueil__communes-grille">
      <div class="rubrique">
        <p class="t-etiquette"><?= $texte('13 communes · Mardi 10:14') ?></p>
        <h2 id="accueil-communes" class="t-titre-1"><?= $texte('Qui est disponible, et où ?') ?></h2>
        <p class="t-petit t-secondaire"><?= $texte('La disponibilité varie selon le quartier et le moment.') ?></p>
      </div>

      <ul class="accueil__liste-communes" role="list">
        <?php foreach ($communes as $commune): ?>
          <li class="accueil__commune">
            <span class="t-gras"><?= $texte($commune['nom']) ?></span>
            <?php if ($commune['statut'] === 'dispo'): ?>
              <span class="accueil__commune-statut"><strong class="t-borne-l"><?= $texte((string) $commune['disponibles']) ?></strong><span class="t-etiquette"><?= $texte('Dispo') ?></span></span>
            <?php else: ?>
              <span class="t-micro t-secondaire"><?= $texte($commune['nom'] === 'Songon' ? 'Bientôt' : 'Aucun pour l’instant') ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <a class="bouton-lien accueil__lien-communes" href="#communes"><?= $texte('Voir les 13 communes') ?> <?= icone('chevron', 18) ?></a>
    </div>
  </section>

  <section class="accueil__pros" aria-labelledby="accueil-pros">
    <div class="accueil__conteneur accueil__pros-grille">
      <div class="accueil__pros-visuel" style="position: relative; display: block; overflow: hidden; padding: 0;">
        <img src="/medias/atelier-pro.png"
        alt="<?= $texte('Un mécanicien professionnel dans son atelier de réparation moderne') ?>"
        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;"
        loading="lazy">
      </div>
      <div class="accueil__pros-contenu">
        <p class="t-etiquette"><?= $texte('Vous êtes mécanicien ?') ?></p>
        <h2 id="accueil-pros" class="t-titre-1"><?= $texte('Les pannes de votre quartier vous appellent directement.') ?></h2>
        <p class="t-petit t-secondaire"><?= $texte('Rejoignez les professionnels vérifiés et recevez des demandes quand vous êtes disponible.') ?></p>
        <?= composant('bouton', ['libelle' => 'Devenir mécano vérifié', 'icone' => 'atelier', 'href' => '/pro/devenir', 'hauteur' => 56, 'plein' => true]) ?>
      </div>
    </div>
  </section>
</div>
