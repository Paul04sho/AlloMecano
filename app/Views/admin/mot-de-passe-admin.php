<?php
/** @var bool $force */
/** @var bool $aUnMotDePasse */
/** @var ?string $message */
?>
<section class="admins admins--profil" aria-labelledby="mot-de-passe-titre">
  <header class="admins__entete">
    <div>
      <p class="t-etiquette t-secondaire">Administration · Sécurité du compte</p>
      <h1 class="admin__titre" id="mot-de-passe-titre"><?= $force ? 'Choisissez votre mot de passe' : 'Changer mon mot de passe' ?></h1>
      <p class="t-base"><?= $force ? 'Avant d’ouvrir l’administration, remplacez le mot de passe temporaire qui vous a été transmis.' : 'Saisissez votre mot de passe actuel, puis choisissez-en un nouveau.' ?></p>
    </div>
  </header>
  <?php if ($message): ?><p class="admins__message t-gras" role="status"><?= e($message) ?></p><?php endif ?>
  <section class="admins__panneau" aria-labelledby="nouveau-mot-de-passe">
    <h2 class="t-titre-2" id="nouveau-mot-de-passe">Nouveau mot de passe</h2>
    <form method="post" action="/admin/mon-compte/mot-de-passe" class="admins__formulaire">
      <?= csrf_champ() ?>
      <?php if (!$force && $aUnMotDePasse): ?>
        <?= composant('champ', ['nom' => 'mot_de_passe_actuel', 'label' => 'Mot de passe actuel', 'type' => 'password', 'requis' => true, 'autocomplete' => 'current-password']) ?>
      <?php endif ?>
      <?= composant('champ', ['nom' => 'mot_de_passe', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'requis' => true, 'aide' => '8 caractères au minimum, dont 1 chiffre.', 'autocomplete' => 'new-password']) ?>
      <?= composant('champ', ['nom' => 'confirmation', 'label' => 'Confirmer le nouveau mot de passe', 'type' => 'password', 'requis' => true, 'autocomplete' => 'new-password']) ?>
      <div class="admins__actions">
        <button type="submit" class="bouton bouton--principal bouton--44"><span class="bouton__libelle">Enregistrer le mot de passe</span></button>
        <?php if (!$force): ?><a href="/admin/mon-compte" class="admins__retour">Annuler</a><?php endif ?>
      </div>
    </form>
  </section>
</section>
