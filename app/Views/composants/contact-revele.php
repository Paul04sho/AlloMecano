<?php
/**
 * Contact · numéro révélé (Figma 159:858, D1) : QR code « tel: », numéro copiable, « Ouvrir WhatsApp Web ».
 * Affiché seulement après la création du contact (RG-A1) : le numéro n'est jamais dans la page avant. Branché en partie 6.
 * @var string $prenom
 * @var string $telephone    E.164 (+2250707123456)
 * @var string $lienWhatsApp lien wa.me avec le message prêt
 */
use App\Core\Format;
use App\Services\QrCode;

$affiche = Format::telephone($telephone);
$court = trim(preg_replace('/^\+225\s*/u', '', str_replace(Format::INSECABLE, ' ', $affiche)) ?? $affiche);
?>
<section class="contact-revele" aria-label="Numéro de <?= e($prenom) ?>">
  <div class="contact-revele__ligne">
    <span class="contact-revele__qr" role="img" aria-label="QR code pour appeler <?= e($prenom) ?>"><?= QrCode::svg('tel:' . $telephone, 112) ?></span>
    <div class="contact-revele__numero">
      <p class="t-etiquette t-secondaire">Numéro de <?= e($prenom) ?></p>
      <p class="contact-revele__chiffres t-borne-l"><span data-numero><?= e($court) ?></span><button type="button" class="bouton-icone contact-revele__copier" data-copier-numero aria-label="Copier le numéro"><?= icone('copier', 22) ?></button></p>
      <?= composant('bouton', ['libelle' => 'Ouvrir WhatsApp Web', 'icone' => 'whatsapp', 'type' => 'secondaire', 'hauteur' => 52, 'plein' => true, 'href' => $lienWhatsApp, 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]) ?>
    </div>
  </div>
  <p class="t-petit t-secondaire">Scannez le QR code pour appeler avec votre téléphone.</p>
</section>
