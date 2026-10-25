<?php
/**
 * Ligne de demande (Figma 107:759) : l'heure en Mono, le client (« Aminata » ou « Un client »), le voyant de bord de la panne et « Batterie · Voiture », la distance,
 * et « Rappeler » quand le client a laissé son numéro (jamais autrement : on ne montre pas un numéro qu'il n'a pas donné).
 * @var array{heure: string, client: string, resume: string, code_panne: ?string, distance: ?string, telephone: ?string} $demande
 */
$d = $demande;
?>
<li class="ligne-demande">
  <p class="ligne-demande__heure t-donnee"><?= e($d['heure']) ?></p>
  <div class="ligne-demande__texte">
    <p class="t-titre-3"><?= e($d['client']) ?></p>
    <p class="ligne-demande__panne"><?php if ($d['code_panne'] !== null): ?><span class="ligne-demande__voyant"><?= composant('voyant-de-bord', ['code' => $d['code_panne'], 'allume' => true]) ?></span><?php endif ?><span class="t-petit"><?= e($d['resume']) ?></span></p>
    <?php if ($d['distance'] !== null): ?><p class="t-donnee t-secondaire">à <?= e($d['distance']) ?></p><?php endif ?>
  </div>
  <?php if ($d['telephone'] !== null): ?><?= composant('bouton', ['libelle' => 'Rappeler', 'type' => 'secondaire', 'hauteur' => 48, 'href' => 'tel:' . $d['telephone'], 'attrs' => ['aria-label' => 'Rappeler ' . $d['client']]]) ?><?php endif ?>
</li>
