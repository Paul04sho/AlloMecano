<?php
/**
 * M1f · Activer les appels (Figma 272:1803) : la dernière étape après la validation du dossier. Trois réglages, une seule fois :
 * installer l'app sur l'écran d'accueil, autoriser les notifications (le téléphone ne sonne pas app fermée sans elles, RG-A2), faire un essai de sonnerie.
 * « C'est prêt » reste grisé tant que les trois ne sont pas faits ; sans Internet, les clients appellent sur le numéro du mécano (RG-A2).
 * @var ?string $cle      clé publique VAPID ; vide : les notifications ne sont pas configurées sur ce serveur
 * @var bool $premiere    première visite (le retour mène au tableau de bord en différant l'étape) ; sinon retour au profil
 * @var string $retour
 */
?>
<div class="activer" data-activer data-cle="<?= e((string) $cle) ?>">
  <header class="activer__barre">
    <a class="activer__retour" href="<?= e($retour) ?>" aria-label="Retour"><?= icone('retour', 24) ?></a>
    <p class="t-etiquette t-secondaire">Dernière étape · 2 min</p>
  </header>
  <div class="activer__contenu">
    <h1 class="t-titre-1">Recevez les appels des clients</h1>
    <p class="t-base t-secondaire">Les clients vous appellent depuis allomécano, par Internet&#8239;: c’est gratuit pour vous et pour eux. Trois réglages, une seule fois.</p>
    <ol class="activer__reglages" role="list">
      <?= composant('reglage-appel', ['cle' => 'installer', 'etat' => 'now', 'titre' => 'Installer allomécano sur l’écran d’accueil',
          'texte' => 'Pour ouvrir l’app d’un geste, comme une application.', 'fait' => 'Fait · icône ajoutée', 'libelle' => 'Installer', 'icone' => 'installer']) ?>
      <?= composant('reglage-appel', ['cle' => 'notifications', 'etat' => 'todo', 'titre' => 'Autoriser les notifications',
          'texte' => 'Sans ça, votre téléphone ne sonne pas quand l’app est fermée.', 'fait' => 'Fait · notifications autorisées', 'libelle' => 'Autoriser', 'icone' => 'cloche']) ?>
      <?= composant('reglage-appel', ['cle' => 'essai', 'etat' => 'todo', 'titre' => 'Faire un essai de sonnerie',
          'texte' => 'On fait sonner votre téléphone pour vérifier.', 'fait' => 'Fait · votre téléphone a sonné', 'libelle' => 'Faire sonner mon téléphone', 'icone' => 'haut-parleur']) ?>
    </ol>
    <?= composant('note', ['type' => 'info', 'message' => 'Gardez les données mobiles activées. Sans Internet, les clients vous appellent sur votre numéro, comme d’habitude.']) ?>
    <p class="activer__reste t-petit t-secondaire" data-reste aria-live="polite">Terminez les 3 réglages pour continuer.</p>
  </div>
  <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'C’est prêt', 'type' => 'secondaire', 'hauteur' => 56, 'plein' => true, 'icone' => 'coche', 'desactive' => true, 'chargement' => 'Un instant…', 'attrs' => ['data-pret' => '']])]) ?>
</div>
