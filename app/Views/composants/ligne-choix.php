<?php
/**
 * Ligne de choix (Figma 54:467) : 56, pleine largeur, filet. Radio rond ou case de 24 (coché = bitume).
 * Les cases de consentement ne sont JAMAIS pré-cochées.
 * @var string $nom
 * @var string $valeur
 * @var string $libelle     texte (échappé ici)
 * @var ?array{libelle: string, href: string, bloc?: bool} $lien  lien souligné dans la ligne (« les CGU », « Ce que nous vérifions ») : à la suite du texte, ou sur sa propre ligne si bloc
 * @var string $type        radio | checkbox
 * @var bool $coche
 * @var array<string,string> $attrs attributs en plus sur l'input (data-*)
 */
$type ??= 'radio';
$html = '';
foreach (array_filter($attrs ?? [], fn ($v) => $v !== null) as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<label class="ligne-choix"><input type="<?= e($type) ?>" name="<?= e($nom) ?>" value="<?= e($valeur) ?>"<?= !empty($coche) ? ' checked' : '' ?><?= $html ?>><span><?= e($libelle) ?><?php if (!empty($lien)): ?><?= !empty($lien['bloc']) ? '<br>' : ' ' ?><a class="lien" href="<?= e($lien['href']) ?>"><?= e($lien['libelle']) ?></a><?php endif ?></span></label>
