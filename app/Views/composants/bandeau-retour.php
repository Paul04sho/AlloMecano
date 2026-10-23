<?php
/**
 * Bandeau « Avez-vous pu joindre Koffi ? » (RG-I4) : à la visite suivante, pendant 24 h, tant que le client n'a pas répondu.
 * Même Note info que le reste du site ; « Répondre » ouvre C6 en pleine page. Pas de maquette dédiée : texte et placement à valider.
 * @var string $public_id public_id du contact
 * @var string $prenom
 * @var ?string $texte  autre question (bandeau d'avis, RG-AV7 : « Comment ça s'est passé avec Koffi ? »)
 * @var ?string $href   adresse du bouton (par défaut le retour d'appel)
 * @var ?string $action libellé du bouton (« Répondre »)
 */
use App\Core\Format;
?>
<div class="bandeau-retour">
  <?= composant('note', ['type' => 'info', 'message' => e(Format::typo($texte ?? ('Avez-vous pu joindre ' . $prenom . ' ?'))),
      'actions' => composant('bouton', ['libelle' => $action ?? 'Répondre', 'type' => 'contour', 'hauteur' => 44, 'href' => $href ?? '/contact/' . $public_id])]) ?>
</div>
