<?php
/**
 * Photo facultative (Figma 210:1812) : bouton « Ajouter une photo » (contour), aide en dessous. Le fichier est allégé et sans EXIF avant l'envoi (JS),
 * puis ré-encodé par le serveur (RG-S3). Sans JS, le fichier part tel quel et le serveur fait le travail.
 * @var string $nom
 * @var string $label
 * @var string $aide
 * @var ?string $erreur
 */
?>
<div class="champ-photo" data-champ-photo>
  <p class="champ__label" id="<?= e($nom) ?>-label"><?= e($label) ?></p>
  <input class="champ-photo__fichier" type="file" id="<?= e($nom) ?>" name="<?= e($nom) ?>" accept="image/jpeg,image/png,image/webp" aria-labelledby="<?= e($nom) ?>-label">
  <label class="bouton bouton--contour bouton--56 champ-photo__ajout" for="<?= e($nom) ?>" data-photo-ajout><?= icone('camera') ?><span class="bouton__libelle">Ajouter une photo</span></label>
  <p class="champ-photo__choisie" data-photo-choisie hidden aria-live="polite">
    <span class="t-donnee" data-photo-nom></span>
    <button type="button" class="lien t-gras" data-photo-retirer>Retirer</button>
  </p>
  <p class="champ__aide t-petit t-secondaire"><?= e($aide) ?></p>
  <?php if (!empty($erreur)): ?><p class="champ__message"><?= icone('triangle') ?><span><?= e($erreur) ?></span></p><?php endif ?>
</div>
