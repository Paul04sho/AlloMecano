<?php
/**
 * Étape de l'entonnoir (Figma 180:706) : titre et sous-titre, barre bitume proportionnelle à la première étape, volume en Borne L,
 * volume de l'autre période en Mono dessous, conversion depuis l'étape précédente.
 * @var array{titre: string, sous: string, valeur: int, autre: int, conversion: ?string, largeur: float} $etape
 * @var int $periode    période affichée (7 | 30)
 * @var int $autre      l'autre période, affichée sous le volume
 */
use App\Core\Format;

$e = $etape;
?>
<tr class="ligne-entonnoir">
  <th scope="row" class="ligne-entonnoir__etape"><span class="t-gras"><?= e($e['titre']) ?></span><span class="t-petit t-secondaire"><?= e(Format::typo($e['sous'])) ?></span></th>
  <td class="ligne-entonnoir__barre" aria-hidden="true"><i style="width:<?= e(rtrim(rtrim(number_format($e['largeur'], 2, '.', ''), '0'), '.') ?: '0') ?>%"></i></td>
  <td class="ligne-entonnoir__volume"><span class="t-borne-l"><?= e(Format::nombre($e['valeur'])) ?></span><span class="t-etiquette t-secondaire"><?= (int) $autre ?>&#8239;j&#8239;: <?= e(Format::nombre($e['autre'])) ?></span></td>
  <td class="ligne-entonnoir__conversion t-etiquette<?= $e['conversion'] === 'base' || $e['conversion'] === null ? ' t-secondaire' : '' ?>"><?= $e['conversion'] === 'base' ? 'Base' : ($e['conversion'] ?? '—') ?></td>
</tr>
