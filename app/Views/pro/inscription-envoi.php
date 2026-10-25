<?php
/**
 * M1d · Inscription 4/4 · Relire et envoyer (Figma 104:607) : le récapitulatif de chaque étape, l'aperçu de la ligne que les clients verront, trois accords jamais pré-cochés (RG-DO3).
 * « Envoyer mon dossier » reste éteint avec la phrase « Cochez les 3 cases pour envoyer » tant que les trois cases ne sont pas cochées.
 * @var array<string, mixed> $b
 * @var array{etats: array<string, string>, courantes: array<string, array<string, mixed>>} $etat
 * @var array<string, string> $specialites  code → libellé
 * @var list<string> $manques
 * @var ?string $portrait
 */
use App\Core\Format;

$nbsp = Format::INSECABLE;
$choisies = array_values(array_filter(array_map(static fn (string $c): ?string => $specialites[$c] ?? null, $b['specialites'])));
$vehicules = ['voiture' => 'Voiture', 'moto' => 'Moto', 'les_deux' => 'Voiture et moto'][$b['vehicules'] ?? 'voiture'] ?? 'Voiture';
$annees = $b['annees'] === 0 ? 'Moins d’un an d’expérience' : $b['annees'] . $nbsp . ($b['annees'] > 1 ? 'ans' : 'an') . ' d’expérience';
$montant = static fn (int $n): string => Format::nombre($n);
$tarifs = [];
if ($b['tarif_diagnostic'] !== null && $b['tarif_diagnostic'] > 0) {
    $tarifs[] = ['libelle' => 'Diagnostic, à partir de', 'valeur' => $montant($b['tarif_diagnostic'])];
}
if ($b['deplacement_inclus']) {
    $tarifs[] = ['libelle' => 'Déplacement', 'valeur' => 'Inclus', 'unite' => ''];
} elseif ($b['tarif_deplacement'] !== null && $b['tarif_deplacement'] > 0) {
    $tarifs[] = ['libelle' => 'Déplacement', 'valeur' => $montant($b['tarif_deplacement'])];
}
$atelier = ($etat['courantes']['atelier'] ?? null) !== null;
?>
<div class="pro inscription avec-socle" data-inscription-envoi>
  <?= composant('barre-parcours', ['etape' => 4, 'total' => 4, 'retour' => '/pro/inscription/pieces', 'annuler' => '/', 'annulerLibelle' => 'Plus tard']) ?>
  <?= composant('parcours-brouillon', ['heure' => Format::heure($b['modifie_le']), 'pieces' => $etat['etats']]) ?>

  <form class="inscription__formulaire inscription__formulaire--large" method="post" action="/pro/inscription/envoi" novalidate data-envoi>
    <?= csrf_champ() ?>
    <div class="inscription__titre">
      <h1 class="t-titre-1">Relisez, puis envoyez.</h1>
      <p class="t-petit t-secondaire">Un dernier coup d’œil avant l’envoi à l’équipe de vérification.</p>
    </div>

    <div class="recap-envoi">
      <?= composant('bloc-recap', ['rubrique' => 'Compte', 'href' => '/pro/inscription/compte?retour=envoi', 'lignes' => array_values(array_filter([
          ['style' => 'titre', 'texte' => $b['prenom'] . ' ' . $b['nom']],
          ['style' => 'donnee', 'texte' => Format::telephone($b['telephone'])],
          $b['email'] !== '' ? ['style' => 'petit', 'texte' => $b['email']] : null,
      ]))]) ?>
      <?= composant('bloc-recap', ['rubrique' => 'Métier', 'href' => '/pro/inscription/metier', 'lignes' => array_values(array_filter([
          ['style' => 'titre', 'texte' => implode(' · ', $choisies)],
          ['style' => 'petit', 'texte' => $vehicules . ' · ' . $annees],
          $b['presentation'] !== '' ? ['style' => 'petit', 'texte' => '«' . $nbsp . $b['presentation'] . $nbsp . '»'] : null,
      ]))]) ?>
      <?= composant('bloc-recap', ['rubrique' => 'Zone et tarifs', 'href' => '/pro/inscription/metier#zone', 'lignes' => [
          ['style' => 'titre', 'texte' => $b['quartier_libelle'] . ' · jusqu’à ' . $b['rayon'] . $nbsp . 'km'],
      ], 'tarifs' => $tarifs]) ?>
      <?= composant('bloc-recap', ['rubrique' => 'Pièces', 'href' => '/pro/inscription/pieces', 'lignes' => [
          ['style' => 'titre', 'texte' => '5 / 5 pièces obligatoires envoyées'],
          ['style' => 'petit', 'texte' => 'CNI recto et verso, permis de conduire, diplôme ou attestation, photo de vous. ' . ($atelier ? 'Atelier : envoyé.' : 'Atelier : non envoyé (facultatif).')],
      ]]) ?>
    </div>

    <section class="envoi__apercu" id="apercu" aria-labelledby="apercu-titre">
      <h2 class="t-titre-2" id="apercu-titre">Voici comment les clients vous verront</h2>
      <p class="envoi__attente"><?= composant('etiquette-verifie', ['etat' => 'attente']) ?><span class="t-petit t-secondaire">« Vérifié » la remplacera après validation.</span></p>
      <?= composant('ligne-classement', [
          'nom' => $b['prenom'] . ' ' . $b['nom'], 'portrait' => $portrait ?? asset('img/accueil/specimen-portrait-160.webp'), 'specialites' => $choisies, 'nouveau' => true,
          'quartier' => $b['quartier']['commune_nom'] ?? '', 'rang' => 1, 'distanceM' => 800, 'verifie' => false,
      ]) ?>
    </section>

    <section class="envoi__accords" aria-labelledby="accords-titre">
      <h2 class="t-titre-2" id="accords-titre">Vos accords</h2>
      <div class="envoi__cases">
        <?= composant('ligne-choix', ['nom' => 'accord_cgu', 'valeur' => '1', 'type' => 'checkbox', 'libelle' => 'J’accepte les', 'lien' => ['libelle' => 'CGU', 'href' => '/cgu'], 'attrs' => ['data-accord' => '']]) ?>
        <?= composant('ligne-choix', ['nom' => 'accord_verification', 'valeur' => '1', 'type' => 'checkbox', 'libelle' => 'J’autorise allomécano à vérifier mes pièces', 'lien' => ['libelle' => 'Ce que nous vérifions', 'href' => '/a-propos#verification', 'bloc' => true], 'attrs' => ['data-accord' => '']]) ?>
        <?= composant('ligne-choix', ['nom' => 'accord_visibilite', 'valeur' => '1', 'type' => 'checkbox', 'libelle' => 'J’accepte que ma photo, mon prénom, ma note et ma zone soient visibles par les clients', 'lien' => ['libelle' => 'Voir ce que les clients voient', 'href' => '#apercu', 'bloc' => true], 'attrs' => ['data-accord' => '']]) ?>
      </div>
    </section>

    <?= composant('socle', [
        'info' => 'Cochez les 3 cases pour envoyer',
        'actions' => composant('bouton', ['libelle' => 'Envoyer mon dossier', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Envoi…', 'attrs' => ['type' => 'submit', 'data-envoyer' => '', 'class' => 'bouton--eteint']]),
    ]) ?>
  </form>
</div>
