<?php
/**
 * C4 · Résultats (Figma 60:196, sombre 185:1669), C4c · Aucun mécano disponible (75:1070), D1 · desktop (156:274).
 * Liste figée (RG-R7) : la ligne étendue est la plus proche, ou celle choisie sur la carte (une seule à la fois).
 * @var array<string, mixed> $recherche
 * @var list<array<string, mixed>> $resultats
 * @var array<string, string> $resume
 * @var array<string, string> $vehicules
 * @var list<array<string, mixed>> $pannes
 * @var int $rayonEtendu
 * @var list<int> $rayons
 * @var ?string $panneau  panneau de la fiche ouverte (D1, ?fiche=slug)
 * @var ?string $ouvert   slug de la fiche ouverte
 */
use App\Core\Format;
use App\Services\Parametres;

$nb = count($resultats);
$heure = Format::heure($recherche['cree_le']);
$nbsp = Format::INSECABLE;
$fine = Format::FINE;
$peutElargir = (int) $recherche['rayon_km'] < $rayonEtendu;
$mecanos = array_map(fn ($m) => [
    'rang' => $m['rang'], 'prenom' => $m['prenom'], 'lat' => $m['lat'], 'lon' => $m['lon'], 'distance' => Format::km($m['distance_m']),
], $resultats);
// Formulaires de contact (RG-A1 : chaque appui crée un contact AVANT d'ouvrir l'appel) : branchés en partie 6
$contact = function (array $m, string $canal, string $contenu) use ($recherche): string {
    return '<form method="post" action="/contact">' . csrf_champ()
        . '<input type="hidden" name="mecano" value="' . e($m['public_id']) . '"><input type="hidden" name="recherche" value="' . e($recherche['public_id']) . '">'
        . '<input type="hidden" name="canal" value="' . e($canal) . '">' . $contenu . '</form>';
};
?>
<div class="resultats<?= $nb === 0 ? ' resultats--aucun' : '' ?><?= !empty($panneau) ? ' resultats--fiche' : '' ?>" data-resultats data-recherche="<?= e($recherche['public_id']) ?>"
     data-lat="<?= e((string) $recherche['lat']) ?>" data-lon="<?= e((string) $recherche['lon']) ?>"
     data-precision="<?= (int) ($recherche['precision_m'] ?? 0) ?>" data-rayon="<?= (int) $recherche['rayon_km'] ?>"
     data-mecanos="<?= e(json_encode($mecanos, JSON_UNESCAPED_UNICODE)) ?>">
  <?= composant('barre-resultats', ['resume' => $resume, 'vehicules' => $vehicules, 'pannes' => $pannes, 'retour' => '/panne']) ?>

  <div class="resultats__carte">
    <?= composant('carte', ['lat' => $recherche['lat'], 'lon' => $recherche['lon'], 'zoom' => 14, 'libelle' => $nb > 0 ? 'Carte des mécanos disponibles autour de vous.' : 'Carte : zone cherchée autour de vous.']) ?>
    <div class="carte-controles seul-desktop" data-controles>
      <div class="carte-controles__zoom">
        <button type="button" data-zoom="1" aria-label="Zoomer"><?= icone('plus') ?></button>
        <button type="button" data-zoom="-1" aria-label="Dézoomer"><?= icone('moins') ?></button>
      </div>
      <button type="button" data-recentrer aria-label="Recentrer sur ma position"><?= icone('viseur') ?></button>
    </div>
    <?= $panneau ?? '' ?>
  </div>
  <template data-vous><?= composant('vous-etes-ici') ?></template>
  <?php foreach ($resultats as $m): ?>
    <template data-repere="<?= $m['rang'] ?>"><?= composant('repere-carte', ['type' => 'numerote', 'rang' => $m['rang']]) ?></template>
    <template data-repere-choisi="<?= $m['rang'] ?>"><?= composant('repere-carte', ['type' => 'selectionne', 'visage' => $m['portrait']['src'] ?? null, 'etiquette' => $m['rang'] . ' · ' . Format::distance($m['distance_m'])]) ?></template>
  <?php endforeach ?>

  <section class="feuille" data-feuille aria-labelledby="resultats-titre">
    <div class="panneau__interieur feuille__interieur" data-feuille-contenu>
      <button type="button" class="feuille__poignee seul-mobile" data-poignee aria-label="Afficher plus ou moins de la liste"></button>

      <?php if ($nb > 0): ?>
        <p class="t-etiquette t-secondaire"><?= e($heure) ?> · Vérifiés<span class="seul-desktop"> · Disponibles</span> · À vol d’oiseau</p>
        <div class="resultats__titre">
          <h1 class="t-titre-1" id="resultats-titre" aria-live="polite"><?= $nb ?> mécano<?= $nb > 1 ? 's' : '' ?> disponible<?= $nb > 1 ? 's' : '' ?></h1>
          <div class="segment resultats__vue seul-mobile" role="group" aria-label="Affichage">
            <button type="button" aria-pressed="true" data-vue="carte">Carte</button><button type="button" aria-pressed="false" data-vue="liste">Liste</button>
          </div>
        </div>
        <div class="resultats__filtres seul-desktop" role="group" aria-label="Filtres">
          <label class="etiquette-choix etiquette-choix--40"><input type="checkbox" data-filtre="specialiste"><span>Spécialité</span></label>
          <label class="etiquette-choix etiquette-choix--40"><input type="checkbox" data-filtre="note"><span>Note 4 et plus</span></label>
          <form class="resultats__rayon" method="post" action="/recherche/rayon">
            <?= csrf_champ() ?>
            <label class="etiquette-choix etiquette-choix--40 etiquette-choix--menu"><span class="sr-only">Rayon de recherche</span>
              <select name="rayon" data-rayon><?php foreach ($rayons as $r): ?><option value="<?= $r ?>"<?= $r === (int) $recherche['rayon_km'] ? ' selected' : '' ?>>Rayon <?= $r ?><?= $nbsp ?>km</option><?php endforeach ?></select>
            </label>
            <noscript><button type="submit" class="bouton-lien">OK</button></noscript>
          </form>
        </div>

        <ol class="resultats__liste" role="list" data-liste>
          <?php foreach ($resultats as $i => $m):
              $nom = $m['prenom'] . ' ' . $m['nom'];
              $actions = $contact($m, 'app',
                    composant('bouton', ['libelle' => 'Appeler ' . $m['prenom'], 'icone' => 'telephone', 'plein' => true, 'attrs' => ['type' => 'submit', 'class' => 'seul-mobile']])
                  . composant('bouton', ['libelle' => 'Afficher le numéro', 'icone' => 'telephone', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit', 'name' => 'canal', 'value' => 'tel', 'class' => 'seul-desktop']]))
                . $contact($m, 'whatsapp', composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire', 'hauteur' => 56, 'attrs' => ['type' => 'submit']]));
              $commun = ['nom' => $nom, 'portrait' => $m['portrait'] ?? ['src' => ''], 'specialites' => $m['specialites'], 'specialitePanne' => $m['specialites_panne'],
                  'note' => $m['note'], 'avis' => $m['avis'], 'quartier' => $m['quartier'], 'rang' => $m['rang'], 'distanceM' => $m['distance_m'],
                  'autreRive' => $m['autre_rive'], 'href' => '/mecano/' . $m['slug'] . '?r=' . $recherche['public_id'], 'attrs' => ['data-fiche' => $m['slug']]]; ?>
            <li class="resultats__mecano<?= $i === 0 ? ' choisi' : '' ?><?= ($ouvert ?? null) === $m['slug'] ? ' ouvert' : '' ?>" data-rang="<?= $m['rang'] ?>" data-specialiste="<?= $m['specialiste'] ? '1' : '0' ?>" data-note="<?= e((string) ($m['note'] ?? 0)) ?>">
              <?= composant('ligne-classement', $commun + ['format' => 'etendue', 'rubrique' => $i === 0 ? 'Le plus proche' : 'Choisi sur la carte', 'actions' => $actions, 'classe' => 'resultats__etendue']) ?>
              <?= composant('ligne-classement', $commun + ['classe' => 'resultats__compacte']) ?>
            </li>
          <?php endforeach ?>
        </ol>
        <p class="resultats__vide t-petit t-secondaire" data-filtre-vide hidden>Aucun mécano ne correspond à ces filtres.</p>

        <?php if ($peutElargir): ?>
          <form class="resultats__elargir" method="post" action="/recherche/rayon">
            <?= csrf_champ() ?><input type="hidden" name="rayon" value="<?= $rayonEtendu ?>">
            <span class="t-petit t-secondaire seul-desktop"><?= e(Format::typo('Aucun ne vous convient ?')) ?></span>
            <button type="submit" class="lien resultats__elargir-lien"><span class="seul-mobile"><?= e(Format::typo('Aucun ne vous convient ?')) ?> </span><?= e('Chercher jusqu’à ' . $rayonEtendu) . $nbsp ?>km</button>
          </form>
        <?php endif ?>

      <?php else: /* C4c · aucun mécano disponible */ ?>
        <div class="aucun">
          <p class="t-etiquette t-secondaire"><?= e($heure) ?> · Rayon <?= (int) $recherche['rayon_km'] . $nbsp ?>km</p>
          <h1 class="t-titre-1" id="resultats-titre">Personne n’est disponible près de vous pour l’instant.</h1>
          <p class="t-base"><?= e(Format::typo('Les mécanos se rendent disponibles tout au long de la journée. Voici ce qu’on peut faire :')) ?></p>
          <div class="aucun__actions">
            <?php if ($peutElargir): ?>
              <form method="post" action="/recherche/rayon"><?= csrf_champ() ?><input type="hidden" name="rayon" value="<?= $rayonEtendu ?>">
                <?= composant('bouton', ['libelle' => 'Chercher jusqu’à ' . $rayonEtendu . $nbsp . 'km', 'icone' => 'recherche', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']]) ?></form>
            <?php endif ?>
            <?= composant('bouton', ['libelle' => 'Changer de commune ou de panne', 'icone' => 'crayon', 'type' => $peutElargir ? 'contour' : 'principal', 'hauteur' => 56, 'plein' => true, 'href' => '/panne']) ?>
          </div>
          <?php
          $police = Parametres::texte('urgence.police', '170');
          $pompiers = Parametres::texte('urgence.pompiers', '180');
          $numeros = '<span class="aucun__numeros"><a href="tel:' . e($police) . '"><span class="aucun__numero">' . e($police) . '</span><span class="t-etiquette">Police</span></a>'
              . '<a href="tel:' . e($pompiers) . '"><span class="aucun__numero">' . e($pompiers) . '</span><span class="t-etiquette">Pompiers</span></a></span>'; ?>
          <?= composant('note', ['type' => 'securite', 'message' => e(Format::typo('En danger sur la route ? Appelez d’abord les secours.')) . $numeros]) ?>
        </div>
      <?php endif ?>
    </div>
  </section>
</div>
