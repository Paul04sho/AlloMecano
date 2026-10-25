<?php
/**
 * Layout de l'espace pro connecté (Figma M2 à M4) : barre mécano de 56, contenu, onglets mécano en bas.
 * Mobile d'abord ; sur grand écran, le contenu reste une colonne centrée (aucun écran desktop n'est dessiné pour le mécano).
 * @var string $onglet     statut | demandes | avis | profil
 * @var int $demandes      demandes à confirmer, pastille de l'onglet (RG-I7)
 * @var bool $sansOnglets  écrans qui ont leur propre socle (M2b, M5)
 * @var bool $sansBarre
 * @var bool $sonnerie     mécano validé : l'app interroge les appels qui sonnent (RG-A2)
 */
use App\Core\Session;
use App\Core\View;

$noindex = true; // l'espace pro ne s'indexe pas
View::debut('contenu'); ?>
<div class="pro<?= empty($sansOnglets) ? ' pro--onglets' : '' ?>">
  <?php if (empty($sansBarre)): ?><?= composant('barre-mecano') ?><?php endif ?>
  <main id="contenu" class="pro__contenu">
    <?= View::bloc('contenu') ?>
  </main>
  <?php if (empty($sansOnglets)): ?><?= composant('onglets-mecano', ['actif' => $onglet ?? 'statut', 'demandes' => $demandes ?? 0]) ?><?php endif ?>
</div>
<?php if ($flash = Session::flash('message')): ?><span hidden data-flash="<?= e($flash) ?>" data-flash-icone="coche"></span><?php endif ?>
<?php View::fin();
if (!empty($sonnerie)) { // un appel qui sonne ouvre l'écran M5 (RG-A2, RG-A5)
    $js = [...($js ?? []), 'js/pages/pro-sonnerie.js'];
}
echo View::inclure('layouts/base', get_defined_vars());
