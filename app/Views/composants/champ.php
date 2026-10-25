<?php
/**
 * Champ (Figma 53:230).
 * @var string $nom           name et id
 * @var string $label
 * @var string $type          text | tel | email | password | number | textarea
 * @var ?string $valeur
 * @var ?string $aide
 * @var ?string $erreur       message qui dit quoi corriger
 * @var ?string $exemple      placeholder « ex. … » (jamais seul : le label est toujours là)
 * @var ?string $suffixe      « FCFA »
 * @var bool $requis
 * @var bool $desactive
 * @var ?int $max             longueur max (affiche le compteur pour un textarea, sauf compteur => false)
 * @var int $lignes            hauteur d'un textarea en lignes (3 par défaut)
 * @var bool $compteur
 * @var ?string $autocomplete
 * @var ?string $inputmode
 * @var ?string $liste        id d'une <datalist> de suggestions (quartier de l'atelier, M1b)
 * @var bool $labelCache  libellé réservé aux lecteurs d'écran (un titre visible tient lieu d'étiquette)
 */
$type ??= 'text';
$id = 'champ-' . $nom;
$classes = 'champ' . (!empty($erreur) ? ' champ--erreur' : '') . (!empty($desactive) ? ' champ--desactive' : '');
$decrit = trim((!empty($aide) ? $id . '-aide ' : '') . (!empty($erreur) ? $id . '-erreur' : ''));
$attrs = 'id="' . e($id) . '" name="' . e($nom) . '" class="champ__saisie"'
    . (!empty($exemple) ? ' placeholder="' . e($exemple) . '"' : '')
    . (!empty($liste) ? ' list="' . e($liste) . '"' : '')
    . (!empty($requis) ? ' required' : '')
    . (!empty($desactive) ? ' disabled' : '')
    . (!empty($max) ? ' maxlength="' . (int) $max . '"' : '')
    . ($decrit !== '' ? ' aria-describedby="' . e($decrit) . '"' : '')
    . (!empty($erreur) ? ' aria-invalid="true"' : '');
if ($type === 'tel') {
    $attrs .= ' inputmode="tel" autocomplete="' . e($autocomplete ?? 'tel-national') . '" data-format="telephone"';
} else {
    $attrs .= !empty($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '';
    $attrs .= !empty($inputmode) ? ' inputmode="' . e($inputmode) . '"' : '';
}
?>
<div class="<?= $classes ?>">
  <label class="champ__label<?= !empty($labelCache) ? ' sr-only' : '' ?>" for="<?= e($id) ?>"><?= e($label) ?></label>
  <div class="champ__boite">
    <?php if ($type === 'tel'): ?><span class="champ__prefixe" aria-hidden="true">+225</span><?php endif ?>
    <?php if ($type === 'textarea'): ?>
      <textarea <?= $attrs ?> rows="<?= (int) ($lignes ?? 3) ?>" style="--lignes:<?= (int) ($lignes ?? 3) ?>"><?= e($valeur ?? '') ?></textarea>
    <?php else: ?>
      <input type="<?= e($type) ?>" value="<?= e($valeur ?? '') ?>" <?= $attrs ?>>
    <?php endif ?>
    <?php if (!empty($suffixe)): ?><span class="champ__suffixe"><?= e($suffixe) ?></span><?php endif ?>
    <?php if ($type === 'password'): ?>
      <button type="button" class="champ__action" data-afficher-mdp aria-controls="<?= e($id) ?>" aria-label="Afficher le mot de passe"><?= icone('oeil') ?></button>
    <?php endif ?>
  </div>
  <?php if (!empty($erreur)): ?>
    <p class="champ__message" id="<?= e($id) ?>-erreur"><?= icone('triangle') ?><span><?= e($erreur) ?></span></p>
  <?php endif ?>
  <?php if (!empty($aide)): ?><p class="champ__aide" id="<?= e($id) ?>-aide"><?= e($aide) ?></p><?php endif ?>
  <?php if ($type === 'textarea' && !empty($max) && ($compteur ?? true)): ?><span class="champ__compteur" data-compteur="<?= e($id) ?>">0 / <?= (int) $max ?></span><?php endif ?>
</div>
