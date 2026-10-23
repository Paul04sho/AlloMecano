<?php
/**
 * Ligne de classement (Figma 54:251).
 *   compacte  (96) : colonne borne facultative (repère de rang W28 + distance Borne/L), portrait rond 52 + poinçon 18,
 *                    nom Titre/3, spécialités (celle de la panne en gras, RG-R6), « 4,8 ★ · 37 avis · quartier » en Mono,
 *                    chevron. Toute la ligne ouvre la fiche.
 *   etendue        : la plus proche (ou choisie sur la carte) : repère numéroté W36 + Borne/XL, rubrique, portrait 4:5
 *                    coupe M, actions Appeler (le seul jaune) + WhatsApp. Une seule étendue à la fois.
 *   squelette      : même géométrie en surface creuse, statique (C4b).
 * @var string $format       compacte | etendue | squelette
 * @var ?string $href        sans lien : ligne d'illustration (récit de l'accueil)
 * @var string $nom
 * @var string|array{src: string, srcset?: string, couleur?: string, cadrage?: string} $portrait
 * @var list<string> $specialites
 * @var list<string>|string|null $specialitePanne  celle(s) qui correspondent à la panne : en gras (RG-R6)
 * @var ?float $note
 * @var int $avis
 * @var ?string $quartier
 * @var ?int $rang           affiche la colonne borne
 * @var ?int $distanceM      distance en mètres (colonne borne)
 * @var bool $autreRive      RG-R9 : « AUTRE RIVE · PRÉVOIR LE PONT »
 * @var bool $verifie        poinçon (RG-DO8 : seulement pour un dossier validé)
 * @var ?string $rubrique    étendue : « LE PLUS PROCHE », « CHOISI SUR LA CARTE »
 * @var ?string $actions     étendue : HTML des boutons
 * @var ?string $classe
 * @var array<string,string> $attrs
 */
use App\Core\Format;

$format ??= 'compacte';
if ($format === 'squelette'): ?>
<div class="ligne-classement ligne-classement--squelette<?= !empty($classe) ? ' ' . e($classe) : '' ?>" aria-hidden="true">
  <span class="ligne-classement__borne"><i class="squelette" style="width:28px;height:34px"></i><i class="squelette" style="width:48px;height:22px"></i></span>
  <i class="squelette squelette--rond" style="width:52px;height:52px"></i>
  <span class="ligne-classement__texte"><i class="squelette" style="width:<?= (int) ($largeurs[0] ?? 150) ?>px;height:18px"></i><i class="squelette" style="width:<?= (int) ($largeurs[1] ?? 214) ?>px;height:12px"></i><i class="squelette" style="width:118px;height:12px"></i></span>
</div>
<?php return; endif;

$avis ??= 0;
$verifie ??= true;
$enGras = is_array($specialitePanne ?? null) ? $specialitePanne : (isset($specialitePanne) ? [$specialitePanne] : []);
$meta = [Format::note($note ?? null) . Format::INSECABLE . '★', $avis . Format::INSECABLE . 'avis'];
if (!empty($quartier) && ($format ?? 'compacte') !== 'etendue') { $meta[] = $quartier; } // l'étendue (60:409) n'affiche que note et avis
$photo = is_array($portrait) ? $portrait : ['src' => $portrait];
$html = '';
foreach (array_filter($attrs ?? [], fn ($v) => $v !== null) as $k => $v) { $html .= ' ' . e($k) . '="' . e($v) . '"'; }
$specialitesHtml = '';
foreach ($specialites as $i => $s) {
    $specialitesHtml .= ($i > 0 ? ' · ' : '') . (in_array($s, $enGras, true) ? '<b>' . e($s) . '</b>' : e($s));
}
$texte = '<span class="ligne-classement__nom t-titre-3">' . e($nom) . '</span>'
    . '<span class="ligne-classement__specialites t-petit">' . $specialitesHtml . '</span>'
    . '<span class="ligne-classement__meta t-donnee">' . e(implode(' · ', $meta)) . '</span>'
    . (!empty($autreRive) ? '<span class="ligne-classement__rive">Autre rive · prévoir le pont</span>' : '');

if ($format === 'etendue'): ?>
<article class="ligne-classement ligne-classement--etendue<?= !empty($classe) ? ' ' . e($classe) : '' ?>"<?= $html ?>>
  <div class="ligne-classement__ligne1">
    <span class="ligne-classement__borne">
      <?= composant('repere-carte', ['type' => 'numerote', 'rang' => $rang ?? 1]) ?>
      <?= borne(Format::km((int) ($distanceM ?? 0)), 'km', 'xl') ?>
    </span>
    <span class="ligne-classement__texte">
      <?php if (!empty($rubrique)): ?><span class="ligne-classement__rubrique t-etiquette"><?= e($rubrique) ?></span><?php endif ?>
      <?php if (isset($href)): ?><a class="ligne-classement__lien" href="<?= e($href) ?>"><?= $texte ?></a><?php else: ?><?= $texte ?><?php endif ?>
    </span>
    <img class="ligne-classement__portrait45" src="<?= e($photo['src']) ?>"<?= !empty($photo['srcset']) ? ' srcset="' . e($photo['srcset']) . '" sizes="80px"' : '' ?> width="80" height="100" alt=""
         style="<?= !empty($photo['couleur']) ? 'background:' . e($photo['couleur']) . ';' : '' ?><?= !empty($photo['cadrage']) ? 'object-position:' . e($photo['cadrage']) : '' ?>" decoding="async">
  </div>
  <?php if (!empty($actions)): ?><div class="ligne-classement__actions"><?= $actions ?></div><?php endif ?>
</article>
<?php return; endif;

$balise = isset($href) ? 'a' : 'div'; ?>
<<?= $balise ?> class="ligne-classement<?= !empty($classe) ? ' ' . e($classe) : '' ?>"<?= isset($href) ? ' href="' . e($href) . '"' : '' ?><?= $html ?>>
  <?php if (isset($rang)): ?>
    <span class="ligne-classement__borne">
      <?= composant('repere-carte', ['type' => 'rang', 'rang' => $rang]) ?>
      <?= borne(Format::km((int) ($distanceM ?? 0)), 'km', 'l') ?>
    </span>
  <?php endif ?>
  <span class="ligne-classement__portrait">
    <img src="<?= e($photo['src']) ?>" width="52" height="52" alt="" loading="lazy" decoding="async"<?= !empty($photo['couleur']) ? ' style="background:' . e($photo['couleur']) . '"' : '' ?>>
    <?php if ($verifie): ?><?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?><?php endif ?>
  </span>
  <span class="ligne-classement__texte"><?= $texte ?></span>
  <?= icone('chevron', 20, 'ligne-classement__chevron') ?>
</<?= $balise ?>>
