<?php
/**
 * Formulaire de contact : chaque appui crée un contact AVANT d'ouvrir l'appel (RG-A1). Branché sur /contact en partie 6.
 * @var string $mecano     public_id du mécano
 * @var ?string $recherche public_id de la recherche d'où l'on vient
 * @var string $canal      app | tel | whatsapp
 * @var string $contenu    HTML du bouton
 * @var bool $position     le client partage son lien de position (interrupteur du bloc message)
 */
?>
<form method="post" action="/contact" class="formulaire-contact" data-formulaire-contact>
  <?= csrf_champ() ?>
  <input type="hidden" name="mecano" value="<?= e($mecano) ?>">
  <?php if (!empty($recherche)): ?><input type="hidden" name="recherche" value="<?= e($recherche) ?>"><?php endif ?>
  <input type="hidden" name="canal" value="<?= e($canal) ?>">
  <input type="hidden" name="position" value="<?= ($position ?? true) ? '1' : '0' ?>" data-champ-position>
  <?= $contenu ?>
</form>
