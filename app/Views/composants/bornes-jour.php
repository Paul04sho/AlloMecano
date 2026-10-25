<?php
/**
 * Bornes du jour (Figma 107:738) : trois chiffres en Borne/L avec leur unité en Mono, et une ligne de sens en Corps/Micro, séparés par des filets.
 * @var list<array{valeur: string, unite: string, libelle: string}> $bornes
 */
?>
<div class="bornes-jour">
  <?php foreach ($bornes as $b): ?>
    <div class="bornes-jour__borne">
      <p class="bornes-jour__valeur"><span class="t-borne-l"><?= e($b['valeur']) ?></span><span class="t-etiquette"><?= e($b['unite']) ?></span></p>
      <p class="t-micro t-secondaire"><?= e($b['libelle']) ?></p>
    </div>
  <?php endforeach ?>
</div>
