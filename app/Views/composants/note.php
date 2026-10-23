<?php
/**
 * Note (Figma 53:446).
 * @var string $type    info | securite | erreur | danger
 * @var string $message HTML autorisé (déjà échappé par l'appelant si besoin)
 * @var ?string $actions HTML des boutons (ex. « Appeler le 185 »)
 */
$type ??= 'info';
$icones = ['info' => 'info', 'securite' => 'triangle', 'erreur' => 'triangle', 'danger' => 'triangle'];
?>
<div class="note note--<?= e($type) ?>" role="<?= $type === 'erreur' || $type === 'danger' ? 'alert' : 'note' ?>">
  <?= icone($icones[$type] ?? 'info') ?>
  <div><?= $message ?></div>
  <?php if (!empty($actions)): ?><div class="note__actions"><?= $actions ?></div><?php endif ?>
</div>
