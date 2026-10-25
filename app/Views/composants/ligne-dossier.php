<?php
/**
 * Ligne de dossier : liste du tableau de bord (A0, Figma 164:93) ou ligne de la table des dossiers (A1, Figma 167:177).
 * @var array<string, mixed> $dossier   voir DossierService
 * @var string $mode      liste | table
 * @var bool $principal   le bouton « Examiner » est jaune (le dossier le plus ancien)
 */
use App\Core\Format;

$d = $dossier;
$href = '/admin/dossiers/' . rawurlencode($d['public_id']);
$portrait = $d['portrait']
    ? '<span class="ligne-dossier__portrait"><img src="' . e($href . '/portrait') . '" width="40" height="40" alt="" loading="lazy" decoding="async"></span>'
    : '<span class="ligne-dossier__portrait ligne-dossier__portrait--vide" aria-hidden="true">' . icone('portrait', 20) . '</span>';
$bouton = composant('bouton', ['libelle' => 'Examiner', 'type' => !empty($principal) ? 'principal' : 'contour', 'hauteur' => 44, 'href' => $href, 'icone' => $mode === 'liste' ? 'oeil' : null,
    'attrs' => ['aria-label' => 'Examiner le dossier de ' . $d['nom_complet'], 'data-examiner' => '1']]);
if ($mode === 'liste'): ?>
<li class="ligne-dossier ligne-dossier--liste">
  <?= $portrait ?>
  <div class="ligne-dossier__identite"><p class="t-titre-3"><?= e($d['nom_complet']) ?></p><p class="t-petit t-secondaire"><?= e(implode(' · ', array_filter([$d['commune'], $d['specialites'][0] ?? '']))) ?></p></div>
  <?= composant('jauge-attente', ['jauge' => $d['jauge'], 'largeur' => 172]) ?>
  <?= $bouton ?>
</li>
<?php else: ?>
<tr class="ligne-dossier ligne-dossier--table" data-ligne>
  <td class="ligne-dossier__c1"><?= $portrait ?></td>
  <th scope="row" class="ligne-dossier__c2"><span class="t-titre-3"><?= e($d['nom_complet']) ?></span><span class="t-donnee t-secondaire"><?= e($d['numero']) ?></span></th>
  <td class="t-petit"><?= e($d['commune']) ?></td>
  <td class="t-petit t-secondaire"><?= e(Format::typo(implode(', ', array_map(static fn (string $s, int $i): string => $i === 0 ? $s : mb_strtolower($s, 'UTF-8'), $d['specialites'], array_keys($d['specialites']))))) ?></td>
  <td class="ligne-dossier__envoye"><?php if ($d['envoye_le'] !== null): ?><span class="t-petit"><?= e(trim((string) preg_replace('/\s\d{4}$/', '', Format::date($d['envoye_le'])))) ?></span><span class="t-donnee t-secondaire"><?= e(Format::heure($d['envoye_le'])) ?></span><?php endif ?></td>
  <td>
    <?php if (in_array($d['statut'], ['en_attente', 'a_reprendre'], true)): ?><?= composant('jauge-attente', ['jauge' => $d['jauge'], 'largeur' => 164]) ?>
    <?php elseif ($d['statut'] === 'valide' && $d['verifie_le'] !== null): ?><span class="t-etiquette t-secondaire">Vérifié le <?= e(Format::date($d['verifie_le'])) ?></span>
    <?php else: ?><span class="t-etiquette t-secondaire"><?= e(\App\Services\DossierService::ONGLETS[$d['statut']] ?? '') ?></span><?php endif ?>
  </td>
  <td><?= composant('pastilles-pieces', ['pieces' => $d['pieces']]) ?></td>
  <td class="ligne-dossier__action"><?= $bouton ?></td>
</tr>
<?php endif ?>
