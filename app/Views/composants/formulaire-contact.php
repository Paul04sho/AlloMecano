<?php
/**
 * Formulaire de contact : chaque appui crée un contact AVANT d'ouvrir l'appel (RG-A1). Poste vers /contacter ; le JS passe par /api/contacts.
 * @var string $mecano     public_id du mécano
 * @var ?string $recherche public_id de la recherche d'où l'on vient
 * @var string $canal      app | tel | whatsapp
 * @var string $contenu    HTML du bouton
 * @var bool $position     le client partage son lien de position (interrupteur du bloc message)
 * @var ?string $precedent public_id du contact raté qui mène à celui-ci (RG-I5)
 * @var bool $reveler      D1 : rendre le numéro et le QR code dans la page au lieu d'ouvrir l'appel
 */
?>
<form method="post" action="/contacter" class="formulaire-contact" data-formulaire-contact data-absence-min="<?= \App\Services\Parametres::int('retour.absence_min_s', 3) ?>"<?= !empty($reveler) ? ' data-reveler' : '' ?>>
  <?= csrf_champ() ?>
  <input type="hidden" name="mecano" value="<?= e($mecano) ?>">
  <?php if (!empty($recherche)): ?><input type="hidden" name="recherche" value="<?= e($recherche) ?>"><?php endif ?>
  <input type="hidden" name="canal" value="<?= e($canal) ?>">
  <input type="hidden" name="position" value="<?= ($position ?? true) ? '1' : '0' ?>" data-champ-position>
  <?php if (!empty($precedent)): ?><input type="hidden" name="precedent" value="<?= e($precedent) ?>"><?php endif ?>
  <?= $contenu ?>
</form>
