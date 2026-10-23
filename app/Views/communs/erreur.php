<?php
/**
 * X6 · Route barrée (Figma 145:481) — et les autres erreurs sur le même gabarit.
 * @var string $titre
 * @var int $statut
 * @var string $message
 */
$textes = [
    404 => 'Cette page n’existe pas, ou plus. On vous remet sur la bonne voie.',
    405 => 'Cette page n’existe pas, ou plus. On vous remet sur la bonne voie.',
    403 => 'Cette page est réservée. Connectez-vous avec le bon compte.',
    419 => 'Par sécurité, la page a expiré. Revenez en arrière et recommencez : vos informations sont gardées.',
];
$texte = $textes[$statut] ?? 'Quelque chose a cassé chez nous. Réessayez dans un instant ; si c’est urgent, appelez directement un mécano depuis l’accueil.';
?>
<section class="erreur">
  <picture class="erreur__illustration">
    <img src="/assets/img/route-barree-780.webp"
         srcset="/assets/img/route-barree-780.webp 780w, /assets/img/route-barree-1170.webp 1170w"
         sizes="(min-width: 1024px) 640px, 100vw"
         width="780" height="520"
         alt="Un mécanicien se gratte la tête devant une barrière de chantier : le 0 de « 404 » est le repère d’allomécano.">
  </picture>
  <div class="erreur__texte conteneur">
    <h1 class="t-display-l"><?= e($titre) ?></h1>
    <p class="t-base"><?= e($texte) ?></p>
    <?php if ($message !== '' && !in_array($statut, [404, 405], true)): ?><p class="t-donnee t-secondaire"><?= e($message) ?></p><?php endif ?>
    <?= composant('bouton', ['libelle' => 'Trouver un mécano', 'icone' => 'viseur', 'href' => '/position', 'plein' => true]) ?>
    <a class="bouton-lien" href="/">Retour à l’accueil</a>
  </div>
</section>
