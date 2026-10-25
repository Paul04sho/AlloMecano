<?php
/** @var list<array{id: int, prenom: string, nom: string, email: string, actif: bool, est_principal: bool, derniere_connexion_le: ?string}> $liste */
/** @var ?string $message */
/** @var ?string $motDePasseTemporaire */
?>
<section class="admins" aria-labelledby="admins-titre">
  <header class="admins__entete">
    <div>
      <p class="t-etiquette t-secondaire">Administration · Accès à l’équipe</p>
      <h1 class="admin__titre" id="admins-titre">Administrateurs</h1>
      <p class="t-base">Créez et gérez les accès de l’équipe. Chaque nouvel administrateur devra remplacer son mot de passe temporaire dès sa première connexion.</p>
    </div>
  </header>

  <?php if ($message): ?><p class="admins__message t-gras" role="status"><?= e($message) ?></p><?php endif ?>

  <?php if ($motDePasseTemporaire !== null): ?>
    <aside class="admins__temporaire" aria-labelledby="mot-de-passe-temporaire">
      <h2 class="t-titre-3" id="mot-de-passe-temporaire">Mot de passe temporaire</h2>
      <p class="t-texte">Copiez-le et transmettez-le à la personne. Il ne sera plus affiché après cette page.</p>
      <code class="admins__code"><?= e($motDePasseTemporaire) ?></code>
    </aside>
  <?php endif ?>

  <section class="admins__panneau" aria-labelledby="ajouter-admin">
    <h2 class="t-titre-2" id="ajouter-admin">Ajouter un administrateur</h2>
    <form method="post" action="/admin/administrateurs/creer" class="admins__formulaire">
      <?= csrf_champ() ?>
      <div class="admins__champs">
        <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'type' => 'text', 'requis' => true, 'max' => 60, 'autocomplete' => 'given-name']) ?>
        <?= composant('champ', ['nom' => 'nom', 'label' => 'Nom', 'type' => 'text', 'requis' => true, 'max' => 60, 'autocomplete' => 'family-name']) ?>
        <?= composant('champ', ['nom' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'requis' => true, 'max' => 190, 'autocomplete' => 'email']) ?>
      </div>
      <button type="submit" class="bouton bouton--principal bouton--44"><span class="bouton__libelle">Créer le compte</span></button>
    </form>
  </section>

  <section class="admins__liste" aria-labelledby="liste-admins">
    <header class="admins__liste-entete">
      <h2 class="t-titre-2" id="liste-admins">Comptes de l’équipe</h2>
      <p class="t-donnee t-secondaire"><?= count($liste) ?> compte<?= count($liste) > 1 ? 's' : '' ?></p>
    </header>
    <?php foreach ($liste as $compte): ?>
      <article class="admins__carte">
        <header class="admins__carte-entete">
          <div>
            <h3 class="t-titre-3"><?= e($compte['prenom'] . ' ' . $compte['nom']) ?></h3>
            <p class="t-texte"><?= e($compte['email']) ?></p>
          </div>
          <span class="admins__statut<?= $compte['actif'] ? '' : ' admins__statut--inactif' ?>">
            <?= $compte['est_principal'] ? 'Compte principal' : ($compte['actif'] ? 'Actif' : 'Désactivé') ?>
          </span>
        </header>
        <?php if (!$compte['est_principal']): ?>
          <form method="post" action="/admin/administrateurs/modifier" class="admins__edition">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= (int) $compte['id'] ?>">
            <div class="admins__champs">
              <div class="champ"><label for="admin-prenom-<?= (int) $compte['id'] ?>" class="champ__libelle">Prénom</label><input id="admin-prenom-<?= (int) $compte['id'] ?>" name="prenom" type="text" class="champ__saisie" value="<?= e($compte['prenom']) ?>" maxlength="60" autocomplete="given-name" required></div>
              <div class="champ"><label for="admin-nom-<?= (int) $compte['id'] ?>" class="champ__libelle">Nom</label><input id="admin-nom-<?= (int) $compte['id'] ?>" name="nom" type="text" class="champ__saisie" value="<?= e($compte['nom']) ?>" maxlength="60" autocomplete="family-name" required></div>
              <div class="champ"><label for="admin-email-<?= (int) $compte['id'] ?>" class="champ__libelle">Adresse e-mail</label><input id="admin-email-<?= (int) $compte['id'] ?>" name="email" type="email" class="champ__saisie" value="<?= e($compte['email']) ?>" maxlength="190" autocomplete="email" required></div>
            </div>
            <div class="admins__actions">
              <button type="submit" class="bouton bouton--contour bouton--44"><span class="bouton__libelle">Enregistrer</span></button>
            </div>
          </form>
          <form method="post" action="/admin/administrateurs/actif" class="admins__actions">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= (int) $compte['id'] ?>">
            <input type="hidden" name="actif" value="<?= $compte['actif'] ? '0' : '1' ?>">
            <button type="submit" class="bouton bouton--<?= $compte['actif'] ? 'danger' : 'secondaire' ?> bouton--44"><span class="bouton__libelle"><?= $compte['actif'] ? 'Désactiver' : 'Réactiver' ?></span></button>
          </form>
          <form method="post" action="/admin/administrateurs/mot-de-passe" class="admins__actions">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= (int) $compte['id'] ?>">
            <button type="submit" class="bouton bouton--contour bouton--44"><span class="bouton__libelle">Réinitialiser le mot de passe</span></button>
          </form>
        <?php else: ?>
          <p class="admins__principal t-texte">Le compte principal ne peut pas être désactivé ici. Gérez ses coordonnées depuis <a href="/admin/mon-compte">Mon compte</a>.</p>
        <?php endif ?>
      </article>
    <?php endforeach ?>
    <?php if ($liste === []): ?><p class="admins__vide t-texte">Aucun compte administrateur à afficher.</p><?php endif ?>
  </section>
</section>
