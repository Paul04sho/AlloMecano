<?php
/**
 * En-tête de l'espace pro (Figma 107:695) : avatar de 40, « Bonjour Koffi », sa ligne (spécialités · quartier) et l'étiquette Vérifié (dossier validé seulement, RG-DO8).
 * @var string $prenom
 * @var string $ligne        « Électricité auto · Riviera 3 »
 * @var array{src: string, cadrage?: string}|string|null $avatar   photo publique (validé) ou sa propre photo (avant)
 * @var bool $verifie
 */
$avatar = is_array($avatar ?? null) ? $avatar : (!empty($avatar) ? ['src' => $avatar] : null);
?>
<header class="en-tete-pro">
  <span class="en-tete-pro__avatar" aria-hidden="true"><?php if ($avatar): ?><img src="<?= e($avatar['src']) ?>" width="40" height="40" alt="" <?= !empty($avatar['cadrage']) ? 'style="object-position:' . e($avatar['cadrage']) . '"' : '' ?>><?php else: ?><?= icone('portrait', 24) ?><?php endif ?></span>
  <div class="en-tete-pro__texte"><p class="t-titre-2">Bonjour <?= e($prenom) ?></p><p class="t-petit t-secondaire"><?= e($ligne) ?></p></div>
  <?php if (!empty($verifie)): ?><?= composant('etiquette-verifie', ['etat' => 'verifie']) ?><?php endif ?>
</header>
