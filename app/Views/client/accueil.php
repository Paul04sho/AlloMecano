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
      <svg class="accueil__scene" viewBox="0 0 600 360" role="img" aria-label="<?= $texte('Un mécanicien intervient sur une voiture en panne à Abidjan') ?>" xmlns="http://www.w3.org/2000/svg">
        <rect width="600" height="360" fill="var(--carte-eau)"/>
        <path d="M0 160 90 126 150 147 220 104 285 143 360 117 434 148 515 120 600 149V0H0Z" fill="var(--surface-creuse)"/>
        <path d="M0 208 78 181 156 192 242 162 334 190 430 168 516 190 600 171V0H0Z" fill="var(--surface-elevee)" opacity=".45"/>
        <g fill="var(--encre-2)" opacity=".72">
          <path d="M22 142h42v70H22zM70 125h31v87H70zM108 153h54v59h-54zM450 137h40v75h-40zM496 117h28v95h-28zM532 148h47v64h-47z"/>
        </g>
        <path d="M0 265Q170 223 310 248T600 231V360H0Z" fill="var(--carte-sol)"/>
        <path d="M0 310Q160 270 305 296T600 274V360H0Z" fill="var(--encre)" opacity=".88"/>
        <path d="M0 326Q140 293 264 313T600 296" fill="none" stroke="var(--filet-sombre)" stroke-width="5"/>
        <path d="M22 348 94 337M153 330 217 322M282 326 351 324M416 317 481 309M534 305 584 300" stroke="var(--beton)" stroke-width="5" stroke-linecap="round" opacity=".85"/>
        <g>
          <path d="M300 226 334 177Q344 162 364 162H441Q459 162 474 180L510 220 548 228Q561 231 564 246L567 267H295L292 249Q292 235 300 226Z" fill="var(--balise)" stroke="var(--bitume)" stroke-width="5" stroke-linejoin="round"/>
          <path d="M349 181 333 216H389V170H365Q355 170 349 181Z" fill="var(--surface-elevee)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M400 170V216H463L435 184Q428 176 416 172Z" fill="var(--surface-elevee)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M473 220H526L505 198H454Z" fill="var(--balise-presse)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M294 239H310M549 241H562" stroke="var(--beton)" stroke-width="8" stroke-linecap="round"/>
          <circle cx="347" cy="267" r="25" fill="var(--bitume)"/>
          <circle cx="347" cy="267" r="11" fill="var(--gris-voyant)"/>
          <circle cx="512" cy="267" r="25" fill="var(--bitume)"/>
          <circle cx="512" cy="267" r="11" fill="var(--gris-voyant)"/>
        </g>
        <g>
          <path d="M177 179 199 157 221 162 235 189 231 228 249 266 227 276 206 233 197 203 189 227 190 273 165 273 166 216Z" fill="var(--encre)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M184 177 171 198 153 221 163 232 189 213 204 193Z" fill="var(--encre-2)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M211 179 228 192 257 212 250 224 222 211 206 197Z" fill="var(--encre-2)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M184 274 177 313 193 317 207 285 211 247Z" fill="var(--bitume)"/>
          <path d="M210 273 221 311 239 309 231 269Z" fill="var(--bitume)"/>
          <path d="M173 310 164 321 195 324 197 316Z M219 308 218 323 246 320 237 307Z" fill="var(--bitume)"/>
          <path d="M188 147Q179 130 189 114Q201 101 217 113Q228 127 218 147L209 156Z" fill="var(--carte-sol)" stroke="var(--bitume)" stroke-width="4"/>
          <path d="M183 124Q181 107 199 102Q218 99 226 117L220 124 210 116 194 128Z" fill="var(--bitume)"/>
          <path d="M189 146 205 153 217 145 221 161 198 173 179 160Z" fill="var(--balise)" stroke="var(--bitume)" stroke-width="4" stroke-linejoin="round"/>
          <path d="M172 191 155 214 146 228" stroke="var(--bitume)" stroke-width="7" stroke-linecap="round"/>
        </g>
        <g fill="var(--balise)">
          <path d="M130 75 139 91 156 94 142 106 145 124 130 115 114 124 117 106 103 94 121 91Z"/>
          <circle cx="263" cy="111" r="5"/>
          <circle cx="281" cy="91" r="3"/>
        </g>
      </svg>
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
          <div class="accueil__identite-portrait">
            <span class="accueil__portrait-tete"></span>
            <span class="accueil__portrait-corps"></span>
          </div>
          <div class="accueil__identite-document">
            <span class="accueil__document-photo"></span>
            <span class="accueil__document-ligne"></span>
            <span class="accueil__document-ligne"></span>
            <span class="accueil__document-ligne"></span>
            <span class="accueil__document-signature"></span>
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
            <div class="accueil__portrait accueil__portrait--<?= e($profil['variante']) ?>" role="img" aria-label="<?= $texte('Portrait illustré de ' . $profil['nom']) ?>">
              <span class="accueil__portrait-tete"></span>
              <span class="accueil__portrait-corps"></span>
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
      <div class="accueil__pros-visuel" role="img" aria-label="<?= $texte('Un mécanicien dans son atelier') ?>">
        <div class="accueil__pros-atelier" aria-hidden="true">
          <span class="accueil__atelier-roue"></span>
          <span class="accueil__atelier-personne">
            <span class="accueil__portrait-tete"></span>
            <span class="accueil__portrait-corps"></span>
          </span>
          <span class="accueil__atelier-outil"><?= icone('atelier', 32) ?></span>
        </div>
      </div>
      <div class="accueil__pros-contenu">
        <p class="t-etiquette"><?= $texte('Vous êtes mécanicien ?') ?></p>
        <h2 id="accueil-pros" class="t-titre-1"><?= $texte('Les pannes de votre quartier vous appellent directement.') ?></h2>
        <p class="t-petit t-secondaire"><?= $texte('Rejoignez les professionnels vérifiés et recevez des demandes quand vous êtes disponible.') ?></p>
        <?= composant('bouton', ['libelle' => 'Devenir mécano vérifié', 'icone' => 'atelier', 'href' => '/pro/devenir', 'hauteur' => 56, 'plein' => true]) ?>
      </div>
    </div>
  </section>

  <section class="accueil__urgence" aria-labelledby="accueil-urgence">
    <div class="accueil__conteneur accueil__urgence-interieur">
      <div class="accueil__urgence-texte">
        <p class="t-etiquette"><?= $texte('Sécurité') ?></p>
        <h2 id="accueil-urgence" class="t-titre-2"><?= $texte('Blessé ou en danger ? Appelez d’abord les secours.') ?></h2>
      </div>
      <div class="accueil__numeros">
        <a class="accueil__numero" href="tel:185"><strong class="t-borne-l"><?= e(Format::typo('185')) ?></strong><span class="t-etiquette"><?= $texte('SAMU') ?></span></a>
        <a class="accueil__numero" href="tel:180"><strong class="t-borne-l"><?= e(Format::typo('180')) ?></strong><span class="t-etiquette"><?= $texte('Pompiers') ?></span></a>
        <a class="accueil__numero" href="tel:170"><strong class="t-borne-l"><?= e(Format::typo('170')) ?></strong><span class="t-etiquette"><?= $texte('Police') ?></span></a>
      </div>
      <p class="t-micro t-secondaire"><?= $texte('N’attendez pas un mécanicien pour vous mettre en sécurité.') ?></p>
    </div>
  </section>
</div>
