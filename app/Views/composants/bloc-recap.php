<?php
/**
 * Bloc de récapitulatif (Figma 104:637) : rubrique en Mono, lien « Modifier », puis les lignes (Titre/3, Donnée ou Corps/Petit), et au besoin des tarifs en Borne/L.
 * @var string $rubrique
 * @var string $href            « Modifier » mène à l'étape concernée
 * @var list<array{style: string, texte: string}> $lignes   style : titre | donnee | petit
 * @var list<array{libelle: string, valeur: string, unite?: string}> $tarifs  montants déjà formatés, en FCFA sauf unité donnée (« Inclus » n'en a pas)
 */
use App\Core\Format;

$classes = ['titre' => 't-titre-3', 'donnee' => 't-donnee', 'petit' => 't-petit t-secondaire'];
$tarifs ??= [];
?>
<section class="bloc-recap">
  <div class="bloc-recap__entete">
    <p class="t-etiquette t-secondaire"><?= e($rubrique) ?></p>
    <a class="lien t-gras" href="<?= e($href) ?>">Modifier<span class="sr-only"> : <?= e(mb_strtolower($rubrique, 'UTF-8')) ?></span></a>
  </div>
  <?php foreach ($lignes as $l): ?><p class="<?= e($classes[$l['style']] ?? 't-petit') ?>"><?= e(Format::typo($l['texte'])) ?></p><?php endforeach ?>
  <?php if ($tarifs !== []): ?>
    <div class="bloc-recap__tarifs">
      <?php foreach ($tarifs as $t): ?>
        <div><p class="t-petit t-secondaire"><?= e($t['libelle']) ?></p><p class="bloc-recap__borne"><span class="t-borne-l"><?= e($t['valeur']) ?></span><?php if (($t['unite'] ?? 'FCFA') !== ''): ?><span class="t-etiquette"><?= e($t['unite'] ?? 'FCFA') ?></span><?php endif ?></p></div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
