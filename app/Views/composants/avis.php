<?php
/**
 * Avis (Figma 54:674) : étoiles 14, citation entre « », méta Mono « PRÉNOM · PANNE · DATE », réponse du mécano en retrait.
 * Jamais de numéro (RG-AV5). Étoiles bitume en clair, jaunes en sombre.
 * @var int $note              1 à 5
 * @var ?string $commentaire
 * @var string $prenom         « Aminata » ou « Un client »
 * @var string $panne          « Batterie »
 * @var string $depuis         « il y a 3 jours »
 * @var ?string $reponse       réponse publique du mécano (RG-AV8)
 * @var ?string $prenomMecano  « Koffi » : « RÉPONSE DE KOFFI »
 */
use App\Core\Format;

$note = max(1, min(5, (int) $note));
?>
<article class="avis">
  <span class="avis__etoiles" role="img" aria-label="Noté <?= $note ?> sur 5"><?php for ($i = 1; $i <= 5; $i++): ?><svg class="avis__etoile<?= $i > $note ? ' avis__etoile--vide' : '' ?>" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M7 1.63333L8.63333 5.01667L12.3083 5.48333L9.625 7.99167L10.325 11.6667L7 9.85833L3.675 11.6667L4.375 7.99167L1.69167 5.48333L5.36667 5.01667L7 1.63333Z"/></svg><?php endfor ?></span>
  <?php if (!empty($commentaire)): ?><p class="avis__citation t-citation">«<?= Format::FINE ?><?= e($commentaire) ?><?= Format::FINE ?>»</p><?php endif ?>
  <p class="avis__meta t-etiquette t-secondaire"><?= e($prenom) ?> · <?= e($panne) ?> · <?= e($depuis) ?></p>
  <?php if (!empty($reponse)): ?>
    <div class="avis__reponse">
      <p class="t-etiquette t-secondaire">Réponse de <?= e($prenomMecano ?? 'lui') ?></p>
      <p class="t-base"><?= e($reponse) ?></p>
    </div>
  <?php endif ?>
</article>
