<?php
/**
 * Étiquette « Vérifié » (Figma 49:175) : bloc de 24, poinçon de 14 et Mono/Étiquette ; jamais verte.
 * Variante « En attente » : contour et bande S10, seulement en aperçu pour le mécano (RG-DO8 : le poinçon n'est pas pour un compte non validé).
 * @var string $etat  verifie | attente
 */
$etat ??= 'verifie';
?>
<?php if ($etat === 'attente'): ?>
  <span class="etiquette-verifie etiquette-verifie--attente">En attente</span>
<?php else: ?>
  <span class="etiquette-verifie"><?= composant('poincon', ['taille' => 14]) ?>Vérifié</span>
<?php endif ?>
