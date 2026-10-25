<?php
/**
 * Dossier refusé (pas dessiné dans Figma : même gabarit que M2b suspendu). Le motif de l'équipe est cité tel qu'il a été écrit (RG-DO6) ; le support répond sur WhatsApp.
 * @var array<string, mixed> $profil
 * @var string $numero
 * @var string $whatsapp
 */
?>
<div class="tableau tableau--suspendu">
  <?= composant('en-tete-pro', ['prenom' => $profil['prenom'], 'ligne' => $profil['en_tete'], 'avatar' => $avatar, 'verifie' => false]) ?>
  <section class="reprendre" aria-labelledby="refuse-titre">
    <p class="reprendre__rubrique t-etiquette"><?= composant('voyant', ['etat' => 'erreur']) ?>Dossier refusé · N° <?= e($numero) ?></p>
    <h1 class="t-titre-1" id="refuse-titre">Votre dossier n’a pas pu être validé.</h1>
    <?php if (!empty($profil['motif_refus'])): ?>
      <div class="refus__motif"><p class="t-etiquette t-secondaire">Motif de l’équipe</p><p class="t-base">«&#8239;<?= e($profil['motif_refus']) ?>&#8239;»</p></div>
    <?php endif ?>
    <p class="t-base t-secondaire">Une personne de l’équipe vous répond sous 24&#8239;h, sur WhatsApp, si vous voulez en parler.</p>
  </section>
</div>
<?= composant('socle', ['info' => 'Support WhatsApp · réponse sous 24 h', 'actions' => composant('bouton', ['libelle' => 'Contacter l’équipe', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'icone' => 'whatsapp', 'href' => $whatsapp, 'attrs' => ['target' => '_blank', 'rel' => 'noopener']])]) ?>
