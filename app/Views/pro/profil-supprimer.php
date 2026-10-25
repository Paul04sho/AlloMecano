<?php
/**
 * M4 · Supprimer mon compte (RG-C6 ; pas dessiné dans Figma, le texte du lien est celui de M4) : on tape son nom pour confirmer.
 * La fiche est retirée, les données personnelles anonymisées, les pièces effacées ; les avis restent, sans nom.
 * @var array<string, mixed> $f
 * @var ?string $erreur
 * @var string $saisie
 */
?>
<div class="profil-modifier">
  <header class="activer__barre">
    <a class="activer__retour" href="/pro/profil" aria-label="Retour à mon profil"><?= icone('retour', 24) ?></a>
    <p class="t-etiquette t-secondaire">Mon profil · Supprimer mon compte</p>
  </header>
  <form class="profil-modifier__formulaire" method="post" action="/pro/profil/supprimer" novalidate>
    <?= csrf_champ() ?>
    <h1 class="t-titre-1">Supprimer mon compte</h1>
    <?= composant('note', ['type' => 'erreur', 'message' => 'Votre fiche et vos avis seront effacés. Ce n’est pas réversible.']) ?>
    <?= composant('champ', ['nom' => 'nom', 'label' => 'Tapez votre nom pour confirmer', 'valeur' => $saisie, 'erreur' => $erreur, 'autocomplete' => 'off', 'exemple' => $f['nom_complet'], 'aide' => 'Il faudra taper votre nom pour confirmer.']) ?>
    <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'Supprimer mon compte', 'type' => 'danger', 'hauteur' => 56, 'plein' => true, 'icone' => 'triangle', 'chargement' => 'Suppression…', 'attrs' => ['type' => 'submit']])]) ?>
  </form>
</div>
