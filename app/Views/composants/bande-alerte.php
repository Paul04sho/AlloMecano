<?php
/**
 * Alerte d'administration (Figma 179:614) : la bande de balisage S10 de 8 px, puis une ligne triangle et texte en Mono, en rouge.
 * @var string $texte    « 1 critique non traité depuis plus de 2 h »
 */
?>
<div class="bande-alerte" role="alert">
  <div class="bande-alerte__bande" aria-hidden="true"></div>
  <p class="bande-alerte__texte t-etiquette"><?= icone('triangle', 20) ?><span><?= e($texte) ?></span></p>
</div>
