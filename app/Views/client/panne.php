<?php
/**
 * C3 · Ma panne (Figma 69:653) · Choc ou accident (69:868) : la note Danger monte au-dessus de tout.
 * @var array<string, string> $vehicules
 * @var list<array{code: string, libelle: string, danger: bool}> $pannes
 * @var string $vehicule
 * @var ?string $panne
 * @var ?string $precisions
 * @var ?string $prenom
 * @var ?string $telephone
 * @var array<string, string> $erreurs
 * @var string $retour
 */
use App\Core\Format;
use App\Services\PanneService;
use App\Services\Parametres;

$samu = Parametres::texte('urgence.samu', '185');
$danger = array_column(array_filter($pannes, fn ($p) => $p['danger']), 'code');
$exemples = array_map(fn ($c) => PanneService::exemple($c), array_combine(array_column($pannes, 'code'), array_column($pannes, 'code')));
?>
<form class="parcours-page panne avec-socle" method="post" action="/panne" data-panne novalidate
      data-exemples="<?= e(json_encode($exemples, JSON_UNESCAPED_UNICODE)) ?>">
  <?= csrf_champ() ?>
  <?php // Entrée dans un champ envoie le formulaire, jamais « Effacer » (premier bouton d'envoi = bouton par défaut) ?>
  <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true"></button>
  <?= composant('barre-parcours', ['etape' => 2, 'total' => 3, 'retour' => $retour, 'annuler' => '/']) ?>

  <div class="parcours-page__contenu panne__contenu">
    <div class="panne__danger" data-note-danger<?= in_array($panne, $danger, true) ? '' : ' hidden' ?>>
      <?= composant('note', ['type' => 'danger',
          'message' => e(Format::typo('Quelqu’un est blessé ? Appelez le ' . $samu . ' d’abord.')),
          'actions' => composant('bouton', ['libelle' => 'Appeler le ' . $samu, 'icone' => 'telephone', 'type' => 'danger', 'hauteur' => 48, 'href' => 'tel:' . $samu])]) ?>
    </div>

    <h1 class="t-titre-1"><?= e(Format::typo('Qu’est-ce qui ne va pas ?')) ?></h1>

    <?= composant('choix-vehicule', ['nom' => 'vehicule', 'vehicules' => $vehicules, 'valeur' => $vehicule, 'legende' => 'Votre véhicule']) ?>

    <?= composant('panneau-voyants', ['nom' => 'panne', 'pannes' => $pannes, 'valeur' => $panne, 'erreur' => $erreurs['panne'] ?? null]) ?>

    <?= composant('champ', ['nom' => 'precisions', 'label' => 'Précisez si vous voulez (facultatif)', 'type' => 'textarea', 'lignes' => 2,
        'max' => PanneService::PRECISIONS_MAX, 'compteur' => false, 'exemple' => PanneService::exemple($panne), 'valeur' => $precisions, 'erreur' => $erreurs['precisions'] ?? null]) ?>

    <section class="panne__reconnaitre" aria-labelledby="panne-reconnaitre">
      <div class="rubrique">
        <p class="t-etiquette">Facultatif</p>
        <h2 class="t-titre-2" id="panne-reconnaitre">Pour que le mécano vous reconnaisse</h2>
      </div>
      <p class="t-petit t-secondaire">Il voit votre prénom avec votre appel et peut vous rappeler si ça coupe. Votre numéro n’est jamais affiché publiquement.</p>
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'valeur' => $prenom, 'autocomplete' => 'given-name', 'max' => PanneService::PRENOM_MAX, 'erreur' => $erreurs['prenom'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'valeur' => $telephone !== null && str_starts_with($telephone, '+225') ? implode(' ', str_split(substr($telephone, 4), 2)) : $telephone, 'erreur' => $erreurs['telephone'] ?? null]) ?>
      <div class="panne__memoire">
        <p class="t-petit t-secondaire">Gardés sur ce téléphone seulement.</p>
        <button type="submit" class="bouton-lien panne__effacer" formaction="/panne/effacer" formnovalidate data-effacer>Effacer</button>
      </div>
    </section>
  </div>

  <?= composant('socle', [
      'pourquoi' => 'Choisissez ce qui ne va pas.',
      'actions' => composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'recherche', 'plein' => true, 'chargement' => 'Recherche…', 'attrs' => ['type' => 'submit', 'data-voir' => '']]),
  ]) ?>
</form>
