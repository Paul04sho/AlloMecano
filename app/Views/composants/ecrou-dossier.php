<?php
/**
 * Écrou dossier (Figma 50:89) : un écrou à six facettes, une par pièce, dans le sens horaire depuis la facette haute à droite :
 * CNI recto, CNI verso, permis, diplôme ou attestation, photo de vous, atelier (facultatif, pointillé). Vide = filet, envoyée = encre, à reprendre = erreur.
 * Le voyant du centre reste gris tant que le dossier n'est pas validé ; validé = jaune. Jamais côté client.
 * @var array<string, string> $pieces  type → envoyee | manquante | facultative | a_reprendre (comme DossierService)
 * @var bool $valide
 * @var int $taille  largeur : 24, 96, 120 ou 160
 */
$taille ??= 24;
$h = 27.7128;
$s = [[12, 0], [24, 6.9282], [24, 20.7846], [12, 27.7128], [0, 20.7846], [0, 6.9282]]; // sommets, depuis le haut, dans le sens horaire
$c = [12, 13.8564];
$ordre = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait', 'atelier'];
$noms = ['cni_recto' => 'pièce d’identité (recto)', 'cni_verso' => 'pièce d’identité (verso)', 'permis' => 'permis', 'diplome' => 'diplôme ou attestation', 'portrait' => 'photo', 'atelier' => 'atelier'];
$etats = ['envoyee' => 'envoyée', 'manquante' => 'manquante', 'facultative' => 'non fournie', 'a_reprendre' => 'à reprendre'];
$retrait = static fn (array $p, float $k): string => round($c[0] + ($p[0] - $c[0]) * $k, 3) . ',' . round($c[1] + ($p[1] - $c[1]) * $k, 3);
?>
<svg class="ecrou-dossier" width="<?= (int) $taille ?>" height="<?= round($taille * $h / 24, 2) ?>" viewBox="0 0 24 <?= $h ?>" role="img" aria-label="Pièces : <?= e(implode(', ', array_map(static fn (string $t) => $noms[$t] . ' ' . ($etats[$pieces[$t] ?? 'manquante'] ?? ''), $ordre))) ?>">
  <?php foreach ($ordre as $i => $type):
      $etat = $pieces[$type] ?? 'manquante';
      $a = $s[$i];
      $b = $s[($i + 1) % 6];
      // chaque facette : le centre et deux sommets, un peu rétrécie pour laisser voir les joints
      $points = $retrait($c, 1) . ' ' . $retrait($a, .93) . ' ' . $retrait($b, .93); ?>
    <polygon class="ecrou-dossier__facette ecrou-dossier__facette--<?= e(str_replace('_', '-', $etat)) ?>" points="<?= $points ?>" />
  <?php endforeach ?>
  <circle class="ecrou-dossier__voyant<?= !empty($valide) ? ' ecrou-dossier__voyant--valide' : '' ?>" cx="12" cy="13.8564" r="2.9"/>
</svg>
