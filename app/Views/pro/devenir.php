<?php
/**
 * M0 · Accueil du parcours mécanicien. Maquette mobile fournie en référence.
 * Les destinations M1 (inscription) et M2 (espace mécanicien) seront activées dans les écrans suivants.
 */
use App\Core\Format;
?>
<div class="pro-devenir">
  <section class="m0-hero" aria-labelledby="m0-titre">
    <?= composant('photo', [
        'image' => 'img/accueil/img-01',
        'largeurs' => [780, 1264],
        'sizes' => '(min-width: 1024px) 56vw, 100vw',
        'largeur' => 1264,
        'hauteur' => 848,
        'classe' => 'm0-hero__photo',
        'prioritaire' => true,
        'alt' => 'Un mécanicien intervient sur une voiture, capot ouvert, dans un quartier d’Abidjan.',
        'legende' => 'Riviera 3 · 15:20',
    ]) ?>
    <div class="m0-hero__contenu">
      <h1 class="t-titre-1" id="m0-titre"><?= e(Format::typo('Les pannes de votre quartier vous appellent.')) ?></h1>
      <p class="t-base">Inscription gratuite. Dossier vérifié sous 48 h. Les clients vous appellent directement ; le prix, vous en discutez entre vous.</p>
      <div class="m0-hero__actions">
        <?= composant('bouton', [
            'libelle' => 'Créer mon dossier',
            'type' => 'principal',
            'hauteur' => 56,
            'plein' => true,
            'href' => '/pro/inscription/1',
        ]) ?>
        <a class="m0-lien-compte t-petit" href="/pro">J’ai déjà un compte</a>
      </div>
    </div>
  </section>

  <section class="m0-section m0-journee" id="journee" aria-labelledby="m0-journee-titre">
    <div class="m0-rubrique">
      <p class="t-etiquette">RECONSTITUTION</p>
      <h2 class="t-titre-2" id="m0-journee-titre">Une journée, de 07:30 à 18:00.</h2>
    </div>
    <ol class="m0-timeline" role="list">
      <li class="m0-timeline__item">
        <time class="m0-timeline__heure t-borne-heure" datetime="07:30">07:30</time>
        <div class="m0-timeline__contenu">
          <?= composant('photo', [
              'image' => 'img/accueil/img-02',
              'largeurs' => [302, 604],
              'sizes' => '(min-width: 1024px) 320px, 100vw',
              'largeur' => 604,
              'hauteur' => 756,
              'classe' => 'm0-journee__photo m0-journee__photo--portrait',
              'alt' => 'Un mécanicien se prépare à intervenir dans son quartier.',
              'legende' => 'Riviera 3 · 07:30',
          ]) ?>
          <p class="t-base">Je me rends disponible.</p>
        </div>
      </li>
      <li class="m0-timeline__item">
        <time class="m0-timeline__heure t-borne-heure" datetime="10:12">10:12</time>
        <div class="m0-timeline__contenu">
          <article class="m0-notification" aria-label="Exemple de demande reçue">
            <p class="t-etiquette">APPEL REÇU · RIVIERA 2</p>
            <h3 class="t-titre-3">Aminata</h3>
            <p class="t-petit">Batterie · Voiture</p>
            <p class="t-etiquette">RIVIERA 2 · À 1,4 KM</p>
            <div class="m0-notification__action t-petit"><?= icone('telephone', 18) ?><span>Rappeler</span></div>
          </article>
          <p class="t-base">Un client à 1,4 km t’appelle : batterie, Riviera 2.</p>
        </div>
      </li>
      <li class="m0-timeline__item">
        <time class="m0-timeline__heure t-borne-heure" datetime="10:30">10:30</time>
        <div class="m0-timeline__contenu">
          <?= composant('photo', [
              'classe' => 'm0-journee__photo m0-journee__photo--large',
              'legende' => 'Riviera 2 · 10:30',
              'alt' => 'Illustration à remplacer par la photo du mécanicien arrivé sur place.',
              'aProduire' => ['titre' => 'Photo à produire · IMG-09', 'texte' => 'Le mécano arrive à moto, Riviera'],
          ]) ?>
          <p class="t-base">Sur place.</p>
        </div>
      </li>
      <li class="m0-timeline__item">
        <time class="m0-timeline__heure t-borne-heure" datetime="18:00">18:00</time>
        <div class="m0-timeline__contenu">
          <div class="m0-fin-journee">
            <p class="t-etiquette">VOUS ÊTES INDISPONIBLE</p>
            <h3 class="t-titre-3">Les clients ne vous voient pas.</h3>
            <span class="m0-fin-journee__bouton t-petit"><?= icone('coche', 18) ?>Me rendre disponible</span>
          </div>
          <p class="t-base">Je me rends indisponible. Je décide.</p>
        </div>
      </li>
    </ol>
  </section>

  <section class="m0-section m0-documents" id="documents" aria-labelledby="m0-documents-titre">
    <div class="m0-rubrique">
      <p class="t-etiquette">CE QU’ON ATTEND DE VOUS</p>
      <h2 class="t-titre-2" id="m0-documents-titre">Six pièces, une par une.</h2>
    </div>
    <div class="m0-pieces">
      <div class="m0-pieces__diagramme" aria-hidden="true">
        <svg viewBox="0 0 160 160" role="presentation">
          <path class="m0-pieces__ligne" d="M80 10V150M10 48L150 112M10 112L150 48"/>
          <path class="m0-pieces__hexagone" d="M80 15L122 39V87L80 111L38 87V39Z"/>
          <path class="m0-pieces__face" d="M80 15V63L122 39Z"/>
          <path class="m0-pieces__face m0-pieces__face--sombre" d="M80 63L122 39V87Z"/>
          <path class="m0-pieces__hexagone" d="M80 63L122 87L80 111L38 87Z"/>
          <circle class="m0-pieces__centre" cx="80" cy="63" r="13"/>
        </svg>
      </div>
      <div class="m0-piece m0-piece--1 t-micro"><span class="m0-piece__rang t-micro">1</span><span>CNI recto</span></div>
      <div class="m0-piece m0-piece--2 t-micro"><span class="m0-piece__rang">2</span><span>CNI verso</span></div>
      <div class="m0-piece m0-piece--3 t-micro"><span class="m0-piece__rang">3</span><span>Permis</span></div>
      <div class="m0-piece m0-piece--4 t-micro"><span class="m0-piece__rang">4</span><span>Diplôme ou attestation</span></div>
      <div class="m0-piece m0-piece--5 t-micro"><span class="m0-piece__rang">5</span><span>Photo de vous</span></div>
      <div class="m0-piece m0-piece--6 t-micro"><span class="m0-piece__rang">6</span><span>Photo de l’atelier <span class="m0-optionnel t-micro">facultatif</span></span></div>
    </div>
    <p class="m0-note-document t-petit">Chaque pièce est regardée par une personne.</p>
  </section>

  <section class="m0-section m0-disponibilite" id="disponibilite" aria-labelledby="m0-dispo-titre">
    <div class="m0-rubrique">
      <p class="t-etiquette">VOTRE INTERRUPTEUR</p>
      <h2 class="t-titre-2" id="m0-dispo-titre">Vous décidez quand on vous appelle.</h2>
    </div>
    <div class="m0-dispo-carte" data-dispo-carte>
      <div class="m0-dispo-carte__entete">
        <span class="m0-dispo-carte__point" aria-hidden="true"></span>
        <p class="t-etiquette" data-dispo-etiquette>VISIBLE À 10 KM AUTOUR DE VOUS</p>
      </div>
      <h3 class="t-titre-3" data-dispo-titre>Disponible depuis 07:32</h3>
      <button class="m0-dispo-carte__bouton t-petit" type="button" data-dispo-toggle aria-pressed="true">
        <?= icone('coche', 18) ?><span data-dispo-libelle>Passer indisponible</span>
      </button>
      <p class="t-micro m0-dispo-carte__aide" data-dispo-aide aria-live="polite">Disponible : les clients autour de vous vous voient.</p>
    </div>
    <p class="t-petit m0-disponibilite__note"><strong>Disponible :</strong> les clients autour de vous vous voient.<br><strong>Indisponible :</strong> personne ne vous dérange.</p>
  </section>

  <section class="m0-temoignage sur-sombre" aria-labelledby="m0-temoignage-titre">
    <div class="m0-rubrique">
      <p class="t-etiquette">TÉMOIGNAGE DE DÉMONSTRATION</p>
      <h2 class="sr-only" id="m0-temoignage-titre">Témoignage d’un mécanicien</h2>
    </div>
    <?= composant('photo', [
        'image' => 'img/accueil/img-13',
        'largeurs' => [328, 656],
        'sizes' => '(min-width: 1024px) 360px, 100vw',
        'largeur' => 656,
        'hauteur' => 820,
        'classe' => 'm0-temoignage__photo',
        'alt' => 'Portrait de démonstration d’un mécanicien devant son atelier.',
    ]) ?>
    <blockquote class="m0-temoignage__citation t-titre-3">« Avant, il fallait que les clients passent devant mon atelier. »</blockquote>
    <p class="t-titre-3">Serge Kouassi</p>
    <p class="t-etiquette m0-temoignage__metier">DIESEL ET UTILITAIRES · MARCORY</p>
    <p class="t-petit">Depuis son inscription, il reçoit des demandes de clients voisins.</p>
  </section>

  <section class="m0-section m0-engagements" aria-labelledby="m0-engagements-titre">
    <div class="m0-rubrique">
      <p class="t-etiquette">CE QU’ON ATTEND DE VOUS</p>
      <h2 class="t-titre-2" id="m0-engagements-titre">Tous nos engagements envers les clients.</h2>
    </div>
    <ul class="m0-engagements__liste" role="list">
      <li><span class="m0-engagements__icone"><?= icone('coche', 18) ?></span><span><strong class="t-petit">Le prix annoncé, respecté.</strong><span class="t-petit">Le prix se discute avant une intervention. Une fois annoncé, il ne change pas sans accord.</span></span></li>
      <li><span class="m0-engagements__icone"><?= icone('coche', 18) ?></span><span><strong class="t-petit">La ponctualité.</strong><span class="t-petit">Vous prévenez le client quand vous êtes en retard, en route ou arrivé.</span></span></li>
      <li><span class="m0-engagements__icone"><?= icone('coche', 18) ?></span><span><strong class="t-petit">La politesse.</strong><span class="t-petit">Un avis suit chaque intervention. Le respect compte.</span></span></li>
    </ul>
  </section>

  <section class="m0-section m0-faq" id="questions" aria-labelledby="m0-faq-titre">
    <div class="m0-rubrique">
      <p class="t-etiquette">QUESTIONS FRÉQUENTES</p>
      <h2 class="t-titre-2" id="m0-faq-titre">Avant de vous lancer</h2>
    </div>
    <div class="m0-faq__liste">
      <details>
        <summary class="t-petit">Combien ça coûte ?</summary>
        <p class="t-petit">L’inscription est gratuite. Vous discutez directement du prix de l’intervention avec le client.</p>
      </details>
      <details>
        <summary class="t-petit">Qui voit mon numéro ?</summary>
        <p class="t-petit">Votre numéro est communiqué lorsqu’un client choisit de vous contacter. Il n’est pas affiché publiquement sur la page d’accueil.</p>
      </details>
      <details>
        <summary class="t-petit">Je n’ai pas de diplôme, je peux m’inscrire ?</summary>
        <p class="t-petit">Oui. Une attestation signée par un patron d’atelier suffit. Elle remplace le diplôme dans votre dossier.</p>
      </details>
      <details>
        <summary class="t-petit">Que se passe-t-il si mon dossier est refusé ?</summary>
        <p class="t-petit">Vous êtes informé des pièces à corriger. Vous pouvez compléter votre dossier et le soumettre à nouveau.</p>
      </details>
    </div>
  </section>
</div>
