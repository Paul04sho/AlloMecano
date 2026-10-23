<?php
/**
 * Squelette HTML commun.
 * @var string $titre     titre de la page (sans « · allomécano »)
 * @var ?string $description
 * @var list<string> $css  feuilles propres à la page
 * @var list<string> $js   modules propres à la page
 * @var ?string $classeCorps
 */
use App\Core\Assets;
use App\Core\Csrf;
use App\Core\View;

$theme = $_COOKIE['theme'] ?? null;
$theme = in_array($theme, ['clair', 'sombre'], true) ? $theme : null;
?>
<!doctype html>
<html lang="fr"<?= $theme ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
  <meta charset="utf-8">
  <script>document.documentElement.classList.add('js')</script>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e(isset($titre) ? $titre . ' · allomécano' : 'allomécano · Le mécano vérifié le plus proche') ?></title>
  <meta name="description" content="<?= e($description ?? 'En panne à Abidjan ? Trouvez le mécanicien vérifié et disponible le plus proche, et appelez-le directement.') ?>">
  <meta name="theme-color" content="#15171c">
  <meta name="csrf" content="<?= e(Csrf::jeton()) ?>">
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
