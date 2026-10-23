<?php
/**
 * X3 · À propos (Figma 136:206) : l'accroche, ce qu'on fait, ce que « vérifié » veut dire, ceux qui réparent, ce qu'on ne fait pas, l'équipe.
 * @var list<array{nom: string, image: string, court: string, metiers: string, quartier: string}> $trombinoscope
 */
use App\Core\Format;

$typo = fn (string $t): string => e(Format::typo($t));
$controles = ['Pièce d’identité, recto et verso', 'Permis de conduire', 'Diplôme ou attestation de métier', 'Photo de face, comparée à la pièce'];
$refus = [
    ['On n’encaisse rien.', 'Vous payez le mécano directement, comme vous l’avez convenu avec lui.'],
    ['On ne fixe pas les prix.', 'Le prix se discute entre vous et lui, avant qu’il commence.'],
    ['On ne garde pas votre position.', 'Elle sert à trouver le plus proche, puis on l’oublie.'],
];
?>
<article class="apropos">
  <?= composant('photo', ['image' => 'img/accueil/img-01', 'largeurs' => [780, 1264], 'sizes' => '(min-width: 1024px) 57vw, 100vw', 'largeur' => 1264, 'hauteur' => 1000,
      'alt' => 'Une panne sur une route d’Abidjan, au crépuscule.', 'legende' => 'ABIDJAN · 18:40', 'prioritaire' => true, 'classe' => 'apropos__photo']) ?>

  <section class="apropos__accroche conteneur" aria-labelledby="accroche">
    <h1 class="t-display-l" id="accroche"><?= $typo('Une panne à Abidjan ne devrait pas être une loterie.') ?></h1>
    <p class="t-lead">Aujourd’hui, on trouve un mécano par le bouche-à-oreille. On appelle un cousin, un voisin, on tombe sur n’importe qui. On ne sait pas qui va venir, ni quand.</p>
    <p class="t-base t-secondaire">allomécano vous montre le mécano vérifié, disponible et le plus proche. Vous l’appelez directement.</p>
  </section>

  <section class="apropos__chapitre apropos__chapitre--filet conteneur" aria-labelledby="fait">
    <p class="t-etiquette t-secondaire">Ce qu’on fait</p>
    <h2 class="t-titre-1" id="fait"><?= $typo('On vous met en relation. Le reste se passe entre vous.') ?></h2>
    <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-07', 'texte' => 'Aminata, panne boulevard VGE, feux de détresse'], 'alt' => 'Aminata attend à côté de sa voiture en panne, feux de détresse allumés.', 'legende' => 'MARCORY · 09:41', 'classe' => 'apropos__photo-etroite']) ?>
    <p class="t-base"><?= $typo('Vous dites où vous êtes et ce qui ne va pas. On vous montre les mécanos disponibles autour de vous, du plus proche au plus loin, à vol d’oiseau.') ?></p>
    <p class="t-base">Vous l’appelez ou vous lui écrivez sur WhatsApp. Pas de compte, pas d’intermédiaire au bout du fil.</p>
  </section>

  <section class="apropos__chapitre apropos__chapitre--sombre sur-sombre" aria-labelledby="verifie">
    <div class="conteneur apropos__sombre-interieur">
      <p class="t-etiquette">Ce que vérifié veut dire</p>
      <h2 class="t-titre-1" id="verifie">Une personne a regardé ses pièces, et son visage.</h2>
      <div class="apropos__comparaison">
        <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-08', 'texte' => 'Mains qui serrent un écrou de roue'], 'alt' => 'Des mains serrent un écrou de roue.', 'classe' => 'apropos__carre']) ?>
        <?= composant('specimen-cni', ['classe' => 'apropos__cni']) ?>
      </div>
      <p class="t-etiquette">Comparées par une personne</p>
      <ul class="apropos__controles" role="list">
        <?php foreach ($controles as $c): ?><li><?= composant('poincon', ['taille' => 18]) ?><span class="t-base"><?= $typo($c) ?></span></li><?php endforeach ?>
      </ul>
      <p class="t-petit apropos__reserve"><?= $typo('On vérifie l’identité et les documents, pas chaque réparation. C’est pour ça qu’il y a des avis, et un bouton pour signaler.') ?></p>
    </div>
  </section>

  <section class="apropos__chapitre apropos__reparent" aria-labelledby="reparent">
    <div class="apropos__entete">
      <p class="t-etiquette t-secondaire">Ceux qui réparent</p>
      <h2 class="t-titre-1" id="reparent">Des mécanos de quartier, avec un nom et un visage.</h2>
    </div>
    <div class="trombi__defilement" role="region" aria-labelledby="reparent" tabindex="0">
      <ul class="trombi__liste" role="list">
        <?php foreach ($trombinoscope as $m): ?>
          <li class="trombi__mecano">
            <?= composant('photo', ['image' => 'img/accueil/' . $m['image'], 'largeurs' => [302, 604], 'sizes' => '148px', 'largeur' => 604, 'hauteur' => 756, 'alt' => 'Portrait de ' . $m['nom'] . '.']) ?>
            <p class="t-titre-3"><?= e($m['nom']) ?></p>
            <p class="t-petit t-secondaire"><?= e($m['metiers']) ?></p>
            <p class="t-etiquette t-secondaire"><?= e($m['quartier']) ?></p>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
    <figure class="apropos__citation">
      <blockquote class="t-citation">« On me connaît à Biétry depuis vingt ans. Maintenant, ceux qui ne me connaissent pas me trouvent aussi. »</blockquote>
      <figcaption class="t-etiquette t-secondaire">Serge Kouassi · Marcory Biétry</figcaption>
    </figure>
    <p class="t-micro t-secondaire apropos__mention">En production : uniquement des mécanos vérifiés qui ont donné leur accord.</p>
  </section>

  <section class="apropos__chapitre apropos__chapitre--filet conteneur" aria-labelledby="refus">
    <p class="t-etiquette t-secondaire" id="refus">Ce qu’on ne fait pas</p>
    <?php foreach ($refus as [$titre, $texte]): ?>
      <div class="apropos__refus"><h2 class="t-titre-1"><?= e($titre) ?></h2><p class="t-base t-secondaire"><?= e($texte) ?></p></div>
    <?php endforeach ?>
  </section>

  <section class="apropos__equipe conteneur" aria-labelledby="equipe">
    <p class="t-etiquette t-secondaire">L’équipe</p>
    <h2 class="t-titre-2" id="equipe">Une petite équipe, à Abidjan.</h2>
    <ul class="apropos__emplacements" role="list">
      <?php foreach (['Fondation', 'Vérification', 'Terrain'] as $role): ?>
        <li><span class="apropos__creux" aria-hidden="true"></span><span class="t-etiquette"><?= e($role) ?></span><span class="t-petit t-secondaire">Portrait à venir</span></li>
      <?php endforeach ?>
    </ul>
    <p class="t-base">Une question, une idée, un mécano à nous présenter ?</p>
    <a class="lien-icone t-gras" href="/contact"><span>Nous écrire</span><?= icone('chevron', 20) ?></a>
  </section>

  <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-09', 'texte' => 'Le mécano arrive à moto, Riviera'], 'alt' => 'Un mécano arrive à moto.', 'legende' => 'RIVIERA 2 · 09:58', 'classe' => 'apropos__photo']) ?>

  <section class="apropos__fin conteneur" aria-labelledby="fin">
    <h2 class="t-titre-2" id="fin">En panne, maintenant ?</h2>
    <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'hauteur' => 64, 'plein' => true, 'href' => '/position']) ?>
    <p class="t-petit t-secondaire">Sans compte, gratuit. Vous l’appelez directement.</p>
  </section>
</article>
