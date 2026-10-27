<?php
/**
 * Squelette HTML commun.
 * @var string $titre     titre de la page (sans « · allomécano »)
 * @var ?string $description
 * @var list<string> $css  feuilles propres à la page
 * @var list<string> $js   modules propres à la page
 * @var ?string $classeCorps
 * @var bool $noindex  l'administration n'est jamais indexée
 */
use App\Core\Assets;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

$theme = $_COOKIE['theme'] ?? null;
$theme = in_array($theme, ['clair', 'sombre'], true) ? $theme : null;
?>
<!doctype html>
<html lang="fr"<?= $theme ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
  <meta charset="utf-8">
  <script>document.documentElement.classList.add('js')</script>
  <?php /* Transitions entre pages : déclarées ici, en ligne, car la page d'arrivée doit les accepter avant son premier rendu (styles dans transition-vue.css) */ ?>
  <style>@media (prefers-reduced-motion: no-preference) { @view-transition { navigation: auto; } }</style>
  <script>
    // Arrivée par une transition de page : pas de seconde entrée ; au retour, la page vient du haut
    addEventListener('pagereveal', function (e) {
      if (!e.viewTransition) return;
      document.documentElement.classList.add('vue-transition');
      e.viewTransition.ready.catch(function () {}); // transition sautée (page cachée, élément manquant) : la page s'affiche normalement
      var a = window.navigation && navigation.activation;
      if (a && a.from && a.entry && a.entry.index < a.from.index) e.viewTransition.types.add('retour');
    });
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(isset($titre) ? $titre . ' · allomécano' : 'allomécano · Le mécano vérifié le plus proche') ?></title>
  <meta name="description" content="<?= e($description ?? 'En panne à Abidjan ? Trouvez le mécanicien vérifié et disponible le plus proche, et appelez-le directement.') ?>">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif ?>
  <meta name="theme-color" content="#15171c">
  <meta name="csrf" content="<?= e(Csrf::jeton()) ?>">
  <?php if ($jetonClient = Session::get('client_jeton_neuf')): Session::oublier('client_jeton_neuf'); /* RG-C7 : copie confiée une fois au navigateur */ ?>
  <meta name="client-jeton" content="<?= e($jetonClient) ?>">
  <?php endif ?>
  <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="preload" href="/assets/fonts/archivo-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/atkinson-next-latin.woff2" as="font" type="font/woff2" crossorigin>
  <?= Assets::css($css ?? []) ?>
  <?= View::bloc('tete') ?>
</head>
<body class="<?= e($classeCorps ?? '') ?>">
  <?= composant('demarrage') ?>
  <a class="sr-only" href="#contenu">Aller au contenu</a>
  <?= View::bloc('contenu') ?>
  <div class="toasts" aria-live="polite" aria-atomic="false" data-toasts></div>
  <?= Assets::js($js ?? []) ?>
</body>
</html>
