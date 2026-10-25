<?php
/**
 * Curseur de rayon (Figma 97:318) : quatre crans, 2 · 5 · 10 · 20 km. Quatre boutons radio natifs (flèches du clavier, sans JavaScript),
 * dessinés comme le marquage du Figma : segments encre jusqu'au pouce, filet après.
 * @var string $nom
 * @var int $valeur
 * @var list<int> $rayons
 */
?>
<fieldset class="curseur-rayon" data-curseur-rayon>
  <legend class="curseur-rayon__titre">
    <span class="t-label">Jusqu’où vous vous déplacez</span>
    <span class="curseur-rayon__valeur" aria-hidden="true"><span class="t-borne-l" data-rayon-valeur><?= (int) $valeur ?></span><span class="t-etiquette">km</span></span>
  </legend>
  <div class="curseur-rayon__piste" style="--i: <?= (int) array_search((int) $valeur, $rayons, true) ?>">
    <span class="curseur-rayon__marquage" aria-hidden="true"></span>
    <span class="curseur-rayon__pouce" aria-hidden="true"></span>
    <?php foreach ($rayons as $i => $km): ?>
      <label class="curseur-rayon__cran" style="--cran: <?= (int) $i ?>">
        <input type="radio" name="<?= e($nom) ?>" value="<?= (int) $km ?>"<?= (int) $km === (int) $valeur ? ' checked' : '' ?> data-cran="<?= (int) $i ?>">
        <span class="t-etiquette"><?= (int) $km ?> km</span>
      </label>
    <?php endforeach ?>
  </div>
</fieldset>
