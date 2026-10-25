<?php
/**
 * Ligne de « Mon compte » (Figma 120:1461) : pictogramme de 24, titre en Corps/Base, précision en Corps/Petit, chevron de 20 ; un filet dessous.
 * Un lien, ou — pour « Se déconnecter » — un formulaire POST (CSRF) : jamais une action par un simple lien.
 * @var string $icone
 * @var string $titre
 * @var ?string $sous
 * @var ?string $href       lien vers la page
 * @var ?string $action     formulaire POST vers cette adresse
 */
$sous ??= null;
$href ??= null;
$action ??= null;
$contenu = icone($icone, 24) . '<span class="ligne-compte__texte"><span class="t-base">' . e($titre) . '</span>' . ($sous !== null ? '<span class="t-petit t-secondaire">' . e($sous) . '</span>' : '') . '</span>' . icone('chevron', 20);
?>
<li class="ligne-compte">
  <?php if ($action !== null): ?><form method="post" action="<?= e($action) ?>"><?= csrf_champ() ?><button type="submit" class="ligne-compte__action"><?= $contenu ?></button></form>
  <?php else: ?><a class="ligne-compte__action" href="<?= e((string) $href) ?>"><?= $contenu ?></a><?php endif ?>
</li>
