<?php
/**
 * Panneau des voyants (Figma 54:391) : bitume, coupe M, 2 colonnes de tuiles de 64 (54:390), « Je ne sais pas »
 * pleine largeur. Choix unique (radios). Choc ou accident → note Danger au-dessus de tout (écran C3).
 * @var string $nom
 * @var list<array{code: string, libelle: string, danger: bool}> $pannes
 * @var ?string $valeur
 * @var ?string $erreur
 */
use App\Core\Format;
?>
<fieldset class="panneau-voyants"<?= !empty($erreur) ? ' aria-describedby="' . e($nom) . '-erreur"' : '' ?>>
  <legend class="t-etiquette"><?= e(Format::typo('Quelle est la panne ?')) ?></legend>
  <div class="voyants">
    <?php foreach ($pannes as $p): ?>
      <label class="tuile-voyant<?= $p['code'] === 'je_ne_sais_pas' ? ' tuile-voyant--large' : '' ?>">
        <input type="radio" name="<?= e($nom) ?>" value="<?= e($p['code']) ?>"<?= $p['code'] === ($valeur ?? null) ? ' checked' : '' ?><?= $p['danger'] ? ' data-danger' : '' ?>>
        <span><?= composant('voyant-de-bord', ['code' => $p['code']]) ?><?= e($p['libelle']) ?></span>
      </label>
    <?php endforeach ?>
  </div>
  <?php if (!empty($erreur)): ?><p class="panneau-voyants__erreur" id="<?= e($nom) ?>-erreur"><?= icone('triangle', 20) ?><span><?= e($erreur) ?></span></p><?php endif ?>
</fieldset>
