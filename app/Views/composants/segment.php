<?php
/**
 * Segment de formulaire (Figma 54:499, variante 54:478) : choix unique, options de même largeur,
 * l'option active sur fond inverse. Boutons radio natifs (clavier : flèches).
 * @var string $nom
 * @var array<string, string> $options valeur => libellé
 * @var ?string $valeur
 * @var string $libelle   nom du groupe pour les lecteurs d'écran (si pas de légende visible)
 * @var ?string $decritPar id de la légende visible
 * @var int $hauteur       44 (défaut) | 56
 */
$hauteur ??= 44;
$valeur ??= array_key_first($options);
?>
<div class="segment segment--plein<?= $hauteur === 56 ? ' segment--56' : '' ?>" role="radiogroup"<?= !empty($decritPar) ? ' aria-labelledby="' . e($decritPar) . '"' : ' aria-label="' . e($libelle ?? '') . '"' ?>>
  <?php foreach ($options as $v => $l): ?>
    <label><input type="radio" name="<?= e($nom) ?>" value="<?= e($v) ?>"<?= (string) $v === (string) $valeur ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
  <?php endforeach ?>
</div>
