<?php
/**
 * Ligne de classement, version compacte (Figma 54:251) : toute la ligne ouvre la fiche.
 * Colonne borne facultative (repère de rang W28 + distance Borne/L), portrait rond 52 + poinçon 18,
 * nom Titre/3, spécialités (celle de la panne en gras), « 4,8 ★ · 37 avis · quartier » en Mono, chevron.
 * @var ?string $href       sans lien : ligne d'illustration (récit de l'accueil)
 * @var string $nom
 * @var string $portrait     URL du portrait WebP (104 × 104 pour 52 en ×2)
 * @var list<string> $specialites
 * @var ?string $specialitePanne  celle qui correspond à la panne : en gras (RG-R6)
 * @var ?float $note
 * @var int $avis
 * @var ?string $quartier
 * @var ?int $rang           affiche la colonne borne
 * @var ?int $distanceM      distance en mètres (colonne borne)
 * @var bool $verifie        poinçon (RG-DO8 : seulement pour un dossier validé)
 * @var ?string $classe
 */
use App\Core\Format;

$avis ??= 0;
$verifie ??= true;
$meta = [Format::note($note ?? null) . Format::INSECABLE . '★', $avis . Format::INSECABLE . 'avis'];
if (!empty($quartier)) { $meta[] = $quartier; }
?>
<?php $balise = isset($href) ? 'a' : 'div'; ?>
<<?= $balise ?> class="ligne-classement<?= !empty($classe) ? ' ' . e($classe) : '' ?>"<?= isset($href) ? ' href="' . e($href) . '"' : '' ?>>
  <?php if (isset($rang)): ?>
    <span class="ligne-classement__borne">
      <?= composant('repere-carte', ['type' => 'rang', 'rang' => $rang]) ?>
      <?= borne(Format::km((int) ($distanceM ?? 0)), 'km', 'l') ?>
    </span>
  <?php endif ?>
  <span class="ligne-classement__portrait">
    <img src="<?= e($portrait) ?>" width="52" height="52" alt="" loading="lazy" decoding="async">
    <?php if ($verifie): ?><?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?><?php endif ?>
  </span>
  <span class="ligne-classement__texte">
    <span class="ligne-classement__nom t-titre-3"><?= e($nom) ?></span>
    <span class="ligne-classement__specialites t-petit"><?php foreach ($specialites as $i => $s): ?><?= $i > 0 ? ' · ' : '' ?><?= $s === ($specialitePanne ?? null) ? '<b>' . e($s) . '</b>' : e($s) ?><?php endforeach ?></span>
    <span class="ligne-classement__meta t-donnee"><?= e(implode(' · ', $meta)) ?></span>
  </span>
  <?= icone('chevron', 20, 'ligne-classement__chevron') ?>
</<?= $balise ?>>
