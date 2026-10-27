<?php
/**
 * A0 · Tableau de bord (Figma 164:14) : les quatre indicateurs, la file des 5 dossiers les plus anciens, les disponibles par commune, les mises en relation sur 30 jours.
 * @var \DateTimeImmutable $maintenant
 * @var array<string, mixed> $indicateurs
 * @var list<array<string, mixed>> $anciens
 * @var int $enAttente
 * @var list<array{nom: string, etat: string, nombre: int}> $communes
 * @var array<string, mixed> $relations
 */
use App\Core\Format;

$i = $indicateurs;
$jour = static fn (\DateTimeImmutable $d): string => trim((string) preg_replace('/\s\d{4}$/', '', Format::date($d)));
$nombre = static fn (int $n): string => Format::nombre($n);
?>
<header class="admin__entete">
  <p class="t-etiquette t-secondaire"><?= e(Format::dateLongue($maintenant) . ' · mis à jour à ' . Format::heure($maintenant)) ?></p>
  <h1 class="admin__titre">Tableau de bord</h1>
</header>

<section class="indicateurs" aria-label="Indicateurs" data-apparition-defilement>
  <?= composant('indicateur', ['valeur' => $i['attente'], 'libelle' => 'Dossiers en attente' . ($i['plus_ancien_h'] !== null ? ' · le plus ancien : ' . $i['plus_ancien_h'] . ' h' : '')]) ?>
  <?= composant('indicateur', ['valeur' => $i['signalements']['ouverts'], 'titre' => 'Signalements ouverts', 'libelle' => $i['signalements']['critiques'] > 0 ? 'Dont ' . $i['signalements']['critiques'] . ' critique' . ($i['signalements']['critiques'] > 1 ? 's' : '') : 'Aucun critique', 'etat' => $i['signalements']['critiques'] > 0 ? 'erreur' : 'dispo']) ?>
  <?= composant('indicateur', ['valeur' => $i['disponibles'], 'libelle' => 'Disponibles maintenant', 'etat' => 'dispo']) ?>
  <?= composant('indicateur', ['valeur' => $i['joignables_pct'] ?? '–', 'unite' => $i['joignables_pct'] !== null ? '%' : null, 'libelle' => 'Clients qui ont pu joindre leur mécano (7 j)']) ?>
</section>

<div class="admin__colonnes">
  <section class="admin__file" aria-labelledby="file-titre" data-apparition-defilement>
    <div class="admin__rubrique">
      <p class="t-etiquette t-secondaire">File d’attente · <?= (int) $enAttente ?> dossier<?= $enAttente > 1 ? 's' : '' ?> · le plus ancien d’abord</p>
      <h2 class="t-titre-1" id="file-titre"><?= count($anciens) > 1 ? 'Les ' . count($anciens) . ' plus anciens' : 'Le plus ancien' ?></h2>
    </div>
    <?php if ($anciens === []): ?>
      <p class="admin__vide t-base t-secondaire">Aucun dossier en attente. La file est vide.</p>
    <?php else: ?>
      <ul class="admin__liste" role="list">
        <?php foreach ($anciens as $k => $d): ?><?= composant('ligne-dossier', ['dossier' => $d, 'mode' => 'liste', 'principal' => $k === 0]) ?><?php endforeach ?>
      </ul>
    <?php endif ?>
    <a class="lien-icone t-gras admin__voir" href="/admin/dossiers"><span>Voir toute la file · <?= (int) $enAttente ?> dossier<?= $enAttente > 1 ? 's' : '' ?></span><?= icone('chevron', 20) ?></a>
  </section>

  <div class="admin__activite">
    <section aria-labelledby="communes-titre" data-apparition-defilement>
      <div class="admin__rubrique">
        <p class="t-etiquette t-secondaire">Maintenant · <?= (int) $i['disponibles'] ?> mécano<?= $i['disponibles'] > 1 ? 's' : '' ?> disponible<?= $i['disponibles'] > 1 ? 's' : '' ?></p>
        <h2 class="t-titre-3" id="communes-titre">Disponibles par commune</h2>
      </div>
      <?= composant('barres-communes', ['communes' => $communes]) ?>
    </section>
    <section aria-labelledby="relations-titre" data-apparition-defilement>
      <div class="admin__rubrique">
        <p class="t-etiquette t-secondaire">30 derniers jours · <?= e($nombre($relations['total'])) ?> mises en relation</p>
        <h2 class="t-titre-3" id="relations-titre">Mises en relation sur 30 jours</h2>
      </div>
      <?= composant('histogramme', ['jours' => $relations['jours'], 'resume' => '30 derniers jours : ' . $nombre($relations['total']) . ' mises en relation, dont ' . $relations['aujourdhui'] . ' aujourd’hui']) ?>
      <p class="admin__axe t-etiquette t-secondaire"><span><?= e(mb_strtoupper($jour($relations['depuis']), 'UTF-8')) ?></span><span>Aujourd’hui · <?= (int) $relations['aujourdhui'] ?> en cours</span></p>
    </section>
  </div>
</div>
