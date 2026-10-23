<?php
/**
 * Bandeau de preuve (Figma 76:1219) : poinçon + « Visage vérifié le 12 mars 2026, comparé à sa pièce d'identité. » (RG-DO8).
 * Le poinçon s'imprime une fois par session et par mécano (moment 5).
 * @var string $date « 12 mars 2026 »
 */
use App\Core\Format;
?>
<p class="bandeau-preuve sur-sombre">
  <span class="bandeau-preuve__poincon" data-poincon><?= composant('poincon', ['taille' => 22]) ?></span>
  <span class="bandeau-preuve__texte"><?= e(Format::typo('Visage vérifié le ' . $date . ', comparé à sa pièce d’identité.')) ?></span>
</p>
