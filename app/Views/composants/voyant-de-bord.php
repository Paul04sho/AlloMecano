<?php
/**
 * Voyant de bord S11 (Figma 48:214) : témoin de tableau de bord (ISO 2575), grille 32.
 * Éteint : gris voyant (sprite). Allumé : jaune balise + halo pré-rendu (public/assets/icones/voyants/*.svg),
 * qui déborde du carré de 32 comme dans le Figma. Toujours avec un libellé à côté.
 * @var string $code    code de la panne (« batterie », « choc_accident »…)
 * @var bool $allume    allumé d'office ; sinon il s'allume quand la tuile est cochée (CSS)
 */
$nom = str_replace('_', '-', $code);
// Débord du dessin allumé (halo) par rapport au carré de 32 : gauche, haut, largeur, hauteur (Figma 48:143 → 48:213)
$halos = [
    'ne-demarre-pas' => [-2.25, 0, 37.5, 32], 'batterie' => [-3.25, -1.25, 38.5, 33.5], 'surchauffe' => [-4.75, -3.25, 41.5, 38.625],
    'huile-fuite' => [-2.25, 0, 38.4467, 32], 'pneu-creve' => [-3.25, -3.25, 38.5, 38.5], 'freins' => [-4.66, -1.27, 41.3167, 34.5355],
    'choc-accident' => [-4.09, -2.61, 40.1764, 36.8651], 'bruit-fumee' => [-0.12, 0, 32.2324, 34.7678], 'je-ne-sais-pas' => [0, -0.83, 32, 33.5823],
];
[$x, $y, $l, $h] = $halos[$nom] ?? [0, 0, 32, 32];
?>
<span class="voyant-bord<?= !empty($allume) ? ' voyant-bord--allume' : '' ?>" aria-hidden="true">
  <?= icone('voyant-' . $nom, 32, 'voyant-bord__eteint') ?>
  <img class="voyant-bord__allume" src="<?= e(asset('icones/voyants/' . $nom . '.svg')) ?>" width="<?= $l ?>" height="<?= $h ?>" style="left:<?= $x ?>px;top:<?= $y ?>px" alt="">
</span>
