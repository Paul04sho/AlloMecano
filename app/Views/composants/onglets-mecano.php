<?php
/**
 * Onglets mécano (Figma 56:469 · 107:842) : 64 + zone sûre, bitume, quatre onglets (Statut, Demandes, Avis, Profil).
 * L'onglet actif est en béton avec un trait jaune de 3 px en haut ; un compteur de demandes en attente est une pastille béton en Mono.
 * @var string $actif     statut | demandes | avis | profil
 * @var int $demandes     contacts tel ou WhatsApp à confirmer (RG-I7) ; rien si 0
 */
$demandes ??= 0;
$onglets = [
    'statut' => ['Statut', '/pro', 'repere'],
    'demandes' => ['Demandes', '/pro/demandes', 'telephone'],
    'avis' => ['Avis', '/pro/avis', 'etoile'],
    'profil' => ['Profil', '/pro/profil', 'portrait'],
];
?>
<nav class="onglets-mecano sur-sombre" aria-label="Espace pro">
  <?php foreach ($onglets as $cle => [$libelle, $href, $icone]): ?>
    <a class="onglets-mecano__onglet<?= $actif === $cle ? ' onglets-mecano__onglet--actif' : '' ?>" href="<?= e($href) ?>"<?= $actif === $cle ? ' aria-current="page"' : '' ?>>
      <span class="onglets-mecano__icone"><?= icone($icone, 24) ?><?php if ($cle === 'demandes' && $demandes > 0): ?><span class="onglets-mecano__compteur t-donnee" aria-label="<?= (int) $demandes ?> à confirmer"><?= (int) $demandes ?></span><?php endif ?></span>
      <span class="onglets-mecano__libelle"><?= e($libelle) ?></span>
    </a>
  <?php endforeach ?>
</nav>
