<?php
/**
 * Layout de l'administration (desktop, Figma A0 à A5) : le rail de 248 à gauche, le contenu à 48 de lui, jamais indexé.
 * @var string $actif                 entrée active du rail
 * @var array{dossiers: int, signalements: int} $compteurs
 * @var string $admin                 nom de l'admin connecté
 */
use App\Core\Session;
use App\Core\View;
use App\Services\AdminService;

$noindex = true;
$idAdmin = Session::get('admin_id');
$profilAdmin = is_int($idAdmin) ? AdminService::depuisBase()->admin($idAdmin) : null;
$estPrincipal = (bool) ($profilAdmin['est_principal'] ?? false);
View::debut('contenu'); ?>
<div class="admin">
  <?= composant('rail-admin', ['actif' => $actif, 'compteurs' => $compteurs, 'admin' => $admin, 'estPrincipal' => $estPrincipal]) ?>
  <main id="contenu" class="admin__contenu">
    <?= View::bloc('contenu') ?>
  </main>
</div>
<?php if ($flash = Session::flash('message')): ?><span hidden data-flash="<?= e($flash) ?>" data-flash-icone="coche"></span><?php endif ?>
<?php View::fin();
echo View::inclure('layouts/base', get_defined_vars());
