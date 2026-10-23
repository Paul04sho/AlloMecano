<?php
/**
 * Portrait de la fiche (Figma 76:1212 plein cadre mobile, 158:828 panneau 4:5) : photo chargée depuis sa couleur dominante,
 * bouton bitume 48 (retour ou fermer), puce « 1,2 km · Riviera 3 » en bas à droite.
 * @var ?array{src: string, srcset: string, couleur: string, cadrage: string} $photo
 * @var string $nom            lu par les lecteurs d'écran
 * @var string $mode           plein | panneau
 * @var string $retour         adresse du bouton retour (mode plein)
 * @var ?string $puce          « 1,2 km · Riviera 3 »
 */
$mode ??= 'plein';
?>
<div class="portrait-fiche portrait-fiche--<?= e($mode) ?>"<?= !empty($photo['couleur']) ? ' style="background:' . e($photo['couleur']) . '"' : '' ?>>
  <?php if (!empty($photo)): ?>
    <img src="<?= e($photo['src']) ?>" srcset="<?= e($photo['srcset']) ?>" sizes="<?= $mode === 'plein' ? '(min-width: 560px) 560px, 100vw' : '372px' ?>"
         alt="Portrait de <?= e($nom) ?>" decoding="async" fetchpriority="high" data-portrait
         style="object-position:<?= e($photo['cadrage']) ?>">
  <?php endif ?>
  <?php if ($mode === 'plein'): ?>
    <a class="bouton-icone bouton-icone--bitume portrait-fiche__bouton" href="<?= e($retour) ?>" data-retour aria-label="Retour"><?= icone('retour') ?></a>
  <?php else: ?>
    <button type="button" class="bouton-icone bouton-icone--bitume portrait-fiche__bouton" data-fermer-fiche aria-label="Fermer la fiche"><?= icone('fermer') ?></button>
  <?php endif ?>
  <?php if (!empty($puce)): ?><span class="portrait-fiche__puce"><?= e($puce) ?></span><?php endif ?>
</div>
