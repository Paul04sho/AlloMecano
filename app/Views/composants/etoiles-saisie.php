<?php
/**
 * Étoiles de saisie (Figma 87:1600) : 5 étoiles de 48 px, pleines jusqu'à la note ; la valeur en Borne/L à droite (« 4 SUR 5 »).
 * Cinq boutons radio : tactile, flèches du clavier, et ça marche sans JS.
 * @var string $nom      name des boutons radio
 * @var ?int $valeur     note choisie
 * @var ?string $erreur
 */
$valeur = (int) ($valeur ?? 0);
?>
<div class="etoiles-saisie<?= !empty($erreur) ? ' etoiles-saisie--erreur' : '' ?>" role="radiogroup" aria-label="Votre note sur 5"<?= !empty($erreur) ? ' aria-invalid="true" aria-describedby="' . e($nom) . '-erreur"' : '' ?>>
  <div class="etoiles-saisie__rangee">
    <?php for ($i = 5; $i >= 1; $i--): // ordre inversé dans le HTML : « les suivantes » se remplissent en CSS, sans JS ?>
      <input class="etoiles-saisie__champ" type="radio" name="<?= e($nom) ?>" id="<?= e($nom) ?>-<?= $i ?>" value="<?= $i ?>"<?= $valeur === $i ? ' checked' : '' ?>>
      <label class="etoiles-saisie__etoile" for="<?= e($nom) ?>-<?= $i ?>" title="<?= $i ?> sur 5">
        <svg width="48" height="48" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M7 1.63333L8.63333 5.01667L12.3083 5.48333L9.625 7.99167L10.325 11.6667L7 9.85833L3.675 11.6667L4.375 7.99167L1.69167 5.48333L5.36667 5.01667L7 1.63333Z"/></svg>
        <span class="sr-only"><?= $i ?> sur 5</span>
      </label>
    <?php endfor ?>
  </div>
  <p class="etoiles-saisie__valeur" data-valeur-note<?= $valeur === 0 ? ' hidden' : '' ?> aria-live="polite"><span class="t-borne-l"><?= $valeur ?></span><span class="t-etiquette">sur 5</span></p>
</div>
<?php if (!empty($erreur)): ?><p class="champ__message" id="<?= e($nom) ?>-erreur"><?= icone('triangle') ?><span><?= e($erreur) ?></span></p><?php endif ?>
