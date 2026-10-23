<?php
/**
 * Ligne commune (Figma 56:734) : 52 de haut, nombre RÉEL de mécanos disponibles en Borne/L.
 * 0 → « aucun pour l'instant ». Commune pas encore ouverte → « BIENTÔT » (pas de lien).
 * Toucher une commune lance C3 avec cette position.
 * @var string $nom
 * @var string $slug
 * @var string $etat     disponibles | aucun | bientot
 * @var int $nombre
 * @var ?string $classe
 */
$classes = 'ligne-commune ligne-commune--' . e($etat) . (!empty($classe) ? ' ' . e($classe) : '');
$balise = $etat === 'bientot' ? 'span' : 'a';
?>
<<?= $balise ?> class="<?= $classes ?>"<?= $balise === 'a' ? ' href="/panne?commune=' . e(rawurlencode($slug)) . '"' : '' ?>>
  <span class="ligne-commune__nom"><?= e($nom) ?></span>
  <?php if ($etat === 'disponibles'): ?>
    <span class="ligne-commune__nombre"><span class="t-borne-l"><?= (int) $nombre ?></span><span class="t-etiquette">dispo</span></span>
  <?php elseif ($etat === 'aucun'): ?>
    <span class="ligne-commune__statut t-petit">aucun pour l’instant</span>
  <?php else: ?>
    <span class="ligne-commune__statut t-etiquette">Bientôt</span>
  <?php endif ?>
  <?= icone('chevron', 20, 'ligne-commune__chevron') ?>
</<?= $balise ?>>
