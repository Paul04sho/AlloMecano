<?php
/**
 * Outil de développement : le mécano qui reçoit l'appel intégré (en attendant l'écran M5 du chantier mécano).
 * Ouvrir ceci dans un deuxième onglet, choisir le mécano appelé, puis appeler depuis le parcours client. Absent en production.
 * @var list<array{public_id: string, prenom: string, nom: string}> $mecanos
 */
?>
<main class="dev-mecano" data-dev-mecano>
  <p class="t-etiquette t-secondaire">Outil de développement · absent en production</p>
  <h1 class="t-titre-2">Mécano de test</h1>
  <p class="t-petit t-secondaire">Joue le mécano qui reçoit l’appel intégré. Choisissez qui vous êtes, laissez cet onglet ouvert, puis appelez ce mécano depuis le parcours client.</p>
  <label class="t-label" for="dev-mecano">Je suis
    <select id="dev-mecano" data-choix>
      <?php foreach ($mecanos as $m): ?><option value="<?= e($m['public_id']) ?>"><?= e($m['prenom'] . ' ' . $m['nom']) ?></option><?php endforeach ?>
    </select>
  </label>
  <p class="t-donnee" data-statut aria-live="polite">En attente d’un appel…</p>
  <section class="dev-mecano__appel" data-appel-entrant hidden>
    <p class="t-gras" data-resume></p>
    <div class="dev-mecano__boutons">
      <button type="button" class="bouton bouton--principal bouton--56" data-decrocher><span class="bouton__libelle">Décrocher</span></button>
      <button type="button" class="bouton bouton--contour bouton--56" data-refuser><span class="bouton__libelle">Refuser</span></button>
    </div>
  </section>
  <section class="dev-mecano__appel" data-en-ligne hidden>
    <p class="t-gras">En ligne · <span data-chrono>0:00</span></p>
    <div class="dev-mecano__boutons">
      <button type="button" class="bouton bouton--danger bouton--56" data-raccrocher><span class="bouton__libelle">Raccrocher</span></button>
    </div>
  </section>
  <section class="dev-mecano__appel" data-reponse hidden>
    <p class="t-gras">Appel terminé. Votre réponse au client :</p>
    <div class="dev-mecano__boutons">
      <button type="button" class="bouton bouton--principal bouton--56" data-issue="j_y_vais"><span class="bouton__libelle">J’y vais</span></button>
      <button type="button" class="bouton bouton--contour bouton--56" data-issue="pas_pour_moi"><span class="bouton__libelle">Pas pour moi</span></button>
    </div>
  </section>
  <audio data-audio autoplay playsinline></audio>
</main>
