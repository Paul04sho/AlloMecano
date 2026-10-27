<?php
/**
 * A5 · Statistiques (Figma 180:631) : période et export, entonnoir de la recherche à l'avis, trois indicateurs, mention de confidentialité.
 * @var array<string, mixed> $stats   voir StatistiquesService::tout()
 */
use App\Core\Format;

$p = $stats['periode'];
$autre = $stats['autre'];
$sansAnnee = static fn (\DateTimeImmutable $d): string => trim((string) preg_replace('/\s\d{4}$/', '', Format::date($d)));
$du = $stats['du']->format('Y') === $stats['au']->format('Y') ? $sansAnnee($stats['du']) : Format::date($stats['du']);
$nb = static fn (int $n): string => Format::nombre($n);
$v = $stats['validation'];
$r = $stats['reproposition'];
$i = $stats['indisponibles'];
?>
<div class="stats">
  <header class="stats__entete">
    <div class="admin__entete">
      <p class="t-etiquette t-secondaire"><?= e('Statistiques · du ' . $du . ' au ' . Format::date($stats['au']) . ' · mis à jour à ' . Format::heure($stats['maj'])) ?></p>
      <h1 class="admin__titre">Statistiques</h1>
    </div>
    <div class="stats__outils">
      <?= composant('onglets-admin', ['nom' => 'Période', 'largeur' => 240, 'onglets' => [
          ['libelle' => '7 jours', 'href' => '/admin/statistiques?periode=7', 'actif' => $p === 7],
          ['libelle' => '30 jours', 'href' => '/admin/statistiques', 'actif' => $p === 30],
      ]]) ?>
      <a class="lien-icone" href="/admin/statistiques/export?periode=<?= (int) $p ?>"><?= icone('fichier', 20) ?><span class="t-gras">Exporter en CSV</span></a>
    </div>
  </header>

  <section class="stats__entonnoir" aria-labelledby="entonnoir-titre" data-apparition-defilement>
    <header class="stats__titre">
      <p class="t-etiquette t-secondaire"><?= e(Format::typo('Entonnoir · ' . $p . ' jours · valeurs sur ' . $autre . ' jours en dessous')) ?></p>
      <h2 class="t-titre-2" id="entonnoir-titre">De la recherche à l’avis</h2>
    </header>
    <table class="entonnoir">
      <thead>
        <tr class="t-etiquette t-secondaire">
          <th scope="col">Étape</th>
          <th scope="col">Volume</th>
          <th scope="col" class="droite"><?= (int) $p ?> j</th>
          <th scope="col">Conversion</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($stats['etapes'] as $etape): ?>
          <?= composant('ligne-entonnoir', ['etape' => $etape, 'periode' => $p, 'autre' => $autre]) ?>
        <?php endforeach ?>
      </tbody>
    </table>
  </section>

  <section class="stats__indicateurs" aria-label="Indicateurs" data-apparition-defilement>
    <?= composant('indicateur-stat', [
        'titre' => 'Délai moyen de validation', 'valeur' => $v['heures'] ?? '—', 'unite' => $v['heures'] !== null ? 'h' : null,
        'repere' => Format::typo('Objectif : moins de ' . $v['objectif'] . Format::INSECABLE . 'h' . ($v['mois_dernier'] !== null ? ' · ' . $v['mois_dernier'] . Format::INSECABLE . 'h le mois dernier' : '')),
        'explication' => 'Du dossier envoyé à la décision de l’équipe.' . ($v['plus_long'] !== null ? ' Le plus long ce mois-ci : ' . $v['plus_long'] . Format::INSECABLE . 'h.' : ' Aucune décision sur la période.'),
    ]) ?>
    <?= composant('indicateur-stat', [
        'titre' => 'Taux de reproposition', 'valeur' => $r['pct'] ?? '—', 'unite' => $r['pct'] !== null ? '%' : null,
        'repere' => $r['sans_reponse'] > 0 ? $nb($r['reproposes']) . ' client' . ($r['reproposes'] > 1 ? 's' : '') . ' sur ' . $nb($r['sans_reponse']) . ' sans réponse' : 'Aucun client sans réponse',
        'explication' => 'Clients qui n’ont pas pu joindre leur mécano et ont appelé celui qu’on leur a proposé ensuite.',
    ]) ?>
    <?= composant('indicateur-stat', [
        'titre' => 'Passés indisponibles automatiquement', 'valeur' => $i['mecanos'], 'unite' => $i['mecanos'] > 1 ? 'mécanos' : 'mécano',
        'repere' => 'Après ' . $i['seuil'] . ' appels sans réponse de suite',
        'explication' => $i['mecanos'] === 0 ? 'Aucun mécano n’est passé indisponible tout seul sur la période.'
            : ($i['revenus'] > 0 ? $i['revenus'] . ($i['revenus'] > 1 ? ' se sont remis disponibles' : ' s’est remis disponible') : 'Aucun ne s’est remis disponible') . ' dans la journée. '
              . ($i['revenus'] < $i['mecanos'] ? ($i['revenus'] > 0 ? 'Les autres ont' : 'Tous ont') . ' reçu un SMS de rappel.' : ''),
    ]) ?>
  </section>

  <p class="stats__confidentialite t-etiquette t-secondaire"><?= icone('cadenas', 20) ?><span>Comptages anonymes · aucun numéro ni coordonnée dans ces statistiques · conservés 90 jours</span></p>
</div>
