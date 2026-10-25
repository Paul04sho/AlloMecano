<?php
/**
 * Rail admin (Figma 56:470 · 56:635) : 248 de large, bitume, logo béton, cinq entrées de 44 en Label.
 * L'entrée active : fond panneau et filet jaune de 3 px à gauche. Compteurs en Mono. En bas : l'admin connecté et « Se déconnecter ».
 * @var string $actif   tableau | dossiers | comptes | administrateurs | signalements | statistiques | mon-compte
 * @var array{dossiers: int, signalements: int} $compteurs
 * @var string $admin   « Mariam Koné »
 * @var bool $estPrincipal
 */
use App\Core\Format;

$entrees = [
    'tableau' => ['Tableau de bord', '/admin', null],
    'dossiers' => ['Dossiers', '/admin/dossiers', $compteurs['dossiers'] ?? 0],
    'comptes' => ['Comptes', '/admin/comptes', null],
    'signalements' => ['Signalements', '/admin/signalements', $compteurs['signalements'] ?? 0],
    'statistiques' => ['Statistiques', '/admin/statistiques', null],
];
if (!empty($estPrincipal)) {
    $entrees['administrateurs'] = ['Administrateurs', '/admin/administrateurs', null];
}
?>
<aside class="rail-admin sur-sombre">
  <a class="rail-admin__logo" href="/admin" aria-label="allomécano, tableau de bord"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a>
  <p class="rail-admin__rubrique t-etiquette">Administration</p>
  <nav class="rail-admin__entrees" aria-label="Administration">
    <?php foreach ($entrees as $cle => [$libelle, $href, $nombre]): ?>
      <a class="rail-admin__entree t-label<?= $actif === $cle ? ' rail-admin__entree--active' : '' ?>" href="<?= e($href) ?>"<?= $actif === $cle ? ' aria-current="page"' : '' ?>>
        <span><?= e($libelle) ?></span>
        <?php if ($nombre !== null && $nombre > 0): ?><span class="t-donnee"><?= (int) $nombre ?></span><?php endif ?>
      </a>
    <?php endforeach ?>
  </nav>
  <form class="rail-admin__admin" method="post" action="/deconnexion">
    <?= csrf_champ() ?>
    <p class="t-label"><?= e($admin) ?></p>
    <a class="rail-admin__compte t-etiquette" href="/admin/mon-compte"<?= $actif === 'mon-compte' ? ' aria-current="page"' : '' ?>>Mon compte</a>
    <button type="submit" class="t-etiquette">Admin · Se déconnecter</button>
  </form>
</aside>
