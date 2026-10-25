<?php
/**
 * M1b · Inscription 2/4 · Votre métier (Figma 97:218) : spécialités, véhicules, expérience, zone d'intervention et rayon, tarifs indicatifs,
 * phrase de présentation, et l'aperçu de la ligne que les clients verront. Chaque champ s'enregistre dès qu'il change (RG-DO1).
 * @var array<string, mixed> $b             le brouillon (InscriptionService::brouillon)
 * @var list<array{code: string, libelle: string}> $specialites
 * @var list<array<string, mixed>> $quartiers
 * @var list<int> $rayons
 * @var array<string, string> $etats        états des pièces, pour l'écrou de 24
 * @var ?string $portrait                   la photo du mécano, à lui seul
 * @var array{lat: float, lon: float} $centre
 */
use App\Core\Format;
use App\Services\InscriptionService;

$montant = static fn (?int $n): string => $n !== null && $n > 0 ? Format::nombre($n) : '';
$libelles = array_column($specialites, 'libelle', 'code');
$choisies = array_values(array_filter(array_map(static fn (string $c): ?string => $libelles[$c] ?? null, $b['specialites'])));
?>
<div class="pro inscription avec-socle" data-inscription-metier data-quartiers="<?= e(json_encode(array_map(static fn (array $q): array => [InscriptionService::libelleQuartier($q), $q['lat'], $q['lon']], $quartiers), JSON_UNESCAPED_UNICODE)) ?>" data-centre="<?= e($centre['lat'] . ',' . $centre['lon']) ?>">
  <?= composant('barre-parcours', ['etape' => 2, 'total' => 4, 'retour' => '/pro/inscription/compte', 'annuler' => '/', 'annulerLibelle' => 'Plus tard']) ?>
  <?= composant('parcours-brouillon', ['heure' => Format::heure($b['modifie_le']), 'pieces' => $etats]) ?>

  <form class="inscription__formulaire" method="post" action="/pro/inscription/metier" novalidate data-brouillon-form>
    <?= csrf_champ() ?>
    <div class="inscription__titre">
      <h1 class="t-titre-1">Votre métier</h1>
      <p class="t-petit t-secondaire">Ce que vous réparez, où, et à quel prix indicatif.</p>
    </div>

    <section class="inscription__groupe inscription__groupe--premier" aria-labelledby="specialites-titre">
      <div class="inscription__entete"><h2 class="t-titre-3" id="specialites-titre">Spécialités</h2><p class="t-petit t-secondaire">Choisissez tout ce que vous faites. Les clients les lisent sous votre nom.</p></div>
      <div class="etiquettes-choix" role="group" aria-labelledby="specialites-titre">
        <?php foreach ($specialites as $s): ?>
          <?= composant('etiquette-choix', ['nom' => 'specialites[]', 'valeur' => $s['code'], 'libelle' => $s['libelle'], 'coche' => in_array($s['code'], $b['specialites'], true), 'attrs' => ['data-libelle' => $s['libelle']]]) ?>
        <?php endforeach ?>
      </div>
    </section>

    <section class="inscription__groupe" aria-labelledby="vehicules-titre">
      <div class="inscription__entete"><h2 class="t-titre-3" id="vehicules-titre">Véhicules</h2><p class="t-petit t-secondaire">Ceux que vous réparez.</p></div>
      <?= composant('segment', ['nom' => 'vehicules', 'options' => ['voiture' => 'Voiture', 'moto' => 'Moto', 'les_deux' => 'Les deux'], 'valeur' => $b['vehicules'] ?? 'voiture', 'decritPar' => 'vehicules-titre']) ?>
    </section>

    <section class="inscription__groupe" aria-labelledby="experience-titre">
      <div class="inscription__entete"><h2 class="t-titre-3" id="experience-titre">Expérience</h2><p class="t-petit t-secondaire">Depuis combien d’années vous réparez.</p></div>
      <?= composant('stepper', ['nom' => 'annees', 'valeur' => $b['annees'], 'min' => 0, 'max' => InscriptionService::ANNEES_MAX, 'unite' => 'ans', 'libelle' => 'Années d’expérience']) ?>
    </section>

    <section class="inscription__groupe" id="zone" aria-labelledby="zone-titre">
      <div class="inscription__entete"><h2 class="t-titre-3" id="zone-titre">Zone d’intervention</h2><p class="t-petit t-secondaire">Les clients voient votre commune, jamais votre adresse exacte.</p></div>
      <?= composant('champ', ['nom' => 'quartier', 'label' => 'Commune et quartier de l’atelier', 'valeur' => $b['quartier_libelle'], 'liste' => 'quartiers-liste', 'autocomplete' => 'off']) ?>
      <datalist id="quartiers-liste"><?php foreach ($quartiers as $q): ?><option value="<?= e(InscriptionService::libelleQuartier($q)) ?>"><?php endforeach ?></datalist>
      <?= composant('curseur-rayon', ['nom' => 'rayon', 'valeur' => $b['rayon'], 'rayons' => $rayons]) ?>
      <div class="zone__mini-carte">
        <?= composant('carte', ['lat' => $centre['lat'], 'lon' => $centre['lon'], 'zoom' => 12, 'libelle' => 'Carte de votre zone d’intervention', 'classe' => 'zone__carte']) ?>
        <template data-repere><?= composant('ecrou-repere', ['type' => 'carte', 'voyant' => false]) ?></template>
        <p class="t-micro t-secondaire zone__attribution">© contributeurs OpenStreetMap</p>
      </div>
    </section>

    <section class="inscription__groupe" aria-labelledby="tarifs-titre">
      <div class="inscription__entete"><h2 class="t-titre-3" id="tarifs-titre">Tarifs indicatifs</h2><p class="t-petit t-secondaire">Le prix final se discute avec le client : ces chiffres rassurent, ils n’engagent pas.</p></div>
      <?= composant('champ', ['nom' => 'tarif_diagnostic', 'label' => 'Diagnostic, à partir de', 'valeur' => $montant($b['tarif_diagnostic']), 'suffixe' => 'FCFA', 'inputmode' => 'numeric', 'autocomplete' => 'off']) ?>
      <?= composant('champ', ['nom' => 'tarif_deplacement', 'label' => 'Déplacement', 'valeur' => $b['deplacement_inclus'] ? '' : $montant($b['tarif_deplacement']), 'suffixe' => 'FCFA', 'inputmode' => 'numeric', 'autocomplete' => 'off', 'desactive' => $b['deplacement_inclus']]) ?>
      <?= composant('ligne-choix', ['nom' => 'deplacement_inclus', 'valeur' => '1', 'libelle' => 'Déplacement inclus dans le diagnostic', 'type' => 'checkbox', 'coche' => $b['deplacement_inclus']]) ?>
    </section>

    <section class="inscription__groupe" aria-labelledby="presentation-titre">
      <div class="inscription__entete"><h2 class="t-titre-3" id="presentation-titre">Phrase de présentation</h2><p class="t-petit t-secondaire">En une phrase, ce que vous faites de mieux.</p></div>
      <?= composant('champ', ['nom' => 'presentation', 'label' => 'Phrase de présentation', 'labelCache' => true, 'type' => 'textarea', 'valeur' => $b['presentation'], 'max' => InscriptionService::PRESENTATION_MAX, 'lignes' => 3]) ?>
    </section>

    <section class="inscription__groupe" aria-labelledby="apercu-titre">
      <div class="inscription__apercu-entete"><h2 class="t-titre-3" id="apercu-titre">Aperçu de votre ligne</h2><?= composant('etiquette-verifie', ['etat' => 'attente']) ?></div>
      <p class="t-petit t-secondaire">Elle se construit pendant que vous remplissez. Les clients la verront une fois votre dossier validé.</p>
      <div data-apercu-ligne>
        <?= composant('ligne-classement', [
            'nom' => $b['prenom'] . ' ' . $b['nom'], 'portrait' => $portrait ?? asset('img/accueil/specimen-portrait-160.webp'), 'specialites' => $choisies === [] ? ['Vos spécialités'] : $choisies, 'nouveau' => true,
            'quartier' => $b['quartier']['commune_nom'] ?? 'Votre commune', 'rang' => 1, 'distanceM' => 800, 'verifie' => false,
        ]) ?>
      </div>
    </section>

    <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'Continuer', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Enregistrement…', 'attrs' => ['type' => 'submit']])]) ?>
  </form>
</div>
