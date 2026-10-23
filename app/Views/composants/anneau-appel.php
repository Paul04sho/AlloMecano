<?php
/**
 * Anneau de l'appel intégré (Figma 269:1827 à 269:1831, 269:1873) : deux anneaux radar en pointillés, anneau jaune du voyant, portrait de 144,
 * poinçon de 40. En ligne : un seul anneau béton de 164 (269:1873). Jamais de spinner rond : le radar suit les mêmes règles que C4b.
 * @var ?array{src: string, srcset: string, couleur: string, cadrage: string} $portrait
 * @var string $nom
 * @var string $phase sonne | ligne
 */
$phase ??= 'sonne';
?>
<div class="anneau-appel" data-anneau data-phase="<?= e($phase) ?>">
  <span class="anneau-appel__radar anneau-appel__radar--grand" aria-hidden="true"></span>
  <span class="anneau-appel__radar anneau-appel__radar--petit" aria-hidden="true"></span>
  <span class="anneau-appel__voyant" aria-hidden="true"></span>
  <span class="anneau-appel__ligne" aria-hidden="true"></span>
  <?php if (!empty($portrait)): ?>
    <img class="anneau-appel__portrait" src="<?= e($portrait['src']) ?>" srcset="<?= e($portrait['srcset']) ?>" sizes="144px" width="144" height="144" alt="Portrait de <?= e($nom) ?>"
         style="background:<?= e($portrait['couleur']) ?>;object-position:<?= e($portrait['cadrage']) ?>">
  <?php endif ?>
  <span class="anneau-appel__poincon"><?= composant('poincon', ['taille' => 40, 'surPhoto' => true]) ?></span>
</div>
