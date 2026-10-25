<?php
/**
 * M3 · Demandes (Figma 116:967) : les clients qui ont contacté le mécano ces 7 derniers jours, jour par jour, avec le retour du client (« joint » ou non).
 * Le numéro d'un client n'apparaît que s'il l'a laissé, et il est masqué après 48 h (RG-P1, RG-C7). La pastille de l'onglet compte les clients à rappeler (24 h).
 * @var array{maj: string, total: int, titre: string, jours: list<array{libelle: string, masque: bool, lignes: list<array<string, mixed>>}>} $liste
 */
?>
<div class="tableau tableau--demandes">
  <header class="tableau__entete">
    <p class="t-etiquette t-secondaire">Demandes · mis à jour à <?= e($liste['maj']) ?></p>
    <h1 class="t-titre-1"><?= e($liste['titre']) ?></h1>
    <?php if ($liste['total'] > 0): ?><p class="t-petit t-secondaire">Appels et messages WhatsApp reçus via allomécano ces 7 derniers jours.</p>
    <?php else: ?><p class="t-petit t-secondaire">Les clients qui vous appellent ou vous écrivent depuis allomécano apparaîtront ici, pendant 7 jours.</p><?php endif ?>
  </header>

  <?php if ($liste['total'] > 0): ?>
    <ul class="liste-demandes" role="list">
      <?php foreach ($liste['jours'] as $jour): ?>
        <?= composant('jour-demandes', ['libelle' => $jour['libelle']]) ?>
        <?php foreach ($jour['lignes'] as $ligne): ?><?= composant('ligne-contact', ['demande' => $ligne]) ?><?php endforeach ?>
      <?php endforeach ?>
    </ul>
    <?= composant('note', ['type' => 'info', 'message' => 'Après 48&nbsp;h, le numéro du client est masqué pour protéger sa vie privée. S’il a encore besoin de vous, il peut vous rappeler.']) ?>
  <?php endif ?>
</div>
