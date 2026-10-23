<?php
/**
 * D1 · Panneau fiche 420 (Figma 158:542) : glisse depuis la droite sur la carte, la liste reste à gauche.
 * Avant la création du contact (RG-A1) le numéro n'est pas dans la page : « Afficher le numéro » le crée et le révèle (partie 6).
 * @var array<string, mixed> $fiche
 * @var list<array<string, mixed>> $avis
 * @var int $totalAvis
 * @var array{sans: string, position: ?string} $message
 * @var ?array<string, mixed> $recherche
 */
use App\Core\Format;

$nbsp = Format::INSECABLE;
$f = $fiche;
$dispo = (bool) $f['disponible'];
$rechercheId = $recherche['public_id'] ?? null;
$bornes = [
    ['valeur' => $f['note'] !== null ? Format::note($f['note']) : 'Nouveau', 'libelle' => $f['nb_avis'] > 0 ? $f['nb_avis'] . ' avis' : 'Pas encore d’avis', 'compte' => $f['note']],
    ['valeur' => (string) $f['annees'], 'libelle' => 'ans de métier', 'compte' => (float) $f['annees']],
];
if ($f['tarif_diagnostic'] !== null) {
    $bornes[] = ['valeur' => str_replace(Format::FINE, $nbsp, Format::nombre($f['tarif_diagnostic'])), 'unite' => 'FCFA', 'libelle' => 'diagnostic dès', 'compte' => (float) $f['tarif_diagnostic']];
}
$lieu = implode(' · ', array_filter([$f['distance_m'] !== null ? Format::distance($f['distance_m']) . ' à vol d’oiseau' : null, $f['quartier']]));
?>
<aside class="fiche-panneau" data-panneau data-slug="<?= e($f['slug']) ?>" role="dialog" aria-modal="false" aria-labelledby="panneau-nom">
  <div class="fiche-panneau__contenu">
    <?= composant('portrait-fiche', ['photo' => $f['portrait'], 'nom' => $f['nom_complet'], 'mode' => 'panneau',
        'puce' => implode(' · ', array_filter([$f['distance_m'] !== null ? Format::distance($f['distance_m']) : null, $f['quartier']]))]) ?>
    <?php if ($f['verifie_le'] !== null): ?><?= composant('bandeau-preuve', ['date' => $f['verifie_le']]) ?><?php endif ?>

    <div class="fiche-panneau__identite">
      <div class="fiche-panneau__nom">
        <h2 class="t-titre-2" id="panneau-nom" tabindex="-1"><?= e($f['nom_complet']) ?></h2>
        <?= composant('statut', ['statut' => $dispo ? 'disponible' : 'plus_disponible']) ?>
      </div>
      <p class="t-base"><?= e($f['metier']) ?> · <?= $f['annees'] ?><?= $nbsp ?>ans de métier</p>
      <?php if ($lieu !== ''): ?><p class="t-etiquette t-secondaire"><?= e($lieu) ?></p><?php endif ?>
    </div>

    <?= composant('bornes', ['bornes' => $bornes, 'unitesAParts' => true]) ?>

    <?php if ($dispo): ?>
      <?= composant('bloc-message', ['message' => $message, 'idInterrupteur' => 'position-panneau']) ?>

      <?php if ($totalAvis > 0): ?>
        <section class="fiche-panneau__avis" aria-labelledby="panneau-avis" data-avis data-decalage="<?= count($avis) ?>" data-reste="<?= max(0, $totalAvis - count($avis)) ?>">
          <h3 class="t-etiquette t-secondaire" id="panneau-avis">Ce qu’en disent les clients</h3>
          <div data-liste-avis aria-live="polite"><?php foreach ($avis as $a): ?><?= composant('avis', $a + ['prenomMecano' => $f['prenom']]) ?><?php endforeach ?></div>
          <?php if ($totalAvis > count($avis)): ?><a class="lien t-gras" href="/mecano/<?= e($f['slug']) ?>?avis=tous<?= $rechercheId ? '&amp;r=' . e($rechercheId) : '' ?>" data-plus-avis>Lire les <?= $totalAvis ?><?= $nbsp ?>avis</a><?php endif ?>
        </section>
      <?php endif ?>
      <p class="fiche__signaler"><a class="lien lien--erreur t-gras" href="/signaler/<?= e($f['slug']) ?>"><?= icone('drapeau', 18) ?>Signaler ce mécanicien</a></p>
    <?php endif ?>
  </div>

  <div class="fiche-panneau__contact">
    <?php if ($dispo): ?>
      <div class="fiche-panneau__actions" data-contact>
        <?= composant('formulaire-contact', ['mecano' => $f['public_id'], 'recherche' => $rechercheId, 'canal' => 'tel',
            'contenu' => composant('bouton', ['libelle' => 'Afficher le numéro', 'icone' => 'telephone', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']])]) ?>
        <?= composant('formulaire-contact', ['mecano' => $f['public_id'], 'recherche' => $rechercheId, 'canal' => 'whatsapp',
            'contenu' => composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire', 'hauteur' => 56, 'attrs' => ['type' => 'submit']])]) ?>
      </div>
    <?php else: ?>
      <p class="t-etiquette t-secondaire"><?= e($f['prenom']) ?> n’est plus disponible</p>
      <?= composant('bouton', ['libelle' => 'Voir les mécanos disponibles', 'icone' => 'liste', 'hauteur' => 56, 'plein' => true, 'attrs' => ['data-fermer-fiche' => '']]) ?>
    <?php endif ?>
  </div>
</aside>
