<?php
/**
 * Feuille « Avez-vous pu le joindre ? » (Figma 82:1322 · 84:1394 · 86:1488) : feuille basse à 80 %, coupe L, sur un aplat socle uni (aucune photo, aucun voile).
 * mode « page » : pleine page (lien du bandeau, sans JS). mode « feuille » : posée par-dessus la page quand le client revient de l'appel (moment 6).
 * @var string $mode     page | feuille
 * @var string $contenu  HTML de l'étape (client/contact-etape)
 * @var ?string $contact public_id du contact
 */
$mode ??= 'page';
?>
<div class="retour retour--<?= e($mode) ?>" data-retour-appel<?= !empty($contact) ? ' data-contact="' . e($contact) . '"' : '' ?><?= $mode === 'feuille' ? ' role="dialog" aria-modal="true" aria-labelledby="retour-titre"' : '' ?>>
  <div class="retour__voile" data-retour-voile aria-hidden="true"></div>
  <section class="retour__feuille" data-retour-feuille<?= $mode === 'page' ? ' aria-labelledby="retour-titre"' : '' ?>>
    <div class="retour__interieur">
      <span class="retour__poignee" aria-hidden="true"></span>
      <div class="retour__contenu" data-retour-contenu aria-live="polite"><?= $contenu ?></div>
    </div>
  </section>
</div>
