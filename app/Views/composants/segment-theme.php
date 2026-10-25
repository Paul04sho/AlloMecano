<?php
/**
 * Choix du thème (Figma 120:1472, segment 54:499) : Clair · Sombre · Auto. Le choix s'applique tout de suite et se garde (cookie) ; Auto suit le réglage du téléphone.
 * Comme le segment de formulaire, l'option active est sur fond inverse. Le script commun (data-theme-choix) fait le reste.
 */
$actif = in_array($_COOKIE['theme'] ?? null, ['clair', 'sombre'], true) ? $_COOKIE['theme'] : 'systeme';
?>
<div class="segment-theme" role="group" aria-label="Thème d’affichage">
  <?php foreach (['clair' => 'Clair', 'sombre' => 'Sombre', 'systeme' => 'Auto'] as $valeur => $libelle): ?>
    <button type="button" data-theme-choix="<?= e($valeur) ?>" aria-pressed="<?= $valeur === $actif ? 'true' : 'false' ?>"><?= e($libelle) ?></button>
  <?php endforeach ?>
</div>
