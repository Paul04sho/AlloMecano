<?php
/**
 * A1 · File des dossiers (Figma 167:88) : onglets par statut, recherche, table triable, légende des pièces, parcours au clavier.
 * @var string $statut
 * @var string $recherche
 * @var string $ordre
 * @var array<string, int> $decompte
 * @var array{lignes: list<array<string, mixed>>, total: int, page: int, pages: int} $file
 */
use App\Core\Format;
use App\Services\DossierService;

$adresse = static fn (array $en_plus = []): string => '/admin/dossiers?' . http_build_query(array_filter(
    array_merge(['statut' => $statut === 'en_attente' ? null : $statut, 'q' => $recherche !== '' ? $recherche : null, 'ordre' => $ordre === 'desc' ? null : $ordre], $en_plus),
    static fn ($v) => $v !== null && $v !== ''
));
$debut = $file['total'] === 0 ? 0 : ($file['page'] - 1) * DossierService::PAR_PAGE + 1;
$fin = ($file['page'] - 1) * DossierService::PAR_PAGE + count($file['lignes']);
$vide = [
    'en_attente' => 'Aucun dossier en attente. La file est vide.', 'a_reprendre' => 'Aucun dossier à corriger.', 'valide' => 'Aucun dossier validé.',
    'refuse' => 'Aucun dossier refusé.', 'suspendu' => 'Aucun compte suspendu.',
];
?>
<header class="admin__entete">
  <p class="t-etiquette t-secondaire"><?= e(Format::typo('Dossiers · délai de traitement : ' . \App\Services\Parametres::int('dossier.delai_h', 48) . ' h · alerte au-delà de ' . \App\Services\Parametres::int('dossier.alerte_h', 36) . ' h')) ?></p>
  <h1 class="admin__titre">File des dossiers</h1>
</header>

<form class="dossiers__outils" method="get" action="/admin/dossiers" role="search">
  <?php if ($ordre !== 'desc'): ?><input type="hidden" name="ordre" value="<?= e($ordre) ?>"><?php endif ?>
  <nav class="dossiers__onglets" aria-label="Statut du dossier">
    <?php foreach (DossierService::ONGLETS as $code => $libelle): ?>
      <a href="<?= e('/admin/dossiers?' . http_build_query(array_filter(['statut' => $code === 'en_attente' ? null : $code, 'q' => $recherche !== '' ? $recherche : null, 'ordre' => $ordre === 'desc' ? null : $ordre]))) ?>"<?= $statut === $code ? ' aria-current="page"' : '' ?>>
        <span class="t-bouton-compact"><?= e($libelle) ?></span><?php if (in_array($code, ['en_attente', 'a_reprendre'], true) && $decompte[$code] > 0): ?><span class="t-donnee"><?= (int) $decompte[$code] ?></span><?php endif ?>
      </a>
    <?php endforeach ?>
  </nav>
  <?php if ($statut !== 'en_attente'): ?><input type="hidden" name="statut" value="<?= e($statut) ?>"><?php endif ?>
  <?= composant('champ', ['nom' => 'q', 'label' => 'Rechercher un dossier', 'valeur' => $recherche, 'exemple' => 'Nom, téléphone ou n° de dossier', 'autocomplete' => 'off']) ?>
</form>

<table class="dossiers__table">
  <caption class="sr-only">Dossiers <?= e(mb_strtolower(DossierService::ONGLETS[$statut], 'UTF-8')) ?></caption>
  <thead>
    <tr class="t-etiquette t-secondaire">
      <th scope="col"><span class="sr-only">Portrait</span></th>
      <th scope="col">Nom · n° de dossier</th>
      <th scope="col">Commune</th>
      <th scope="col">Spécialités</th>
      <th scope="col">Envoyé le</th>
      <th scope="col" aria-sort="<?= $ordre === 'desc' ? 'descending' : 'ascending' ?>"><a class="dossiers__tri" href="<?= e($adresse(['ordre' => $ordre === 'desc' ? 'asc' : null, 'page' => null])) ?>">Attente<?= icone('chevron', 16) ?></a></th>
      <th scope="col">Pièces</th>
      <th scope="col"><span class="sr-only">Action</span></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($file['lignes'] as $k => $d): ?><?= composant('ligne-dossier', ['dossier' => $d, 'mode' => 'table', 'principal' => $k === 0 && $statut === 'en_attente' && $file['page'] === 1 && $ordre === 'desc']) ?><?php endforeach ?>
  </tbody>
</table>
<?php if ($file['lignes'] === []): ?>
  <p class="admin__vide t-base t-secondaire"><?= e($recherche !== '' ? 'Aucun dossier ne correspond à « ' . $recherche . ' ».' : $vide[$statut]) ?></p>
<?php endif ?>

<footer class="dossiers__pied">
  <?= composant('pastilles-pieces', ['pieces' => [], 'legende' => true]) ?>
  <div class="dossiers__clavier t-etiquette t-secondaire">
    <span class="touche" aria-hidden="true"><?= icone('chevron', 16) ?></span><span class="touche" aria-hidden="true"><?= icone('chevron', 16) ?></span><span>Parcourir</span>
    <span class="touche" aria-hidden="true">Entrée</span><span>Ouvrir</span>
    <?php if ($file['total'] > 0): ?>
      <span class="dossiers__page"><?= (int) $fin ?> sur <?= (int) $file['total'] ?></span>
      <?php if ($file['page'] > 1): ?><a class="dossiers__pas dossiers__pas--prec" href="<?= e($adresse(['page' => $file['page'] - 1 > 1 ? $file['page'] - 1 : null])) ?>" aria-label="Page précédente"><?= icone('chevron', 20) ?></a><?php endif ?>
      <?php if ($file['page'] < $file['pages']): ?><a class="dossiers__pas" href="<?= e($adresse(['page' => $file['page'] + 1])) ?>" aria-label="Page suivante"><?= icone('chevron', 20) ?></a><?php endif ?>
    <?php endif ?>
  </div>
</footer>
