<?php
/**
 * C1 · Accueil (mobile, Figma 58:15) et D0 · Accueil (desktop ≥ 1024, Figma 154:14).
 * Une seule page : les blocs propres à un format portent .seul-mobile / .seul-desktop.
 * @var list<array{nom: string, slug: string, etat: string, nombre: int}> $communes
 * @var array<string, string> $vehicules
 * @var list<array{code: string, libelle: string, icone: string, danger: bool}> $pannes
 * @var list<array{nom: string, image: string, court: string, metiers: string, quartier: string}> $trombinoscope
 * @var DateTimeImmutable $maintenant
 * @var ?array{public_id: string, prenom: string} $bandeauContact contact sans réponse depuis moins de 24 h (RG-I4)
 */
use App\Core\Format;

$nbsp = Format::INSECABLE;
// Tableau des communes : 4 colonnes sur desktop (4 · 3 · 3 · 3 pour 13), une liste sur mobile
$nbColonnes = 4;
$colonnes = [];
$reste = $communes;
for ($c = 0; $c < $nbColonnes; $c++) {
    $taille = intdiv(count($communes), $nbColonnes) + ($c < count($communes) % $nbColonnes ? 1 : 0);
    $colonnes[] = array_splice($reste, 0, $taille);
}
$visiblesMobile = 6;
$lienWhatsapp = 'https://wa.me/?text=' . rawurlencode(url('/'));
?>
<div class="accueil">
  <?php if (!empty($bandeauContact)): ?><?= composant('bandeau-retour', $bandeauContact) ?><?php endif ?>

  <!-- Héros : photo IMG-01, accroche, action (mobile : bouton ; desktop : formulaire intégré) -->
  <section class="heros" aria-labelledby="heros-titre">
    <?= composant('photo', [
        'image' => 'img/accueil/img-01', 'largeurs' => [780, 1264], 'sizes' => '(min-width: 1024px) 57vw, 100vw',
        'largeur' => 1264, 'hauteur' => 848, 'prioritaire' => true, 'classe' => 'heros__photo',
        'alt' => 'À Adjamé, un mécanicien répare le moteur d’un taxi orange, capot ouvert.',
        'legende' => 'Adjamé · 10:12',
    ]) ?>
    <div class="heros__accroche">
      <p class="t-etiquette t-secondaire seul-desktop" data-entree>Abidjan · <?= count($communes) ?> communes</p>
      <h1 class="t-display-xl heros__titre" id="heros-titre" data-entree><?= e(Format::typo('En panne ?')) ?></h1>
      <p class="t-titre-2 heros__promesse" data-entree>Le mécano vérifié<br class="seul-mobile"> le plus proche.</p>

      <div class="heros__action seul-mobile" data-entree>
        <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'hauteur' => 64, 'plein' => true, 'href' => '/position']) ?>
        <p class="t-petit t-secondaire">Sans compte, gratuit. Vous l’appelez directement.</p>
      </div>

      <form class="heros__formulaire seul-desktop" action="/position/quartier" method="get" data-entree>
        <div class="heros__ou">
          <?= composant('champ', ['nom' => 'q', 'label' => Format::typo('Où êtes-vous ?'), 'exemple' => 'ex. Riviera 2, pharmacie des Jardins', 'autocomplete' => 'off']) ?>
          <a class="bouton-lien heros__position" href="/position" data-ma-position><?= icone('viseur', 20) ?>Utiliser ma position</a>
        </div>
        <div class="heros__choix">
          <div class="heros__vehicule">
            <span class="champ__label" id="heros-vehicule">Votre véhicule</span>
            <?= composant('segment', ['nom' => 'vehicule', 'options' => $vehicules, 'decritPar' => 'heros-vehicule', 'hauteur' => 56]) ?>
          </div>
          <?= composant('menu-panne', ['nom' => 'panne', 'label' => 'Votre panne', 'pannes' => $pannes]) ?>
        </div>
        <div class="heros__envoi">
          <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Recherche…', 'attrs' => ['type' => 'submit']]) ?>
          <p class="t-petit t-secondaire">Sans compte, gratuit. Vous l’appelez directement.</p>
        </div>
        <div class="heros__distance">
          <?= icone('whatsapp') ?>
          <p><span class="t-petit t-secondaire"><?= e(Format::typo('Vous aidez quelqu’un à distance ?')) ?></span>
            <a class="lien" href="<?= e($lienWhatsapp) ?>" target="_blank" rel="noopener">Envoyez-lui le lien par WhatsApp</a></p>
        </div>
      </form>
    </div>
  </section>

  <div class="accueil__securite seul-mobile">
    <?= composant('note', ['type' => 'securite', 'message' => e('Garez-vous à l’abri, feux de détresse allumés, et restez derrière la glissière.')]) ?>
  </div>

  <!-- Récit minuté (S9) : trois temps -->
  <section class="recit" id="reconstitution" aria-labelledby="recit-titre">
    <div class="rubrique">
      <p class="t-etiquette">Reconstitution</p>
      <h2 class="t-titre-1" id="recit-titre">Mardi, 09:41,<br class="seul-mobile"> boulevard VGE.</h2>
    </div>
    <ol class="recit__temps" role="list">
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="09:41">09:41</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <?= composant('photo', [
              'classe' => 'temps__photo temps__photo--haute', 'legende' => 'Marcory · 09:41',
              'alt' => 'Aminata, en panne boulevard VGE, feux de détresse allumés.',
              'aProduire' => ['titre' => 'Photo à produire · IMG-07', 'texte' => 'Aminata, panne boulevard VGE, feux de détresse'],
          ]) ?>
          <p class="t-base">La voiture d’Aminata s’arrête au feu de Marcory. Elle ouvre allomécano.</p>
        </div>
      </li>
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="09:42">09:42</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <?= composant('ligne-classement', [
              'classe' => 'seul-mobile', 'nom' => 'Koffi Yao', 'portrait' => asset('img/accueil/koffi-rond-104.webp'),
              'specialites' => ['Électricité auto', 'Batterie'], 'note' => 4.8, 'avis' => 37,
          ]) ?>
          <div class="fragment seul-desktop">
            <p class="t-etiquette t-secondaire">10:14 · 4 mécanos · À vol d’oiseau</p>
            <?= composant('ligne-classement', [
                'nom' => 'Koffi Yao', 'portrait' => asset('img/accueil/koffi-rond-104.webp'), 'rang' => 1, 'distanceM' => 1200,
                'specialites' => ['Électricité auto', 'Batterie', 'Démarreur'], 'note' => 4.8, 'avis' => 37,
            ]) ?>
            <?= composant('ligne-classement', [
                'nom' => 'Adama Traoré', 'portrait' => asset('img/accueil/adama-rond-104.webp'), 'rang' => 2, 'distanceM' => 2400,
                'specialites' => ['Mécanique générale', 'Batterie', 'Moto'], 'note' => 4.6, 'avis' => 21,
            ]) ?>
          </div>
          <p class="t-base">Koffi est à 1,2<?= $nbsp ?>km. Elle l’appelle.</p>
        </div>
      </li>
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="09:58">09:58</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <?= composant('photo', [
              'classe' => 'temps__photo temps__photo--large', 'legende' => 'Riviera 2 · 09:58',
              'alt' => 'Le mécano arrive à moto, à Riviera.',
              'aProduire' => ['titre' => 'Photo à produire · IMG-09', 'texte' => 'Le mécano arrive à moto, Riviera'],
          ]) ?>
          <p class="t-base">Koffi est là. Batterie changée. Le prix, ils l’ont discuté entre eux.</p>
        </div>
      </li>
    </ol>
  </section>

  <!-- Visage vérifié : section sombre -->
  <section class="verification sur-sombre" aria-labelledby="verification-titre">
    <div class="rubrique verification__rubrique">
      <p class="t-etiquette">Vérification</p>
      <h2 class="t-titre-1" id="verification-titre">On a vérifié son visage, pas seulement son numéro.</h2>
    </div>
    <div class="verification__preuve">
      <div class="verification__comparaison">
        <?= composant('photo', [
            'image' => 'img/accueil/img-02', 'largeurs' => [302, 604], 'sizes' => '(min-width: 1024px) 296px, 156px',
            'largeur' => 604, 'hauteur' => 756, 'classe' => 'verification__portrait', 'alt' => 'Portrait de Koffi Yao, mécanicien.',
        ]) ?>
        <?= composant('specimen-cni', ['classe' => 'verification__piece']) ?>
      </div>
      <p class="t-etiquette verification__legende">Photo et pièce ·<br class="seul-mobile"> comparées par une personne</p>
    </div>
    <div class="verification__details">
      <p class="t-lead seul-desktop">Chaque mécano envoie sa pièce d’identité, ses justificatifs de métier et une photo de face. Une personne de l’équipe compare la photo à la pièce avant d’ouvrir son profil.</p>
      <ul class="verification__liste" role="list">
        <?php foreach (['Pièce d’identité contrôlée', 'Permis de conduire', 'Diplôme ou attestation de métier', 'Un avis après chaque intervention'] as $controle): ?>
          <li><?= composant('poincon', ['taille' => 18]) ?><span><?= e($controle) ?></span></li>
        <?php endforeach ?>
      </ul>
      <a class="verification__lien" href="/a-propos#verification">Comment on vérifie</a>
    </div>
  </section>

  <!-- Trombinoscope -->
  <section class="trombi" aria-labelledby="trombi-titre">
    <div class="rubrique">
      <p class="t-etiquette">Ils réparent près de vous</p>
      <h2 class="t-titre-1" id="trombi-titre">Des visages, pas des numéros.</h2>
    </div>
    <div class="trombi__defilement" role="region" aria-labelledby="trombi-titre" data-defilement>
      <ul class="trombi__liste" role="list">
        <?php foreach ($trombinoscope as $m): ?>
          <li class="trombi__mecano">
            <?= composant('photo', [
                'image' => 'img/accueil/' . $m['image'], 'largeurs' => [302, 604], 'sizes' => '(min-width: 1024px) 302px, 148px',
                'largeur' => 604, 'hauteur' => 756, 'alt' => 'Portrait de ' . $m['nom'] . '.',
            ]) ?>
            <p class="t-titre-3"><?= e($m['nom']) ?></p>
            <p class="t-etiquette t-secondaire">
              <span class="seul-mobile"><?= e($m['court']) ?><br><?= e($m['quartier']) ?></span>
              <span class="seul-desktop"><?= e($m['metiers'] . ' · ' . $m['quartier']) ?></span>
            </p>
          </li>
        <?php endforeach ?>
      </ul>
    </div>
  </section>

  <!-- Tableau des communes : nombres réels (RG-R3) -->
  <section class="communes" aria-labelledby="communes-titre" data-communes>
    <div class="rubrique">
      <p class="t-etiquette"><?= count($communes) ?> communes · mis à jour à <time datetime="<?= e($maintenant->format('c')) ?>"><?= e(Format::heure($maintenant)) ?></time></p>
      <h2 class="communes__titre" id="communes-titre"><?= e(Format::typo('Qui est disponible, et où ?')) ?></h2>
    </div>
    <div class="communes__tableau" id="communes-tableau">
      <?php $i = 0; foreach ($colonnes as $colonne): ?>
        <ul class="communes__colonne" role="list">
          <?php foreach ($colonne as $commune): ?>
            <li<?= $i++ >= $visiblesMobile ? ' class="communes__suite"' : '' ?>><?= composant('ligne-commune', $commune) ?></li>
          <?php endforeach ?>
        </ul>
      <?php endforeach ?>
    </div>
    <button type="button" class="bouton-lien communes__voir" aria-controls="communes-tableau" aria-expanded="false" hidden data-voir-communes>Voir les <?= count($communes) ?> communes</button>
    <p class="t-petit t-secondaire seul-desktop">Choisissez une commune pour lancer la recherche depuis là. Les chiffres comptent les mécanos vérifiés disponibles maintenant.</p>
  </section>

  <!-- Bande pro -->
  <section class="pro" aria-labelledby="pro-titre">
    <?= composant('photo', [
        'image' => 'img/accueil/img-13', 'largeurs' => [328, 656], 'sizes' => '328px', 'largeur' => 656, 'hauteur' => 820,
        'sources' => [['media' => '(min-width: 1024px)', 'image' => 'img/accueil/img-13-large', 'largeurs' => [519, 1038], 'sizes' => '519px']],
        'classe' => 'pro__photo', 'alt' => 'Un mécanicien en bleu de travail, souriant, devant son atelier.',
    ]) ?>
    <div class="pro__texte">
      <div class="rubrique pro__rubrique">
        <p class="t-etiquette pro__etiquette"><?= e(Format::typo('Vous êtes mécanicien ?')) ?></p>
        <h2 class="t-titre-1" id="pro-titre">Les pannes de votre quartier vous appellent directement.</h2>
      </div>
      <p class="t-lead t-secondaire seul-desktop">Créez votre dossier en quatre étapes. Une personne de l’équipe vérifie vos pièces, puis vous apparaissez sur la carte dès que vous vous rendez disponible.</p>
      <?= composant('bouton', ['libelle' => 'Devenir mécano vérifié', 'icone' => 'atelier', 'type' => 'secondaire', 'hauteur' => 56, 'href' => '/pro/devenir']) ?>
    </div>
  </section>

  <?= composant('urgences') ?>
</div>
