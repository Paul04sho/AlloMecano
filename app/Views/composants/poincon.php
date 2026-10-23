<?php
/**
 * Poinçon « Vérifié » (Figma 49:166) : écrou plein sans pointe, coche. Tailles (largeur) 14, 18, 22, 48.
 * Réservé à l'identité et aux documents (RG-DO8 : jamais pour un compte en attente ou suspendu).
 * @var int $taille
 * @var ?string $titre   texte lu par les lecteurs d'écran
 * @var bool $surPhoto   liseré béton 2 px (posé sur une photo)
 */
$taille ??= 18;
$hauteur = round($taille * 1.1547, 2); // ratio de l'écrou : 18 × 20,78
?>
<span class="poincon<?= !empty($surPhoto) ? ' poincon--photo' : '' ?>" style="width:<?= (int) $taille ?>px;height:<?= $hauteur ?>px"<?= !empty($titre) ? ' role="img" aria-label="' . e($titre) . '"' : ' aria-hidden="true"' ?>>
  <svg viewBox="0 0 18 20.7846" overflow="visible"><path class="poincon__ecrou" d="M8.69 0.09C8.89667 -0.03 9.10333 -0.03 9.31 0.09L17.69 4.92C17.8967 5.04667 18 5.23 18 5.47V15.13C18 15.3767 17.8967 15.56 17.69 15.68L9.31 20.51C9.10333 20.63 8.89667 20.63 8.69 20.51L0.31 15.68C0.103333 15.56 0 15.3767 0 15.13V5.47C0 5.23 0.103333 5.04667 0.31 4.92L8.69 0.09Z" fill="currentColor"/><path d="M4.91 10.39L7.77 13.26L13.09 7.53" fill="none" stroke="var(--coche)" stroke-width="1.64" stroke-linecap="square" stroke-linejoin="bevel"/></svg>
</span>
