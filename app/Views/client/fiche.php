<?php
/**
 * C5 · Fiche mécanicien (Figma 76:1211, sombre 185:1697) et C5 · Plus disponible (78:1304).
 * Identité vérifiée (RG-DO8), note « Nouveau » sous 3 avis (RG-AV6), numéro jamais dans la page (RG-A1).
 * @var array<string, mixed> $fiche
 * @var list<array<string, mixed>> $avis
 * @var int $totalAvis
 * @var array{sans: string, position: ?string} $message
 * @var ?array<string, mixed> $recherche
 * @var string $retour
 */
use App\Core\Format;

$nbsp = Format::INSECABLE;
$f = $fiche;
$dispo = (bool) $f['disponible'];
$puce = implode(' · ', array_filter([$f['distance_m'] !== null ? Format::distance($f['distance_m']) : null, $f['quartier']]));
$rechercheId = $recherche['public_id'] ?? null;
$bornes = [
    ['valeur' => $f['note'] !== null ? Format::note($f['note']) : 'Nouveau', 'libelle' => $f['nb_avis'] > 0 ? $f['nb_avis'] . ' avis' : 'Pas encore d’avis', 'compte' => $f['note']],
    ['valeur' => (string) $f['annees'], 'libelle' => 'ans de métier', 'compte' => (float) $f['annees']],
];
if ($f['tarif_diagnostic'] !== null) {
    $bornes[] = ['valeur' => str_replace(Format::FINE, $nbsp, Format::nombre($f['tarif_diagnostic'])), 'unite' => 'FCFA', 'libelle' => 'diagnostic dès', 'compte' => (float) $f['tarif_diagnostic']];
}
// « Cocody, Riviera et Bingerville » : la dernière commune s'enchaîne par « et »
$zone = $f['zone'];
$communes = count($zone) > 1 ? implode(', ', array_slice($zone, 0, -1)) . ' et ' . end($zone) : ($zone[0] ?? '');
$phraseZone = 'Intervient ' . ($communes !== '' ? 'à ' . $communes . ', ' : '') . 'jusqu’à ' . $f['rayon_km'] . $nbsp . 'km.';
$sections = fn (string $surtitre, string $titre, string $id) => '<header class="fiche__entete"><p class="t-etiquette t-secondaire">' . e($surtitre) . '</p><h2 class="t-titre-2" id="' . $id . '">' . e($titre) . '</h2></header>';
?>
<article class="fiche" data-fiche data-slug="<?= e($f['slug']) ?>" data-recherche="<?= e((string) $rechercheId) ?>" data-retour="<?= e($retour) ?>">
  <?= composant('portrait-fiche', ['photo' => $f['portrait'], 'nom' => $f['nom_complet'], 'mode' => 'plein', 'retour' => $retour, 'puce' => $puce]) ?>
  <?php if ($f['verifie_le'] !== null): ?><?= composant('bandeau-preuve', ['date' => $f['verifie_le']]) ?><?php endif ?>

  <div class="fiche__corps">
    <header class="fiche__identite">
      <h1 class="t-titre-1"><?= e($f['nom_complet']) ?></h1>
      <p class="t-base t-secondaire"><?= e($f['metier']) ?> · <?= $f['annees'] ?><?= $nbsp ?>ans de métier</p>
      <?= composant('statut', ['statut' => $dispo ? 'disponible' : 'plus_disponible']) ?>
      <?= composant('bornes', ['bornes' => $bornes]) ?>
    </header>

    <?php if ($dispo): ?>
      <?= composant('bloc-message', ['message' => $message, 'idInterrupteur' => 'position-fiche']) ?>
    <?php endif ?>

    <section class="fiche__section" aria-labelledby="zone-titre">
      <?= $sections('Zone et tarifs', $phraseZone, 'zone-titre') ?>
      <?php if ($dispo && ($f['tarif_diagnostic'] !== null || $f['tarif_deplacement'] !== null)): ?>
        <dl class="fiche__tarifs">
          <?php foreach ([['Diagnostic dès', $f['tarif_diagnostic']], ['Déplacement', $f['tarif_deplacement']]] as [$libelle, $montant]): if ($montant === null) { continue; } ?>
            <div><dt class="t-base"><?= e($libelle) ?></dt><dd><?= borne(str_replace(Format::FINE, $nbsp, Format::nombre($montant)), 'FCFA', 'l') ?></dd></div>
          <?php endforeach ?>
        </dl>
        <p class="t-petit t-secondaire"><?= e(Format::typo('Indicatif. Le prix final se discute avec lui, avant l’intervention.')) ?></p>
      <?php endif ?>
    </section>

    <?php if ($dispo && $f['specialites'] !== []): ?>
      <section class="fiche__section" aria-labelledby="specialites-titre">
        <?= $sections('Spécialités', 'Ce qu’il répare', 'specialites-titre') ?>
        <ul class="etiquettes-choix" role="list">
          <?php foreach ($f['specialites'] as $s): ?><li class="etiquette-choix etiquette-choix--fixe"><span><?= e($s) ?></span></li><?php endforeach ?>
        </ul>
      </section>
    <?php endif ?>

    <?php if ($dispo && $f['atelier'] !== null): ?>
      <section class="fiche__section" aria-labelledby="atelier-titre">
        <?= $sections('Photo fournie par ' . $f['prenom'], 'Son atelier', 'atelier-titre') ?>
        <div class="fiche__atelier-bloc">
        <figure class="fiche__atelier photo photo--legendee">
          <img src="<?= e($f['atelier']['src']) ?>" srcset="<?= e($f['atelier']['srcset']) ?>" sizes="(min-width: 560px) 528px, calc(100vw - 32px)" width="780" height="523"
               alt="L’atelier de <?= e($f['nom_complet']) ?><?= $f['atelier_quartier'] ? ', ' . e($f['atelier_quartier']) : '' ?>" loading="lazy" decoding="async"
               style="background:<?= e($f['atelier']['couleur']) ?>;object-position:<?= e($f['atelier']['cadrage']) ?>">
          <?= composant('legende-repere', ['texte' => 'Son atelier' . ($f['atelier_quartier'] ? ' · ' . $f['atelier_quartier'] : '')]) ?>
        </figure>
        <?php if (!empty($f['atelier_adresse'])): ?><p class="t-petit t-secondaire"><?= e($f['atelier_adresse']) ?>.</p><?php endif ?>
        </div>
      </section>
    <?php endif ?>

    <?php if ($dispo && $totalAvis > 0): ?>
      <section class="fiche__section" aria-labelledby="avis-titre" data-avis data-decalage="<?= count($avis) ?>" data-reste="<?= max(0, $totalAvis - count($avis)) ?>">
        <?= $sections('Ce qu’en disent les clients', $f['note'] !== null ? 'Noté ' . Format::note($f['note']) . ' sur 5 par ' . $f['nb_avis'] . ' clients' : 'Nouveau', 'avis-titre') ?>
        <div class="fiche__avis" data-liste-avis aria-live="polite">
          <?php foreach ($avis as $a): ?><?= composant('avis', $a + ['prenomMecano' => $f['prenom']]) ?><?php endforeach ?>
        </div>
        <?php if ($totalAvis > count($avis)): ?>
          <a class="lien t-gras" href="?avis=tous<?= $rechercheId ? '&amp;r=' . e($rechercheId) : '' ?>" data-plus-avis>Lire les <?= $totalAvis ?><?= $nbsp ?>avis</a>
        <?php endif ?>
      </section>
    <?php endif ?>

    <?php if ($dispo): ?>
      <p class="fiche__signaler"><a class="lien lien--erreur t-gras" href="/signaler/<?= e($f['slug']) ?>"><?= icone('drapeau', 20) ?>Signaler ce mécanicien</a></p>
    <?php endif ?>
  </div>

  <?php if ($dispo): ?>
    <?= composant('socle', [
        'info' => 'Appel direct · le prix se discute avec lui',
        'actions' => composant('formulaire-contact', ['mecano' => $f['public_id'], 'recherche' => $rechercheId, 'canal' => 'app',
                'contenu' => composant('bouton', ['libelle' => 'Appeler ' . $f['prenom'], 'icone' => 'telephone', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']])])
            . composant('formulaire-contact', ['mecano' => $f['public_id'], 'recherche' => $rechercheId, 'canal' => 'whatsapp',
                'contenu' => composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'contour-beton', 'hauteur' => 56, 'attrs' => ['type' => 'submit']])]),
    ]) ?>
  <?php else: ?>
    <?= composant('socle', [
        'info' => $f['prenom'] . ' n’est plus disponible',
        'actions' => composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'liste', 'hauteur' => 56, 'plein' => true, 'href' => $retour === '/' ? '/position' : $retour]),
    ]) ?>
  <?php endif ?>
</article>
