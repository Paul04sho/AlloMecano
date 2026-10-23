<?php
/**
 * État de l'appel (Figma 269:1837 « Ça sonne », 269:1881 « En ligne », 270:1853 « 30 s sans réponse », 270:1922 « En attente de sa réponse ») :
 * voyant d'état + libellé Mono, ou « EN LIGNE » + chrono en Borne/Heure.
 * @var ?string $voyant  recherche | indisponible | erreur ; sans voyant : « EN LIGNE » + grand chrono
 * @var string $texte    « Ça sonne chez Koffi »
 * @var ?string $chrono  « 0:12 » : le JS le compte (data-chrono)
 * @var array<string, string> $attrs data-* en plus
 */
$attrs ??= [];
$classe = isset($attrs['class']) ? ' ' . $attrs['class'] : '';
unset($attrs['class']);
$html = '';
foreach ($attrs as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
?>
<?php if (!empty($voyant)): ?>
<p class="etat-appel t-etiquette<?= e($classe) ?>"<?= $html ?>><?= composant('voyant', ['etat' => $voyant]) ?><span data-etat-texte><?= e($texte) ?></span><?php if (!empty($chrono)): ?> · <span data-chrono><?= e($chrono) ?></span><?php endif ?></p>
<?php else: ?>
<p class="etat-appel etat-appel--ligne<?= e($classe) ?>"<?= $html ?>><span class="t-etiquette"><?= e($texte) ?></span><span class="appel__chrono" data-chrono><?= e((string) $chrono) ?></span></p>
<?php endif ?>
