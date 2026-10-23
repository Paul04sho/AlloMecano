<?php
/**
 * X1 · Connexion (Figma 127:69 · erreur 128:69) : un seul écran pour le mécano (téléphone +225) et l'admin (e-mail) — RG-C1, RG-C5.
 * @var array<string, mixed> $saisie
 * @var ?string $erreur   message sous le mot de passe
 * @var bool $google      la connexion Google est configurée
 */
?>
<article class="connexion">
  <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-08', 'texte' => 'Mains qui serrent un écrou de roue'], 'alt' => 'Des mains serrent un écrou de roue.', 'legende' => 'L’ESPACE DE CEUX QUI RÉPARENT', 'classe' => 'connexion__photo']) ?>
  <form class="connexion__corps" method="post" action="/connexion" novalidate data-formulaire-connexion>
    <?= csrf_champ() ?>
    <input type="hidden" name="suivant" value="<?= e((string) ($saisie['suivant'] ?? '')) ?>">
    <h1 class="t-titre-1">Espace pro</h1>
    <div class="connexion__champs">
      <?= composant('champ', ['nom' => 'identifiant', 'label' => 'Téléphone ou e-mail', 'valeur' => (string) ($saisie['identifiant'] ?? ''), 'autocomplete' => 'username', 'inputmode' => 'email',
          'erreur' => $erreurs['identifiant'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'mot_de_passe', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password', 'erreur' => $erreur ?? null]) ?>
      <a class="connexion__oublie lien t-gras" href="/mot-de-passe">Mot de passe oublié ?</a>
    </div>
    <div class="connexion__actions">
      <?= composant('bouton', ['libelle' => 'Me connecter', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Connexion…', 'attrs' => ['type' => 'submit']]) ?>
      <?php if (!empty($google)): ?>
        <a class="bouton bouton--contour bouton--56 bouton--plein connexion__google" href="/connexion/google"><span class="connexion__g" aria-hidden="true">G</span><span class="bouton__libelle">Continuer avec Google</span></a>
      <?php endif ?>
    </div>
    <div class="connexion__inscription">
      <span class="t-petit t-secondaire">Pas encore inscrit ?</span>
      <a class="lien-icone t-gras" href="/pro/devenir"><span>Devenir mécano vérifié</span><?= icone('chevron', 20) ?></a>
    </div>
    <div class="connexion__egare">
      <p class="t-base">En panne ? Pas besoin de compte :</p>
      <a class="lien-icone t-gras" href="/position"><span>Trouver un mécano</span><?= icone('chevron', 20) ?></a>
    </div>
  </form>
</article>
