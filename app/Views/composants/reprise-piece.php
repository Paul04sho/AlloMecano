<?php
/**
 * Envoi d'une pièce depuis l'espace (M2a « Ajouter la photo de mon atelier », M2b « Reprendre ma photo ») : un bouton qui ouvre l'appareil photo (capture=environment),
 * et l'envoi part tout seul au choix de la photo. Sans JavaScript, le bouton « Envoyer » envoie le fichier choisi.
 * @var string $type       pièce à envoyer
 * @var string $libelle    libellé du bouton
 * @var string $icone
 * @var string $action     adresse d'envoi
 * @var string $bouton     principal | contour
 */
$bouton ??= 'principal';
?>
<form class="reprise-piece" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-reprise="<?= e($type) ?>">
  <?= csrf_champ() ?>
  <input class="reprise-piece__champ" type="file" name="fichier" id="reprise-<?= e($type) ?>" accept="image/jpeg,image/png,image/webp" capture="environment" data-reprise-champ>
  <label class="bouton bouton--<?= e($bouton) ?> bouton--56 bouton--plein" for="reprise-<?= e($type) ?>"><?= icone($icone) ?><span class="bouton__libelle"><?= e($libelle) ?></span><span class="bouton__voyant" aria-hidden="true"></span></label>
  <noscript><button type="submit" class="bouton bouton--secondaire bouton--48 bouton--plein"><span class="bouton__libelle">Envoyer la photo</span></button></noscript>
</form>
