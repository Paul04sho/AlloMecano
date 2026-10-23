<?php
/**
 * C6b · « Merci Aminata. On est ensemble. » (Figma 88:1609) : l'avis est publié (RG-AV4).
 * @var array<string, mixed> $contact
 * @var array<string, mixed> $avis
 * @var string $heure
 */
use App\Core\Format;

$c = $contact;
$note = (int) $avis['note'];
?>
<article class="avis-merci">
  <div class="avis-merci__haut">
    <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-09', 'texte' => 'Le mécano arrive à moto, Riviera'], 'alt' => 'Un mécano arrive à moto.', 'legende' => mb_strtoupper(($c['lieu'] ?? 'Abidjan') . ' · ' . Format::heure($avis['cree_le']), 'UTF-8'), 'classe' => 'avis-merci__photo']) ?>
    <a class="bouton-icone bouton-icone--bitume avis-merci__fermer" href="/" aria-label="Fermer"><?= icone('fermer') ?></a>
  </div>
  <div class="avis-merci__corps">
    <p class="t-etiquette t-secondaire">Avis publié · <?= e($heure) ?></p>
    <h1 class="t-titre-1">Merci <?= e($avis['prenom'] ?? '') ?>. On est ensemble.</h1>
    <p class="t-base"><?= e(Format::typo('Votre avis aide le prochain qui tombe en panne.')) ?></p>
    <section class="avis-merci__recap" aria-label="Votre avis">
      <span class="avis-merci__visage"><?php if (!empty($c['portrait'])): ?><img src="<?= e($c['portrait']['src']) ?>" width="40" height="40" alt="" style="background:<?= e($c['portrait']['couleur']) ?>;object-position:<?= e($c['portrait']['cadrage']) ?>"><?php endif ?></span>
      <div>
        <p class="t-label">Votre avis sur <?= e($c['nom_complet']) ?></p>
        <span class="avis__etoiles" role="img" aria-label="Noté <?= $note ?> sur 5"><?php for ($i = 1; $i <= 5; $i++): ?><svg class="avis__etoile<?= $i > $note ? ' avis__etoile--vide' : '' ?>" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M7 1.63333L8.63333 5.01667L12.3083 5.48333L9.625 7.99167L10.325 11.6667L7 9.85833L3.675 11.6667L4.375 7.99167L1.69167 5.48333L5.36667 5.01667L7 1.63333Z"/></svg><?php endfor ?></span>
        <?php if (!empty($avis['commentaire'])): ?><p class="t-petit t-secondaire">«<?= Format::FINE ?><?= e($avis['commentaire']) ?><?= Format::FINE ?>»</p><?php endif ?>
      </div>
    </section>
    <a class="lien t-gras" href="/">Retour à l’accueil</a>
    <?php if (!empty($avis['modifiable'])): ?><a class="lien t-petit avis-merci__modifier" href="/avis/<?= e($c['public_id']) ?>">Modifier mon avis (24 h)</a><?php endif ?>
  </div>
</article>
