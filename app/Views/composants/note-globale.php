<?php
/**
 * Note globale (Figma 118:1080) : la moyenne en Borne/XL avec « SUR 5 », le nombre d'avis en gras et, dessous, depuis quand ils comptent.
 * @var ?string $note      « 4,8 » ; vide tant qu'il n'y a aucun avis
 * @var string $nombre     « 37 avis »
 * @var ?string $depuis    « Depuis votre validation, le 12 mars 2026. »
 */
?>
<div class="note-globale">
  <p class="note-globale__borne"><span class="t-borne-xl"><?= e($note ?? '–') ?></span><span class="t-etiquette">sur 5</span></p>
  <div class="note-globale__texte"><p class="t-gras"><?= e($nombre) ?></p><?php if (!empty($depuis)): ?><p class="t-petit t-secondaire"><?= e($depuis) ?></p><?php endif ?></div>
</div>
