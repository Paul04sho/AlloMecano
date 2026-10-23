<?php
/**
 * C7 · Signalement envoyé (Figma 90:1660) : numéro de suivi et fil « La suite » (RG-S2).
 * @var array<string, mixed> $signalement
 * @var string $heure
 */
use App\Core\Format;
use App\Services\Parametres;

$s = $signalement;
$jour = static fn (\DateTimeInterface $d): string => trim((string) preg_replace('/\s\d{4}$/', '', Format::date($d)));
$urgence = Parametres::texte('urgence.police', '170');
$etapes = [
    ['etat' => 'fait', 'titre' => 'Signalement reçu', 'detail' => Format::typo($jour($s['cree_le']) . ' · ' . Format::heure($s['cree_le']))],
    ['etat' => 'en_cours', 'titre' => 'Lu par une personne de l’équipe', 'detail' => Format::typo('Sous ' . Parametres::int('signalement.lecture_h', 24) . ' h · avant le ' . $jour($s['limite']) . ', ' . Format::heure($s['limite']))],
    ['etat' => 'a_venir', 'titre' => 'On vous rappelle si besoin', 'detail' => $s['telephone'] !== null ? 'Au ' . $s['telephone'] : 'Au numéro donné'],
];
?>
<article class="avis-page signalement-envoye avec-socle">
  <?= composant('barre-formulaire', ['icone' => 'fermer', 'retour' => '/', 'rubrique' => 'Signalement']) ?>
  <div class="avis-page__corps signalement-envoye__corps">
    <p class="t-etiquette t-secondaire">Envoyé à <?= e($heure) ?></p>
    <h1 class="t-titre-1"><?= e(Format::typo('C’est noté. Merci de nous avoir prévenus.')) ?></h1>
    <p class="t-base t-secondaire"><?= e(Format::typo('On vous rappelle si besoin. ' . $s['prenom'] . ' ne saura pas qui l’a signalé.')) ?></p>

    <section class="signalement-envoye__numero" aria-label="Votre numéro de signalement">
      <p class="t-etiquette t-secondaire">Votre numéro de signalement</p>
      <p class="t-donnee"><?= e($s['numero']) ?></p>
      <p class="t-petit t-secondaire">Gardez-le si vous nous rappelez.</p>
    </section>

    <section class="signalement-envoye__suite" aria-labelledby="suite-titre">
      <h2 class="t-etiquette t-secondaire" id="suite-titre">La suite</h2>
      <?= composant('frise-suivi', ['etapes' => $etapes]) ?>
    </section>

    <a class="lien-icone lien--erreur t-gras" href="tel:<?= e($urgence) ?>"><?= icone('telephone', 20) ?><span><?= e(Format::typo('En danger maintenant ? Appeler le ' . $urgence)) ?></span></a>
  </div>
  <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'Trouver un autre mécano', 'icone' => 'viseur', 'hauteur' => 56, 'plein' => true, 'href' => '/'])]) ?>
</article>
