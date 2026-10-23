<?php
/**
 * Photo (WebP dimensionnées) avec légende-repère facultative.
 * @var string $image     préfixe dans assets/img (« accueil/img-01 ») ; fichiers « <préfixe>-<largeur>.webp »
 * @var list<int> $largeurs largeurs disponibles (la première sert de src)
 * @var string $sizes      attribut sizes
 * @var int $largeur       dimensions intrinsèques (réservent la place, pas de saut de mise en page)
 * @var int $hauteur
 * @var string $alt
 * @var ?string $legende   texte de la légende-repère (« ADJAMÉ · 10:12 »)
 * @var ?string $classe
 * @var bool $prioritaire  photo visible au chargement : pas de lazy, fetchpriority=high
 * @var list<array{media: string, image: string, largeurs: list<int>, sizes: string}> $sources  autre cadrage selon l'écran (<picture>)
 * @var ?array{titre: string, texte: string} $aProduire  photo pas encore livrée : emplacement réservé (TODO-PHOTO)
 */
$largeurs ??= [];
$classes = 'photo' . (!empty($legende) ? ' photo--legendee' : '') . (!empty($classe) ? ' ' . $classe : '');
?>
<figure class="<?= e($classes) ?>">
  <?php if (!empty($aProduire)): /* TODO-PHOTO : remplacer par la photo livrée */ ?>
    <div class="photo__a-produire" role="img" aria-label="<?= e($alt) ?>">
      <span class="photo__a-produire-texte"><span class="t-etiquette"><?= e($aProduire['titre']) ?></span><span class="t-micro"><?= e($aProduire['texte']) ?></span></span>
    </div>
  <?php else:
    $srcset = fn (string $img, array $ls) => implode(', ', array_map(fn (int $l) => asset($img . '-' . $l . '.webp') . ' ' . $l . 'w', $ls)); ?>
    <?php if (!empty($sources)): ?><picture><?php foreach ($sources as $s): ?><source media="<?= e($s['media']) ?>" srcset="<?= e($srcset($s['image'], $s['largeurs'])) ?>" sizes="<?= e($s['sizes']) ?>"><?php endforeach ?><?php endif ?>
    <img src="<?= e(asset($image . '-' . $largeurs[0] . '.webp')) ?>" srcset="<?= e($srcset($image, $largeurs)) ?>" sizes="<?= e($sizes ?? '100vw') ?>"
         width="<?= (int) $largeur ?>" height="<?= (int) $hauteur ?>" alt="<?= e($alt) ?>"
         <?= !empty($prioritaire) ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"><?= !empty($sources) ? '</picture>' : '' ?>
  <?php endif ?>
  <?php if (!empty($legende)): ?><?= composant('legende-repere', ['texte' => $legende]) ?><?php endif ?>
</figure>
