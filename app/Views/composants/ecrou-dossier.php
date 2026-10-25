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
// Les six facettes exactes du Figma (50:89, dessinées en 120 × 138,564, ici ramenées à 24 × 27,7128) : des quadrilatères qui laissent un évidement autour du voyant et un joint entre chaque pièce.
$facettes = [
    'cni_recto' => 'M12.2187 0.2859 L23.6428 6.8819 L18.1047 9.9219 L12.3567 6.6019 Z',
    'cni_verso' => 'M23.8622 7.2606 V20.4526 L18.4602 17.1746 V10.5366 Z',
    'permis' => 'M23.6428 20.8318 L12.2187 27.4278 L12.3567 21.1098 L18.1047 17.7918 Z',
    'diplome' => 'M11.7813 27.4278 L0.3573 20.8318 L5.8953 17.7918 L11.6433 21.1098 Z',
    'portrait' => 'M0.1386 20.4526 V7.2606 L5.5406 10.5366 V17.1746 Z',
    'atelier' => 'M0.3573 6.8819 L11.7813 0.2859 L11.6433 6.6019 L5.8953 9.9219 Z',
];
$ordre = array_keys($facettes);
$noms = ['cni_recto' => 'pièce d’identité (recto)', 'cni_verso' => 'pièce d’identité (verso)', 'permis' => 'permis', 'diplome' => 'diplôme ou attestation', 'portrait' => 'photo', 'atelier' => 'atelier'];
$etats = ['envoyee' => 'envoyée', 'manquante' => 'manquante', 'facultative' => 'non fournie', 'a_reprendre' => 'à reprendre'];
?>
<svg class="ecrou-dossier" width="<?= (int) $taille ?>" height="<?= round($taille * $h / 24, 2) ?>" viewBox="0 0 24 <?= $h ?>" role="img" aria-label="Pièces : <?= e(implode(', ', array_map(static fn (string $t) => $noms[$t] . ' ' . ($etats[$pieces[$t] ?? 'manquante'] ?? ''), $ordre))) ?>">
  <?php foreach ($facettes as $type => $d): $etat = $pieces[$type] ?? 'manquante'; ?>
    <path class="ecrou-dossier__facette ecrou-dossier__facette--<?= e(str_replace('_', '-', $etat)) ?>" d="<?= $d ?> Z" />
  <?php endforeach ?>
  <circle class="ecrou-dossier__voyant<?= !empty($valide) ? ' ecrou-dossier__voyant--valide' : '' ?>" cx="12" cy="13.8564" r="2.976"/>
</svg>
