<?php
/**
 * Barre de parcours (Figma 53:374) : retour · ÉTAPE n / total · Annuler, puis le marquage.
 * @var int $etape
 * @var int $total      3 (parcours client) ou 4 (inscription mécano)
 * @var string $retour  URL du bouton retour
 * @var string $annuler URL du lien Annuler (la saisie reste en mémoire)
 */
$total ??= 3; ?>
<div class="parcours">
  <div class="parcours__barre">
    <a class="bouton-icone" href="<?= e($retour ?? '/') ?>" aria-label="Retour"><?= icone('retour') ?></a>
    <span class="parcours__etape t-etiquette">Étape <?= (int) $etape ?> / <?= (int) $total ?></span>
    <a class="parcours__annuler" href="<?= e($annuler ?? '/') ?>">Annuler</a>
  </div>
  <div class="marquage" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $total ?>" aria-valuenow="<?= (int) $etape ?>" aria-label="Progression">
    <?php for ($i = 1; $i <= $total; $i++): ?><i class="<?= $i < $etape ? 'fait' : ($i === $etape ? 'fait courant' : '') ?>"></i><?php endfor ?>
  </div>
</div>
