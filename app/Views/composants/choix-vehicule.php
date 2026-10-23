<?php
/**
 * Tuiles véhicule (Figma 54:293) : 108 × 88, profil 40 + libellé. Sélection : fond bitume (inverse), picto et
 * texte béton. Choix unique (radios). Le véhicule filtre strictement la recherche (RG-R2).
 * @var string $nom
 * @var array<string, string> $vehicules code => libellé
 * @var ?string $valeur
 * @var string $legende
 */ ?>
<fieldset class="choix-vehicule">
  <legend class="t-etiquette t-secondaire"><?= e($legende) ?></legend>
  <div class="vehicules">
    <?php foreach ($vehicules as $code => $libelle): ?>
      <label class="tuile-vehicule"><input type="radio" name="<?= e($nom) ?>" value="<?= e($code) ?>"<?= $code === ($valeur ?? null) ? ' checked' : '' ?>><span><?= icone('vehicule-' . $code, 40) ?><?= e($libelle) ?></span></label>
    <?php endforeach ?>
  </div>
</fieldset>
