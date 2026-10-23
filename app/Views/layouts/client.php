<?php
/**
 * Layout client : barre (logo + Espace pro), contenu, pied de page.
 * @var bool $sansBarre   écrans de parcours qui ont leur propre barre (C2a → C3)
 * @var bool $sansPied
 * @var bool $sansEspacePro  connexion : la barre n'a que le logo
 */
use App\Core\Session;
use App\Core\View;

View::debut('contenu'); ?>
<?php if (empty($sansBarre)): ?><?= composant('barre-client', ['pro' => empty($sansEspacePro)]) ?><?php endif ?>
<main id="contenu">
  <?= View::bloc('contenu') ?>
</main>
<?php if (empty($sansPied)): ?><?= composant('pied-de-page') ?><?php endif ?>
<?php if ($flash = Session::flash('message')): ?><span hidden data-flash="<?= e($flash) ?>" data-flash-icone="coche"></span><?php endif ?>
<?php View::fin();
echo View::inclure('layouts/base', get_defined_vars());
