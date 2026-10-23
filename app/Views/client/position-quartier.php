<?php
/**
 * C2b · Commune, quartier, repère (Figma 68:604). Sans GPS, la position est le centre du quartier (RG-R1).
 * Toute la liste est dans la page : la recherche marche aussi sans connexion.
 * Sans JS : on choisit la commune, le serveur ouvre ses quartiers ; ?q= affiche les quartiers trouvés.
 * @var list<array{slug: string, nom: string}> $communes
 * @var list<array{slug: string, nom: string, alias: ?string, commune: string, commune_nom: string}> $quartiers
 * @var ?array{slug: string, nom: string} $commune
 * @var string $q
 * @var ?list<array<string, mixed>> $resultats
 * @var ?string $choix
 * @var ?string $repere
 * @var ?string $erreur
 */
use App\Core\Format;

$liste = array_map(fn ($x) => [$x['slug'], $x['nom'], (string) $x['alias'], $x['commune'], $x['commune_nom']], $quartiers);
$choisi = null;
foreach ($quartiers as $x) { if ($x['slug'] === $choix) { $choisi = $x; } }
$quartiersCommune = $commune !== null ? array_values(array_filter($quartiers, fn ($x) => $x['commune'] === $commune['slug'])) : [];
$libelleContinuer = 'Continuer' . ($choisi !== null ? Format::typo(' : ') . $choisi['nom'] : '');
?>
<form class="parcours-page position-quartier avec-socle" method="post" action="/position/quartier" data-quartier
      data-quartiers="<?= e(json_encode($liste, JSON_UNESCAPED_UNICODE)) ?>"
      data-communes="<?= e(json_encode(array_map(fn ($c) => [$c['slug'], $c['nom']], $communes), JSON_UNESCAPED_UNICODE)) ?>">
  <?= csrf_champ() ?>
  <?= composant('barre-parcours', ['etape' => 1, 'total' => 3, 'retour' => '/position', 'annuler' => '/']) ?>

  <div class="parcours-page__contenu position-quartier__contenu">
    <h1 class="t-titre-1">Dites-nous où vous êtes.</h1>

    <?= composant('champ', ['nom' => 'q', 'label' => 'Commune ou quartier', 'exemple' => 'ex. Riviera 2, Sideci, Zone 4', 'valeur' => $q,
        'aide' => 'La liste marche aussi sans connexion.', 'autocomplete' => 'off']) ?>

    <?php if ($erreur !== null): ?><?= composant('note', ['type' => 'erreur', 'message' => e(Format::typo($erreur))]) ?><?php endif ?>

    <div class="position-quartier__choix" data-zone-choix>
      <?php if ($resultats !== null): /* ?q= sans JS, ou saisie reprise de D0 */ ?>
        <h2 class="t-titre-2"><?= e(Format::typo('Quel quartier ?')) ?></h2>
        <div class="lignes-choix" role="radiogroup" aria-label="Quartiers trouvés" data-liste>
          <?php foreach ($resultats as $x): ?><?= composant('ligne-choix', ['nom' => 'quartier', 'valeur' => $x['slug'], 'libelle' => $x['nom'] . ', ' . $x['commune_nom'], 'coche' => $x['slug'] === $choix]) ?><?php endforeach ?>
          <?php if ($resultats === []): ?><p class="t-petit t-secondaire position-quartier__vide">Aucun quartier ne correspond. Essayez un autre nom, ou choisissez votre commune.</p><?php endif ?>
        </div>
      <?php elseif ($commune !== null): ?>
        <div class="position-quartier__fil">
          <span class="t-etiquette t-secondaire"><?= e($commune['nom']) ?> ›</span>
          <a class="lien" href="/position/quartier" data-changer-commune>Changer de commune</a>
        </div>
        <h2 class="t-titre-2"><?= e(Format::typo('Quel quartier ?')) ?></h2>
        <div class="lignes-choix" role="radiogroup" aria-label="Quartiers de <?= e($commune['nom']) ?>" data-liste>
          <?php foreach ($quartiersCommune as $x): ?><?= composant('ligne-choix', ['nom' => 'quartier', 'valeur' => $x['slug'], 'libelle' => $x['nom'], 'coche' => $x['slug'] === $choix]) ?><?php endforeach ?>
          <?= composant('ligne-choix', ['nom' => 'quartier', 'valeur' => 'commune:' . $commune['slug'], 'libelle' => Format::typo('Je ne sais pas : centre de la commune')]) ?>
        </div>
      <?php else: ?>
        <h2 class="t-titre-2"><?= e(Format::typo('Quelle commune ?')) ?></h2>
        <ul class="lignes-choix" role="list" data-liste>
          <?php foreach ($communes as $c): ?><li><a class="ligne-choix ligne-choix--lien" href="/position/quartier?commune=<?= e(rawurlencode($c['slug'])) ?>" data-commune="<?= e($c['slug']) ?>"><span><?= e($c['nom']) ?></span><?= icone('chevron', 20) ?></a></li><?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>

    <?= composant('champ', ['nom' => 'repere', 'label' => 'Un repère connu (facultatif)', 'exemple' => 'ex. près de la pharmacie des Jardins', 'valeur' => $repere, 'max' => 160,
        'aide' => 'Pharmacie, station, carrefour, église, école… À Abidjan, un repère vaut mieux qu’une adresse. On le donnera au mécano.', 'autocomplete' => 'off']) ?>

    <?= composant('recap', ['etiquette' => 'Ce qu’on donnera au mécano', 'texte' => $choisi !== null ? $choisi['nom'] . ($repere ? ' · ' . $repere : '') : '',
        'attrs' => ['data-recap' => '', 'hidden' => $choisi === null ? 'hidden' : null]]) ?>

    <?= composant('note', ['type' => 'info', 'message' => e(Format::typo('Sans GPS, les distances partent du centre du quartier : comptez quelques centaines de mètres d’écart.'))]) ?>

    <a class="bouton-lien position-quartier__gps js-seul" href="/position"><?= icone('viseur', 20) ?>Réessayer le GPS</a>
  </div>

  <?= composant('socle', [
      'pourquoi' => $choisi === null ? 'Choisissez votre quartier.' : null,
      'actions' => composant('bouton', ['libelle' => $libelleContinuer, 'icone' => 'chevron', 'plein' => true, 'attrs' => ['type' => 'submit', 'data-continuer' => '']]),
  ]) ?>
</form>
