<?php
/**
 * Bulle d'aide (Figma 67:588) : étiquette bitume, Mono/Étiquette béton, pointe de 12 × 8 vers le bas.
 * @var string $texte
 * @var ?string $classe
 * @var array<string,string> $attrs
 */
$html = '';
foreach (array_filter($attrs ?? [], fn ($v) => $v !== null) as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<span class="bulle<?= !empty($classe) ? ' ' . e($classe) : '' ?>"<?= $html ?>><span class="bulle__texte t-etiquette"><?= e($texte) ?></span></span>
