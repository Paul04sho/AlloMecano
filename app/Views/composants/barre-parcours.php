<?php
/**
 * Barre de parcours (Figma 53:374) : retour · ÉTAPE n / total · Annuler, puis le marquage.
 * @var int $etape
 * @var int $total      3 (parcours client) ou 4 (inscription mécano)
 * @var string $retour  URL du bouton retour
 * @var string $annuler URL du lien Annuler (la saisie reste en mémoire)
 * @var ?string $annulerLibelle « Plus tard » dans l'inscription mécano (M1a à M1d) ; « Annuler » par défaut
 * @var ?string $rubrique « Mot de passe » : « MOT DE PASSE · 1 / 3 » au lieu de « ÉTAPE 1 / 3 » (X2)
 * @var bool $termine   parcours fini (X2 4/4) : « MOT DE PASSE · TERMINÉ », retour et Annuler cachés, tous les segments pleins
 */
$total ??= 3;
$termine ??= false;
$mot = !empty($rubrique) ? $rubrique . ' · ' : 'Étape '; ?>
<div class="parcours">
  <div class="parcours__barre">
    <a class="bouton-icone"<?= $termine ? ' style="visibility:hidden" aria-hidden="true" tabindex="-1"' : '' ?> href="<?= e($retour ?? '/') ?>" aria-label="Retour"><?= icone('retour') ?></a>
    <span class="parcours__etape t-etiquette"><?= e($mot) ?><?= $termine ? 'terminé' : (int) $etape . ' / ' . (int) $total ?></span>
    <a class="parcours__annuler"<?= $termine ? ' style="visibility:hidden" aria-hidden="true" tabindex="-1"' : '' ?> href="<?= e($annuler ?? '/') ?>"><?= e($annulerLibelle ?? 'Annuler') ?></a>
  </div>
  <div class="marquage" role="progressbar" aria-valuemin="0" aria-valuemax="<?= (int) $total ?>" aria-valuenow="<?= (int) $etape ?>" aria-label="Progression">
    <?php for ($i = 1; $i <= $total; $i++): ?><i class="<?= $termine || $i < $etape ? 'fait' : ($i === $etape ? 'fait courant' : '') ?>"></i><?php endfor ?>
  </div>
</div>
