<?php
/**
 * Ligne « Brouillon enregistré à 10:14 » de l'inscription (Figma 95:169) : l'heure du dernier enregistrement en Mono, l'écrou dossier de 24 à droite
 * (la même pièce par pièce qu'à M1c, en petit). Mise à jour par le JS à chaque enregistrement ; annoncée aux lecteurs d'écran (aria-live).
 * @var ?string $heure   « 10:14 » ; absent : la ligne attend le premier enregistrement
 * @var ?array<string, string> $pieces  états des pièces pour l'écrou ; pas d'écrou à l'étape 3 (la pièce l'affiche en grand)
 * @var string $verbe    « enregistré » par défaut
 */
$verbe ??= 'enregistré';
$pieces ??= null;
?>
<div class="parcours-brouillon"<?= empty($heure) ? ' hidden' : '' ?> data-brouillon>
  <p class="t-donnee t-secondaire" aria-live="polite" data-brouillon-texte><?= !empty($heure) ? e('Brouillon ' . $verbe . ' à ' . $heure) : '' ?></p>
  <?php if ($pieces !== null): ?><span data-brouillon-ecrou><?= composant('ecrou-dossier', ['pieces' => $pieces, 'taille' => 24]) ?></span><?php endif ?>
</div>
