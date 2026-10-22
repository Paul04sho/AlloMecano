<?php
/**
 * Statut (Figma 53:406).
 * @var string $statut disponible | indisponible | brouillon | en_attente | a_reprendre | valide | refuse | suspendu
 */
$libelles = [
    'disponible' => 'Disponible', 'indisponible' => 'Indisponible', 'en_intervention' => 'En intervention',
    'brouillon' => 'Brouillon', 'en_attente' => 'En attente', 'a_reprendre' => 'À reprendre',
    'valide' => 'Validé', 'refuse' => 'Refusé', 'suspendu' => 'Suspendu',
];
$s = $statut ?? 'brouillon';
$classe = str_replace('_', '-', $s === 'en_intervention' ? 'en_attente' : $s);
?>
<span class="statut statut--<?= e($classe) ?>">
  <?php if ($s === 'disponible'): ?><?= composant('voyant', ['etat' => 'disponible']) ?>
  <?php elseif ($s === 'indisponible'): ?><?= composant('voyant', ['etat' => 'indisponible']) ?>
  <?php elseif ($s === 'a_reprendre' || $s === 'refuse'): ?><?= composant('voyant', ['etat' => 'erreur']) ?>
  <?php elseif ($s === 'valide'): ?><?= composant('poincon', ['taille' => 14]) ?>
  <?php endif ?>
  <?= e($libelles[$s] ?? $s) ?>
</span>
