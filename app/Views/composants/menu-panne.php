<?php
/**
 * Menu de panne (D0, Figma 154:92) : champ de 56 avec le voyant de bord (48:214) de la panne choisie.
 * Sans JS : liste déroulante native. Avec JS (menu-panne.js) : bouton + liste d'options avec leur voyant.
 * @var string $nom
 * @var string $label
 * @var list<array{code: string, libelle: string, icone: string}> $pannes
 * @var ?string $valeur  code ; « je_ne_sais_pas » par défaut
 */
$id = 'champ-' . $nom;
$valeur ??= 'je_ne_sais_pas';
$choisie = array_values(array_filter($pannes, fn ($p) => $p['code'] === $valeur))[0] ?? $pannes[0] ?? null;
?>
<div class="champ menu-panne" data-menu-panne>
  <label class="champ__label" for="<?= e($id) ?>" id="<?= e($id) ?>-label"><?= e($label) ?></label>
  <select class="menu-panne__natif" id="<?= e($id) ?>" name="<?= e($nom) ?>">
    <?php foreach ($pannes as $p): ?><option value="<?= e($p['code']) ?>" data-icone="<?= e($p['icone']) ?>"<?= $p['code'] === ($choisie['code'] ?? '') ? ' selected' : '' ?>><?= e($p['libelle']) ?></option><?php endforeach ?>
  </select>
  <div class="menu-panne__js" hidden>
    <button type="button" class="menu-panne__bouton" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="<?= e($id) ?>-label <?= e($id) ?>-valeur" aria-controls="<?= e($id) ?>-liste">
      <span class="menu-panne__voyant" data-menu-icone><?= icone($choisie['icone'] ?? 'voyant-je-ne-sais-pas', 32) ?></span>
      <span class="menu-panne__valeur" id="<?= e($id) ?>-valeur"><?= e($choisie['libelle'] ?? '') ?></span>
      <?= icone('chevron', 20, 'menu-panne__chevron') ?>
    </button>
    <ul class="menu-panne__liste" id="<?= e($id) ?>-liste" role="listbox" aria-labelledby="<?= e($id) ?>-label" tabindex="-1" hidden>
      <?php foreach ($pannes as $p): ?>
        <li role="option" id="<?= e($id . '-' . $p['code']) ?>" data-valeur="<?= e($p['code']) ?>" aria-selected="<?= $p['code'] === ($choisie['code'] ?? '') ? 'true' : 'false' ?>"><?= icone($p['icone'], 32) ?><span><?= e($p['libelle']) ?></span></li>
      <?php endforeach ?>
    </ul>
  </div>
</div>
