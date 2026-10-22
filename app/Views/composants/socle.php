<?php
/**
 * Socle d'action (Figma 53:282) : barre basse bitume, 1 ou 2 actions, ligne d'info facultative.
 * @var string $actions  HTML des boutons (principal d'abord)
 * @var ?string $info    ligne Mono au-dessus (« APPEL DIRECT · LE PRIX SE DISCUTE AVEC LUI »)
 * @var ?string $pourquoi phrase qui dit ce qui manque quand le principal est désactivé
 */ ?>
<div class="socle sur-sombre" data-socle>
  <?php if (!empty($info)): ?><p class="socle__info"><?= e($info) ?></p><?php endif ?>
  <?php if (!empty($pourquoi)): ?><p class="socle__pourquoi" data-socle-pourquoi><?= e($pourquoi) ?></p><?php endif ?>
  <div class="socle__actions"><?= $actions ?></div>
</div>
