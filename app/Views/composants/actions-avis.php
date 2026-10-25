<?php
/**
 * Actions sous un avis (Figma 118:1145) : « Répondre » (une seule réponse publique par avis, RG-AV8) et « Signaler un avis abusif » en rouge souligné.
 * Un avis déjà répondu n'a plus que le signalement. « Répondre » ouvre le champ de réponse (l'adresse de la page porte ?repondre=…), sans JavaScript.
 * @var string $contact     public_id du contact qui a permis l'avis (RG-C8)
 * @var bool $repondu
 * @var bool $ouvert        le champ de réponse est ouvert
 * @var ?string $reponse    réponse actuelle (champ prérempli)
 * @var ?string $erreur
 * @var bool $tous          la page montre tous les avis : le lien « Répondre » et « Annuler » y reviennent
 */
$repondu ??= false;
$ouvert ??= false;
$reponse ??= null;
$erreur ??= null;
?>
<div class="actions-avis">
  <div class="actions-avis__ligne">
    <?php if (!$repondu && !$ouvert): ?><a class="bouton bouton--contour bouton--48" href="/pro/avis?repondre=<?= e(rawurlencode($contact)) ?><?= !empty($tous) ? '&amp;avis=tous' : '' ?>#avis-<?= e($contact) ?>"><?= icone('crayon') ?><span class="bouton__libelle">Répondre</span></a><?php endif ?>
    <form method="post" action="/pro/avis/<?= e(rawurlencode($contact)) ?>/signaler" class="actions-avis__signaler"><?= csrf_champ() ?>
      <button type="submit" class="actions-avis__lien t-label"><?= icone('drapeau', 18) ?><span>Signaler un avis abusif</span></button>
    </form>
  </div>
  <?php if ($ouvert): ?>
    <form method="post" action="/pro/avis/<?= e(rawurlencode($contact)) ?>/repondre" class="actions-avis__reponse"><?= csrf_champ() ?>
      <?= composant('champ', ['nom' => 'reponse', 'label' => 'Votre réponse, visible par tous', 'type' => 'textarea', 'valeur' => $reponse, 'max' => 600, 'lignes' => 3, 'erreur' => $erreur, 'aide' => 'Une seule réponse publique par avis. Les clients la lisent avant d’appeler.']) ?>
      <div class="actions-avis__boutons">
        <?= composant('bouton', ['libelle' => 'Publier ma réponse', 'type' => 'principal', 'hauteur' => 48, 'attrs' => ['type' => 'submit']]) ?>
        <a class="actions-avis__annuler t-label" href="/pro/avis<?= !empty($tous) ? '?avis=tous' : '' ?>#avis-<?= e($contact) ?>">Annuler</a>
      </div>
    </form>
  <?php endif ?>
</div>
