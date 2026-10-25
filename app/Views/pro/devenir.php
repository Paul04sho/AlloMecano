<?php
/**
 * M0 · Devenir mécano vérifié (Figma 92:14) : l'offre faite au mécano, du héros à la FAQ. Page publique, mobile ; sur grand écran, une colonne centrée.
 * Ce qui est montré est exactement ce qui existe : la disponibilité (RG-D1), les six pièces (RG-DO2), la réponse sous 48 h (RG-DO3).
 */
use App\Core\Format;
use App\Services\Parametres;

$delai = Parametres::int('dossier.delai_annonce_h', 48);
$nbsp = Format::INSECABLE;
?>
<div class="pro devenir">
  <!-- Héros : photo IMG-10 (substitut IMG-01 en attendant la livraison), accroche, action -->
  <section class="devenir__heros" aria-labelledby="devenir-titre">
    <?= composant('photo', [
        'image' => 'img/accueil/img-01', 'largeurs' => [780, 1264], 'sizes' => '(min-width: 480px) 480px, 100vw', 'largeur' => 1264, 'hauteur' => 848, 'prioritaire' => true,
        'classe' => 'devenir__photo', 'alt' => 'À Riviera 3, un mécanicien répare le moteur d’un taxi orange, capot ouvert.', 'legende' => 'Riviera 3 · 15:20',
    ]) ?>
    <div class="devenir__accroche">
      <h1 class="t-display-xl" id="devenir-titre"><?= e(Format::typo('Les pannes de votre quartier vous appellent.')) ?></h1>
      <p class="t-lead"><?= e(Format::typo('Inscription gratuite. Dossier vérifié sous ' . $delai . $nbsp . 'h. Les clients vous appellent directement ; le prix, vous en discutez entre vous.')) ?></p>
      <?= composant('bouton', ['libelle' => 'Créer mon dossier', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'icone' => 'fichier', 'href' => '/pro/inscription/compte']) ?>
      <a class="lien devenir__compte t-gras" href="/connexion?suivant=<?= e(rawurlencode('/pro')) ?>">J’ai déjà un compte</a>
    </div>
  </section>

  <!-- Récit minuté (S9) : une journée de mécano -->
  <section class="devenir__section" aria-labelledby="journee-titre">
    <div class="rubrique">
      <p class="t-etiquette">Reconstitution</p>
      <h2 class="t-titre-1" id="journee-titre">Une journée, de 07:30 à 18:00.</h2>
    </div>
    <ol class="devenir__temps" role="list">
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="07:30">07:30</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <?= composant('photo', [
              'image' => 'img/accueil/img-13', 'largeurs' => [328, 656], 'sizes' => '264px', 'largeur' => 656, 'hauteur' => 438, 'classe' => 'temps__photo',
              'alt' => 'Un mécanicien ouvre allomécano sur son téléphone, à l’atelier, à Riviera 3.', 'legende' => 'Riviera 3 · 07:30',
          ]) ?>
          <p class="t-base">Je me rends disponible.</p>
        </div>
      </li>
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="10:12">10:12</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <div class="fragment-demande">
            <p class="t-etiquette t-secondaire">Appel reçu · 10:12</p>
            <p class="t-titre-3">Aminata</p>
            <p class="t-petit t-secondaire">Batterie · Voiture</p>
            <p class="t-donnee">Riviera 2 · à 1,4<?= $nbsp ?>km</p>
            <div class="fragment-demande__bouton" inert><?= composant('bouton', ['libelle' => 'Rappeler', 'type' => 'secondaire', 'hauteur' => 44, 'icone' => 'telephone']) ?></div>
          </div>
          <p class="t-base">Un client à 1,4<?= $nbsp ?>km m’appelle : batterie, Riviera 2.</p>
        </div>
      </li>
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="10:30">10:30</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <?= composant('photo', [
              'classe' => 'temps__photo', 'legende' => 'Riviera 2 · 10:30', 'alt' => 'Le mécano arrive à moto, à Riviera.',
              'aProduire' => ['titre' => 'Photo à produire · IMG-09', 'texte' => 'Le mécano arrive à moto, Riviera'],
          ]) ?>
          <p class="t-base">Sur place.</p>
        </div>
      </li>
      <li class="temps">
        <p class="temps__heure"><time class="t-borne-heure" datetime="18:00">18:00</time><span class="temps__marquage" aria-hidden="true"></span></p>
        <div class="temps__contenu">
          <?= composant('interrupteur-dispo', ['etat' => 'indisponible', 'demo' => true, 'bouton' => 'contour']) ?>
          <p class="t-base">Je me rends indisponible. Plus d’appels.</p>
        </div>
      </li>
    </ol>
  </section>

  <!-- Les six pièces -->
  <section class="devenir__section" aria-labelledby="pieces-titre">
    <div class="rubrique">
      <p class="t-etiquette">Ce qu’on vérifie</p>
      <h2 class="t-titre-1" id="pieces-titre">Six pièces, une par une.</h2>
    </div>
    <?= composant('ecrou-legende', []) ?>
    <p class="devenir__delai"><span class="t-titre-2">Réponse sous</span><span class="devenir__borne"><span class="t-borne-l"><?= (int) $delai ?></span><span class="t-etiquette">h</span></span></p>
    <?= composant('photo', [
        'classe' => 'devenir__photo-pieces', 'alt' => 'Des mains serrent un écrou de roue.',
        'aProduire' => ['titre' => 'Photo à produire · IMG-08', 'texte' => 'Mains qui serrent un écrou de roue'],
    ]) ?>
    <p class="t-base">Chaque pièce est regardée par une personne.</p>
  </section>

  <!-- Votre interrupteur -->
  <section class="devenir__section" aria-labelledby="interrupteur-titre">
    <div class="rubrique">
      <p class="t-etiquette">Votre interrupteur</p>
      <h2 class="t-titre-1" id="interrupteur-titre">Vous décidez quand on vous appelle.</h2>
    </div>
    <?= composant('interrupteur-dispo', ['etat' => 'disponible', 'demo' => true, 'zone' => '10' . $nbsp . 'km autour de Riviera 3', 'depuis' => '07:32']) ?>
    <div class="devenir__explication">
      <p class="t-base"><strong>Disponible</strong> : les clients autour de vous vous voient.</p>
      <p class="t-base"><strong>Indisponible</strong> : personne ne vous dérange.</p>
    </div>
  </section>

  <!-- Témoignage de démonstration : section sombre -->
  <section class="devenir__temoignage sur-sombre" aria-labelledby="temoignage-titre">
    <p class="t-etiquette" id="temoignage-titre">Témoignage de démonstration</p>
    <?= composant('photo', ['image' => 'img/accueil/img-05', 'largeurs' => [302, 604], 'sizes' => '264px', 'largeur' => 604, 'hauteur' => 756, 'classe' => 'devenir__portrait', 'alt' => 'Serge Kouassi, mécanicien diesel à Marcory Biétry.']) ?>
    <blockquote class="devenir__citation">
      <p class="t-citation">« Avant, il fallait que les clients passent devant mon atelier. »</p>
      <footer><p class="t-titre-3">Serge Kouassi</p><p class="t-etiquette">Diesel et utilitaires · Marcory Biétry</p></footer>
    </blockquote>
    <p class="t-micro">Maquette. En production, uniquement des citations vraies et signées.</p>
  </section>

  <!-- Trois engagements -->
  <section class="devenir__section devenir__section--sans-filet" aria-labelledby="engagements-titre">
    <div class="rubrique">
      <p class="t-etiquette">Ce qu’on attend de vous</p>
      <h2 class="t-titre-1" id="engagements-titre">Trois engagements envers les clients.</h2>
    </div>
    <ul class="devenir__engagements" role="list">
      <?= composant('ligne-engagement', ['titre' => 'Le prix annoncé, respecté', 'texte' => 'Le prix se discute avant. Une fois annoncé, il ne change pas.']) ?>
      <?= composant('ligne-engagement', ['titre' => 'La ponctualité', 'texte' => 'Vous dites quand vous arrivez. En retard, vous prévenez.']) ?>
      <?= composant('ligne-engagement', ['titre' => 'La politesse', 'texte' => 'Un avis suit chaque intervention.']) ?>
    </ul>
  </section>

  <!-- FAQ -->
  <section class="devenir__section devenir__section--sans-filet devenir__faq" aria-labelledby="faq-titre">
    <div class="rubrique">
      <p class="t-etiquette">Questions fréquentes</p>
      <h2 class="t-titre-1" id="faq-titre">Avant de vous lancer</h2>
    </div>
    <div class="devenir__accordeons">
      <?= composant('accordeon', ['question' => 'Combien ça coûte ?', 'reponse' => 'Rien. L’inscription est gratuite, et les appels des clients aussi.']) ?>
      <?= composant('accordeon', ['question' => 'Qui voit mon numéro ?', 'reponse' => 'Un client voit votre numéro seulement s’il choisit « Son numéro » ou WhatsApp. Avec l’appel intégré, il ne le voit pas.']) ?>
      <?= composant('accordeon', ['question' => 'Je n’ai pas de diplôme, je peux m’inscrire ?', 'reponse' => 'Oui. Une attestation signée par un patron d’atelier suffit. Elle remplace le diplôme dans votre dossier.', 'ouvert' => true]) ?>
      <?= composant('accordeon', ['question' => 'Que se passe-t-il si mon dossier est refusé ?', 'reponse' => 'Vous recevez le motif par e-mail et dans votre espace. Un refus est définitif ; vos pièces sont effacées au bout de 6 mois.']) ?>
    </div>
  </section>
</div>
