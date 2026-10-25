<?php
/** @var array{id: int, prenom: string, nom: string, email: string, est_principal: bool, actif: bool} $compte */
/** @var ?string $message */
?>
<section class="admins admins--profil" aria-labelledby="mon-compte-titre">
  <header class="admins__entete">
    <div>
      <p class="t-etiquette t-secondaire">Administration · Vos informations</p>
      <h1 class="admin__titre" id="mon-compte-titre">Mon compte</h1>
      <p class="t-base">Modifiez votre nom ou votre adresse de connexion.</p>
    </div>
  </header>
  <?php if ($message): ?><p class="admins__message t-gras" role="status"><?= e($message) ?></p><?php endif ?>
  <section class="admins__panneau" aria-labelledby="coordonnees-titre">
    <h2 class="t-titre-2" id="coordonnees-titre">Coordonnées</h2>
    <form method="post" action="/admin/mon-compte" class="admins__formulaire">
      <?= csrf_champ() ?>
      <div class="admins__champs">
        <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'type' => 'text', 'valeur' => $compte['prenom'], 'requis' => true, 'max' => 60, 'autocomplete' => 'given-name']) ?>
        <?= composant('champ', ['nom' => 'nom', 'label' => 'Nom', 'type' => 'text', 'valeur' => $compte['nom'], 'requis' => true, 'max' => 60, 'autocomplete' => 'family-name']) ?>
        <?= composant('champ', ['nom' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'valeur' => $compte['email'], 'requis' => true, 'max' => 190, 'autocomplete' => 'email']) ?>
      </div>
      <button type="submit" class="bouton bouton--principal bouton--44"><span class="bouton__libelle">Enregistrer mes coordonnées</span></button>
    </form>
  </section>
  <section class="admins__panneau admins__panneau--ligne" aria-labelledby="securite-titre">
    <div>
      <h2 class="t-titre-3" id="securite-titre">Mot de passe</h2>
      <p class="t-texte">Choisissez un mot de passe personnel et gardez-le confidentiel.</p>
    </div>
    <a class="bouton bouton--contour bouton--44" href="/admin/mon-compte/mot-de-passe"><span class="bouton__libelle">Changer mon mot de passe</span></a>
  </section>
</section>
