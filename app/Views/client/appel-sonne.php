<?php
/**
 * C5c · Appel intégré, côté client : « Ça sonne » (Figma 269:1820) puis « En ligne » (269:1866). Page sombre plein écran.
 * Le WebRTC est mené par js/pages/appel.js ; sans JS (ou sans WebRTC), « Appeler » n'ouvre jamais cette page (RG-A2).
 * @var array<string, mixed> $contact
 * @var string $retour
 * @var int $sonnerie_s
 * @var int $echec_s
 * @var string $presentation « Voiture · Batterie · Riviera 2, près de la pharmacie des Jardins »
 */
use App\Core\Format;

$c = $contact;
$ligne2 = implode(' · ', array_filter([$c['metier'], $c['distance_m'] !== null ? Format::distance($c['distance_m']) : null, $c['quartier_mecano']]));
?>
<div class="appel appel--sombre sur-sombre" data-appel data-contact="<?= e($c['public_id']) ?>" data-phase="sonne" data-statut="<?= e($c['statut']) ?>"
     data-sonnerie="<?= $sonnerie_s ?>" data-echec="<?= $echec_s ?>" data-prenom="<?= e($c['prenom']) ?>">
  <header class="appel__entete t-etiquette"><?= icone('viseur', 18) ?><span>Appel allomécano · gratuit par Internet</span></header>

  <?= composant('anneau-appel', ['portrait' => $c['portrait'], 'nom' => $c['nom_complet'], 'phase' => 'sonne']) ?>

  <div class="appel__identite">
    <h1 class="appel__nom"><?= e($c['nom_complet']) ?></h1>
    <p class="appel__metier t-petit"><?= e($ligne2) ?></p>
    <div class="appel__etat" data-etat aria-live="polite">
      <?= composant('etat-appel', ['voyant' => 'recherche', 'texte' => 'Ça sonne chez ' . $c['prenom'], 'chrono' => '0:00', 'attrs' => ['data-phase-sonne' => '']]) ?>
      <?= composant('etat-appel', ['texte' => 'En ligne', 'chrono' => '00:00', 'attrs' => ['data-phase-ligne' => '', 'hidden' => 'hidden']]) ?>
    </div>
  </div>

  <section class="appel__voit" aria-label="Ce que <?= e($c['prenom']) ?> voit">
    <p class="t-gras"><?= e($c['prenom']) ?> voit votre panne et votre position.</p>
    <p class="t-donnee"><?= e($presentation) ?></p>
  </section>

  <div class="appel__commandes">
    <?= composant('commande-appel', ['icone' => 'micro', 'libelle' => 'Micro', 'attrs' => ['data-commande' => 'micro']]) ?>
    <?= composant('commande-appel', ['icone' => 'haut-parleur', 'libelle' => 'Haut-parleur', 'attrs' => ['data-commande' => 'haut-parleur']]) ?>
  </div>

  <?= composant('bouton', ['libelle' => 'Raccrocher', 'icone' => 'raccrocher', 'type' => 'danger', 'hauteur' => 64, 'plein' => true, 'attrs' => ['data-raccrocher' => '', 'class' => 'appel__raccrocher']]) ?>

  <audio data-audio autoplay playsinline></audio>
  <noscript><p class="appel__sans-js t-petit">L’appel intégré a besoin de JavaScript. <a class="lien" href="<?= e($retour) ?>">Revenir à la liste</a> et appeler directement.</p></noscript>
</div>
