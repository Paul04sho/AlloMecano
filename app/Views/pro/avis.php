<?php
/**
 * M3 · Avis (Figma 118:1060) : la note, la répartition des avis publiés, les plus récents avec la réponse publique du mécano (RG-AV8)
 * et le signalement d'un avis abusif (examiné par l'équipe, qui peut le masquer, RG-AV4).
 * @var array{note: ?float, nb: int, depuis: ?string, repartition: array<int, int>, avis: list<array<string, mixed>>, reste: int, prenom: string} $page
 * @var bool $tous        tous les avis, pas seulement les plus récents
 * @var ?string $ouvert   avis dont le champ de réponse est ouvert (public_id du contact)
 * @var ?string $erreur
 * @var ?string $saisie
 */
use App\Core\Format;

$nb = $page['nb'];
?>
<div class="tableau tableau--avis">
  <header class="tableau__entete">
    <p class="t-etiquette t-secondaire">Avis des clients</p>
    <h1 class="t-titre-1">Ce que vos clients disent de vous</h1>
  </header>

  <?php if ($nb === 0): ?>
    <p class="t-base t-secondaire">Vos premiers avis apparaîtront ici : un client peut en laisser un après avoir vraiment parlé avec vous.</p>
  <?php else: ?>
    <?= composant('note-globale', ['note' => Format::nombre((float) $page['note'], 1), 'nombre' => $nb . ' avis', 'depuis' => $page['depuis'] !== null ? 'Depuis votre validation, le ' . $page['depuis'] . '.' : null]) ?>

    <section class="repartition-avis" aria-labelledby="repartition-titre">
      <h2 class="t-etiquette t-secondaire" id="repartition-titre">Répartition des <?= $nb ?> avis</h2>
      <?php foreach ([5, 4, 3, 2, 1] as $e): ?><?= composant('barre-etoiles', ['etoiles' => $e, 'nombre' => $page['repartition'][$e], 'total' => $nb]) ?><?php endforeach ?>
    </section>

    <section class="liste-avis" aria-labelledby="recents-titre">
      <div class="liste-avis__entete">
        <h2 class="t-etiquette t-secondaire" id="recents-titre">Les plus récents</h2>
        <p class="t-petit t-secondaire">Vous pouvez répondre une fois, publiquement, à chaque avis. Les clients lisent vos réponses avant d’appeler.</p>
      </div>
      <?php foreach ($page['avis'] as $a): $ici = $ouvert === $a['contact']; ?>
        <div class="liste-avis__avis" id="avis-<?= e($a['contact']) ?>">
          <?= composant('avis', ['note' => $a['note'], 'commentaire' => $a['commentaire'], 'prenom' => $a['prenom'], 'panne' => $a['panne'], 'depuis' => $a['depuis'], 'reponse' => $a['reponse'], 'prenomMecano' => $page['prenom']]) ?>
          <?= composant('actions-avis', ['contact' => $a['contact'], 'repondu' => $a['reponse'] !== null && !$ici, 'ouvert' => $ici, 'tous' => $tous, 'reponse' => $ici ? ($saisie ?? $a['reponse']) : null, 'erreur' => $ici ? $erreur : null]) ?>
        </div>
      <?php endforeach ?>
      <?php if ($page['reste'] > 0): ?><a class="lien-icone t-gras liste-avis__plus" href="/pro/avis?avis=tous"><span>Voir les <?= $page['reste'] ?> autres avis</span><?= icone('chevron', 20) ?></a><?php endif ?>
    </section>
  <?php endif ?>
</div>
