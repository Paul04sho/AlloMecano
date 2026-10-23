<?php
/**
 * C4b · Chargement (Figma 73:942) : radar sur la carte, squelettes statiques. Le script lance la recherche
 * (POST /api/recherches) et passe aux résultats ; sans JS, un bouton fait la même chose.
 * @var array{source: string, lat: float, lon: float, precision_m: ?int} $position
 * @var string $lieu
 * @var int $rayon
 * @var array<string, string> $resume
 */
use App\Core\Format;
?>
<div class="resultats resultats--chargement" data-recherche
     data-lat="<?= e((string) $position['lat']) ?>" data-lon="<?= e((string) $position['lon']) ?>" data-precision="<?= (int) ($position['precision_m'] ?? 0) ?>">
  <?= composant('barre-resultats', ['resume' => $resume, 'retour' => '/panne']) ?>

  <div class="resultats__carte">
    <?= composant('carte', ['lat' => $position['lat'], 'lon' => $position['lon'], 'zoom' => 14, 'libelle' => 'Carte : recherche des mécanos autour de vous.']) ?>
    <div class="radar" data-radar aria-hidden="true">
      <span class="radar__balayage"></span>
      <span class="radar__anneau radar__anneau--grand"></span>
      <span class="radar__anneau radar__anneau--petit"></span>
    </div>
  </div>
  <template data-vous><?= composant('vous-etes-ici', ['recherche' => true]) ?></template>

  <section class="feuille feuille--chargement" aria-labelledby="recherche-titre" aria-busy="true">
    <div class="panneau__interieur feuille__interieur">
      <span class="feuille__poignee seul-mobile" aria-hidden="true"></span>
      <div class="recherche__entete" aria-live="polite">
        <h1 class="t-titre-2 recherche__titre" id="recherche-titre"><?= e('On cherche autour de ' . $lieu . '…') ?><span class="recherche__voyant" aria-hidden="true"></span></h1>
        <p class="t-etiquette t-secondaire">Rayon <?= (int) $rayon ?><?= Format::INSECABLE ?>km</p>
      </div>
      <div class="recherche__squelettes">
        <?= composant('ligne-classement', ['format' => 'squelette', 'largeurs' => [150, 214]]) ?>
        <?= composant('ligne-classement', ['format' => 'squelette', 'largeurs' => [132, 190]]) ?>
        <?= composant('ligne-classement', ['format' => 'squelette', 'largeurs' => [140, 200], 'classe' => 'seul-desktop']) ?>
      </div>
      <noscript>
        <form method="post" action="/recherche"><?= csrf_champ() ?><?= composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'recherche', 'plein' => true, 'attrs' => ['type' => 'submit']]) ?></form>
      </noscript>
    </div>
  </section>
</div>
