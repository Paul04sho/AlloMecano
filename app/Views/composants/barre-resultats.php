<?php
/**
 * En-tête des résultats.
 *   Mobile (60:341) : barre flottante sur la carte : retour bitume 48 + résumé « Voiture · Batterie · Riviera 2 ».
 *   Desktop (156:551) : barre 64, logo, résumé modifiable sur place (lieu ✎ · véhicule ✎ · panne ✎), Espace pro.
 * @var array{vehicule: string, vehicule_libelle: string, panne: string, panne_libelle: string, lieu: string} $resume
 * @var array<string, string> $vehicules  pour modifier sur place (desktop) ; vide = pas de modification
 * @var list<array{code: string, libelle: string, danger: bool}> $pannes
 * @var string $retour
 */
$vehicules ??= [];
$pannes ??= [];
$modifiable = $vehicules !== [] && $pannes !== [];
?>
<div class="barre-flottante seul-mobile">
  <a class="bouton-icone bouton-icone--bitume" href="<?= e($retour) ?>" aria-label="Retour"><?= icone('retour') ?></a>
  <a class="barre-flottante__resume" href="/panne"><?= icone('vehicule-' . $resume['vehicule'], 24) ?><span><?= e($resume['vehicule_libelle'] . ' · ' . $resume['panne_libelle'] . ' · ' . $resume['lieu']) ?></span></a>
</div>

<header class="barre barre--resultats seul-desktop">
  <a class="barre__logo" href="/" aria-label="allomécano, accueil"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a>
  <div class="resume" aria-label="Votre recherche">
    <a class="resume__segment" href="/position/quartier"><?= icone('repere', 20) ?><span><?= e($resume['lieu']) ?></span><?= icone('crayon', 16, 'resume__crayon') ?><span class="sr-only">(modifier le lieu)</span></a>
    <?php if ($modifiable): ?>
      <div class="resume__item" data-resume-item>
        <button type="button" class="resume__segment" aria-expanded="false" aria-controls="resume-vehicule"><?= icone('vehicule-' . $resume['vehicule'], 20) ?><span><?= e($resume['vehicule_libelle']) ?></span><?= icone('crayon', 16, 'resume__crayon') ?><span class="sr-only">(modifier le véhicule)</span></button>
        <form class="resume__panneau" id="resume-vehicule" method="post" action="/recherche/modifier" hidden>
          <?= csrf_champ() ?>
          <?= composant('choix-vehicule', ['nom' => 'vehicule', 'vehicules' => $vehicules, 'valeur' => $resume['vehicule'], 'legende' => 'Votre véhicule']) ?>
          <?= composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'recherche', 'plein' => true, 'attrs' => ['type' => 'submit']]) ?>
        </form>
      </div>
      <div class="resume__item" data-resume-item>
        <button type="button" class="resume__segment" aria-expanded="false" aria-controls="resume-panne"><?= composant('voyant-de-bord', ['code' => $resume['panne']]) ?><span><?= e($resume['panne_libelle']) ?></span><?= icone('crayon', 16, 'resume__crayon') ?><span class="sr-only">(modifier la panne)</span></button>
        <form class="resume__panneau resume__panneau--panne" id="resume-panne" method="post" action="/recherche/modifier" hidden>
          <?= csrf_champ() ?>
          <?= composant('panneau-voyants', ['nom' => 'panne', 'pannes' => $pannes, 'valeur' => $resume['panne']]) ?>
          <?= composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'recherche', 'plein' => true, 'attrs' => ['type' => 'submit']]) ?>
        </form>
      </div>
    <?php else: ?>
      <span class="resume__segment"><?= icone('vehicule-' . $resume['vehicule'], 20) ?><span><?= e($resume['vehicule_libelle']) ?></span></span>
      <span class="resume__segment"><?= composant('voyant-de-bord', ['code' => $resume['panne']]) ?><span><?= e($resume['panne_libelle']) ?></span></span>
    <?php endif ?>
  </div>
  <nav class="barre__liens" aria-label="Navigation principale"><a class="barre__pro barre__pro--lien" href="/pro">Espace pro</a></nav>
</header>
