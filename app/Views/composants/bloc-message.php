<?php
/**
 * Bloc « Ce qu'il faut lui dire » (Figma 54:675) : surface creuse, filet gauche bitume 4 px. Reprend mot pour mot le repère saisi en C2b.
 * « Copier » → toast ; l'interrupteur « Lien de ma position » retire la fin du message en fondu (160 ms).
 * @var array{sans: string, position: ?string} $message
 * @var string $idInterrupteur
 */
use App\Core\Format;

$position = $message['position'];
?>
<section class="bloc-message" data-bloc-message aria-labelledby="<?= e($idInterrupteur) ?>-titre">
  <h2 class="t-etiquette t-secondaire" id="<?= e($idInterrupteur) ?>-titre">Ce qu’il faut lui dire</h2>
  <p class="bloc-message__texte t-base" data-message><span data-message-debut><?= e($message['sans']) ?></span><?php if ($position !== null): ?> <span data-message-position><?= e($position) ?></span><?php endif ?></p>
  <div class="bloc-message__actions">
    <button type="button" class="bloc-message__copier t-label" data-copier><?= icone('copier', 18) ?><span>Copier</span></button>
    <?php if ($position !== null): ?><?= composant('interrupteur', ['id' => $idInterrupteur, 'libelle' => 'Lien de ma position', 'actif' => true, 'attrs' => ['data-interrupteur-position' => '']]) ?><?php endif ?>
  </div>
  <p class="t-petit t-secondaire"><?= e(Format::typo('C’est le message prêt dans WhatsApp. Rien n’est envoyé sans vous.')) ?></p>
</section>
