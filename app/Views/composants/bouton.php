<?php
/**
 * Bouton (Figma 53:148).
 * @var string $libelle
 * @var string $type      principal | secondaire | contour | contour-beton | danger
 * @var ?string $icone    nom dans le sprite (viseur, telephone, whatsapp…)
 * @var ?string $href     si présent : rend un <a>
 * @var int $hauteur      64 | 56 | 52 | 44
 * @var bool $plein       pleine largeur
 * @var bool $desactive
 * @var ?string $chargement libellé affiché pendant le chargement (« Recherche… »), lu par le JS
 * @var array<string,string> $attrs attributs en plus (id, name, value, type, data-*)
 */
$type ??= 'principal';
$hauteur ??= 56;
$classes = ['bouton', 'bouton--' . $type];
if ($hauteur !== 56) { $classes[] = 'bouton--' . $hauteur; }
if (!empty($plein)) { $classes[] = 'bouton--plein'; }
$attrs ??= [];
if (!empty($chargement)) { $attrs['data-chargement'] = $chargement; }
$balise = isset($href) ? 'a' : 'button';
if ($balise === 'button') { $attrs['type'] ??= 'button'; }
if (!empty($desactive)) { $balise === 'a' ? $attrs['aria-disabled'] = 'true' : $attrs['disabled'] = 'disabled'; }
$html = '';
foreach ($attrs as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<<?= $balise ?> class="<?= e(implode(' ', $classes)) ?>"<?= isset($href) ? ' href="' . e($href) . '"' : '' ?><?= $html ?>>
  <?php if (!empty($icone)): ?><?= icone($icone) ?><?php endif ?>
  <span class="bouton__libelle"><?= e($libelle) ?></span>
  <span class="bouton__voyant" aria-hidden="true"></span>
</<?= $balise ?>>
