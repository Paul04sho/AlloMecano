<?php
/**
 * Pastilles des pièces (Figma 167:204) : six carrés de 12, un par pièce. Pleine = envoyée, contour = manquante, tirets = atelier facultatif non fourni, rouge = à reprendre.
 * @var array<string, string> $pieces  type → envoyee | manquante | facultative | a_reprendre
 * @var bool $legende  affiche la légende de pied de table (A1)
 */
$noms = ['cni_recto' => 'Pièce d’identité (recto)', 'cni_verso' => 'Pièce d’identité (verso)', 'permis' => 'Permis de conduire', 'diplome' => 'Diplôme ou attestation', 'portrait' => 'Portrait', 'atelier' => 'Atelier'];
$etats = ['envoyee' => 'envoyée', 'manquante' => 'manquante', 'facultative' => 'non fournie (facultative)', 'a_reprendre' => 'à reprendre'];
if (!empty($legende)): ?>
  <ul class="pastilles-pieces pastilles-pieces--legende" role="list">
    <?php foreach (['envoyee' => 'Envoyée', 'manquante' => 'Manquante', 'facultative' => 'Atelier facultatif', 'a_reprendre' => 'À reprendre'] as $etat => $libelle): ?>
      <li><i class="pastille pastille--<?= e(str_replace('_', '-', $etat)) ?>" aria-hidden="true"></i><span class="t-etiquette t-secondaire"><?= e($libelle) ?></span></li>
    <?php endforeach ?>
  </ul>
<?php else: ?>
  <span class="pastilles-pieces" role="img" aria-label="Pièces : <?= e(implode(', ', array_map(static fn (string $t, string $e): string => $noms[$t] . ' ' . $etats[$e], array_keys($pieces), $pieces))) ?>">
    <?php foreach ($pieces as $etat): ?><i class="pastille pastille--<?= e(str_replace('_', '-', $etat)) ?>" aria-hidden="true"></i><?php endforeach ?>
  </span>
<?php endif ?>
