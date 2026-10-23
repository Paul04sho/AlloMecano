<?php
/**
 * Bandeau « Avez-vous pu joindre Koffi ? » (RG-I4) : à la visite suivante, pendant 24 h, tant que le client n'a pas répondu.
 * Même Note info que le reste du site ; « Répondre » ouvre C6 en pleine page. Pas de maquette dédiée : texte et placement à valider.
 * @var string $public_id public_id du contact
 * @var string $prenom
 */
use App\Core\Format;
?>
<div class="bandeau-retour">
  <?= composant('note', ['type' => 'info', 'message' => e(Format::typo('Avez-vous pu joindre ' . $prenom . ' ?')),
      'actions' => composant('bouton', ['libelle' => 'Répondre', 'type' => 'contour', 'hauteur' => 44, 'href' => '/contact/' . $public_id])]) ?>
</div>
