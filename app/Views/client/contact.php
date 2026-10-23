<?php
/**
 * C6 · Avez-vous pu le joindre ? en pleine page (Figma 82:1322 · 84:1394 · 86:1488) : lien du bandeau (RG-I4) et sans JS.
 * @var string $etape
 * @var array<string, mixed> $contact
 */
use App\Core\View;

?>
<?= composant('retour-appel', ['mode' => 'page', 'contact' => $contact['public_id'], 'contenu' => View::inclure('client/contact-etape', get_defined_vars())]) ?>
