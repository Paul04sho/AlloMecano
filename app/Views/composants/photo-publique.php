<?php
/**
 * Photo publique d'un mécano (copies WebP de 160, 360 et 720 px, RG-DO9) avec sa légende-repère facultative.
 * @var array{src: string, srcset: string, ratio: float, couleur: string, cadrage: string} $photo
 * @var string $alt
 * @var ?string $legende   « SON ATELIER · RIVIERA 3 »
 * @var ?string $sizes
 * @var ?string $classe
 */
$legende ??= null;
$classe ??= '';
$sizes ??= '358px';
?>
<figure class="photo-publique<?= $legende !== null ? ' photo-publique--legendee' : '' ?><?= $classe !== '' ? ' ' . e($classe) : '' ?>" style="--ratio:<?= e((string) $photo['ratio']) ?>;background:<?= e($photo['couleur']) ?>">
  <img src="<?= e($photo['src']) ?>" srcset="<?= e($photo['srcset']) ?>" sizes="<?= e($sizes) ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async" style="object-position:<?= e($photo['cadrage']) ?>">
  <?php if ($legende !== null): ?><?= composant('legende-repere', ['texte' => $legende]) ?><?php endif ?>
</figure>
