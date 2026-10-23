<?php
/**
 * C2a · Confirmation de la position (Figma 67:462) : la carte glisse sous l'écrou fixe, le client ajuste.
 * @var array{lat: float, lon: float, precision_m: ?int} $gps
 * @var ?array{slug: string, nom: string, commune: string, commune_nom: string} $quartier
 * @var string $coordonnees
 * @var bool $imprecis      RG-R1 : plus de 300 m de précision, on invite à ajuster
 * @var ?string $repere
 * @var list<array<string, mixed>> $quartiers
 */
use App\Core\Format;

$nom = $quartier !== null ? $quartier['nom'] . ', ' . $quartier['commune_nom'] : 'Votre position';
$zoom = match (true) {
    ($gps['precision_m'] ?? 0) <= 50 => 18,
    $gps['precision_m'] <= 150 => 16,
    $gps['precision_m'] <= 300 => 15,
    $gps['precision_m'] <= 900 => 14,
    default => 13,
};
$donnees = array_map(fn ($q) => [$q['nom'] . ', ' . $q['commune_nom'], $q['lat'], $q['lon'], $q['commune']], $quartiers);
?>
<form class="confirmer" method="post" action="/position/confirmer" data-confirmer
      data-precision="<?= (int) ($gps['precision_m'] ?? 0) ?>" data-quartiers="<?= e(json_encode($donnees, JSON_UNESCAPED_UNICODE)) ?>">
  <?= csrf_champ() ?>
  <input type="hidden" name="lat" value="<?= e((string) $gps['lat']) ?>">
  <input type="hidden" name="lon" value="<?= e((string) $gps['lon']) ?>">
  <input type="hidden" name="precision" value="<?= e((string) ($gps['precision_m'] ?? '')) ?>">
  <input type="hidden" name="deplace" value="0">

  <?= composant('barre-parcours', ['etape' => 1, 'total' => 3, 'retour' => '/position', 'annuler' => '/']) ?>

  <div class="confirmer__carte">
    <?= composant('carte', ['lat' => $gps['lat'], 'lon' => $gps['lon'], 'zoom' => $zoom,
        'libelle' => 'Carte de votre position. Déplacez-la pour placer le repère exactement où vous êtes.']) ?>
    <div class="confirmer__repere" data-repere>
      <?= composant('bulle', ['texte' => $imprecis ? 'Précision faible : déplacez la carte pour ajuster' : 'Déplacez la carte pour ajuster', 'classe' => 'confirmer__bulle', 'attrs' => ['data-bulle' => '']]) ?>
      <?= composant('ecrou-repere', ['type' => 'carte', 'classe' => 'confirmer__ecrou']) ?>
    </div>
  </div>

  <?= composant('panneau', ['classe' => 'confirmer__panneau', 'etiquette' => 'Position trouvée', 'contenu' =>
      '<h1 class="t-titre-2 confirmer__nom" data-nom aria-live="polite">' . e($nom) . '</h1>'
      . '<p class="t-donnee t-secondaire confirmer__coordonnees" data-coordonnees>' . e(mb_strtoupper($coordonnees)) . '</p>'
      . composant('champ', ['nom' => 'repere', 'label' => 'Un repère pour le mécano (facultatif)', 'exemple' => 'ex. devant la pharmacie des Jardins', 'valeur' => $repere, 'max' => 160, 'autocomplete' => 'off'])
      . '<a class="lien confirmer__autre" href="/position/quartier' . ($quartier !== null ? '?commune=' . e(rawurlencode($quartier['commune'])) : '') . '" data-autre-quartier>' . e(Format::typo('Ce n’est pas mon quartier ?')) . '</a>',
  ]) ?>

  <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'C’est ici', 'icone' => 'coche', 'plein' => true, 'attrs' => ['type' => 'submit']])]) ?>
</form>
