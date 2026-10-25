<?php
/**
 * A2 · Examen d'un dossier (Figma 168:166) : en-tête, visionneuses (photo de profil · pièce d'identité), pièce par pièce, pièces à reprendre avec
 * l'aperçu exact du message, journal d'audit ; à droite la décision, collante (RG-DO4 à RG-DO6).
 * @var array<string, mixed> $dossier
 * @var array{rang: int, total: int, suivant: ?array{public_id: string, nom: string}} $position
 * @var list<array{le: \DateTimeImmutable, qui: string, texte: string}> $journal
 * @var array{piece: array<string, string>, motif: array<string, string>, precision: array<string, string>} $saisie
 */
use App\Core\Format;
use App\Services\DossierService;
use App\Services\ExamenDossierService;

$d = $dossier;
$id = rawurlencode($d['public_id']);
$enAttente = $d['statut'] === 'en_attente';
$etatsPieces = [];
foreach (ExamenDossierService::PIECES as $t) {
    $p = $d['pieces'][$t];
    $etatsPieces[$t] = $p === null ? ($t === 'atelier' ? 'facultative' : 'manquante') : ($p['statut'] === 'a_reprendre' ? 'a_reprendre' : 'envoyee');
}
$jour = static fn (\DateTimeImmutable $x): string => trim((string) preg_replace('/\s\d{4}$/', '', Format::date($x)));
$court = static fn (\DateTimeImmutable $x): string => $x->format('d/m') . ' · ' . $x->format('H:i');
$sujet = ['en_attente' => 'En attente', 'a_reprendre' => 'À corriger', 'valide' => 'Validés', 'refuse' => 'Refusés', 'suspendu' => 'Suspendus'][$d['statut']] ?? '';
$lien = '/admin/dossiers' . ($d['statut'] === 'en_attente' ? '' : '?statut=' . $d['statut']);
$conformes = count(array_filter($saisie['piece'], static fn (string $v, string $t): bool => $v === 'conforme' && in_array($t, ExamenDossierService::OBLIGATOIRES, true), ARRAY_FILTER_USE_BOTH));
$aReprendre = array_keys(array_filter($saisie['piece'], static fn (string $v): bool => $v === 'a_reprendre'));
$specs = implode(', ', array_map(static fn (string $s, int $i): string => mb_strtoupper($s, 'UTF-8'), $d['specialites'], array_keys($d['specialites'])));
$consult = static fn (string $t): string => isset($d['pieces'][$t]['id'], $d['consultees'][$d['pieces'][$t]['id']]) ? 'Consultée · ' . Format::heure($d['consultees'][$d['pieces'][$t]['id']]) : 'Non consultée';
?>
<header class="dossier__entete">
  <div class="dossier__fil t-etiquette t-secondaire">
    <p><a href="/admin/dossiers">Dossiers</a> / <a href="<?= e($lien) ?>"><?= e($sujet) ?></a> / <?= e($d['numero']) ?></p>
    <?php if ($position['rang'] > 0): ?><p>Dossier <?= (int) $position['rang'] ?> sur <?= (int) $position['total'] ?><?php if ($position['suivant'] !== null): ?> · <a href="/admin/dossiers/<?= e(rawurlencode($position['suivant']['public_id'])) ?>">Suivant : <?= e($position['suivant']['nom']) ?></a><?php endif ?></p><?php endif ?>
  </div>
  <div class="dossier__nom">
    <?= composant('ecrou-dossier', ['pieces' => $etatsPieces, 'valide' => $d['statut'] === 'valide', 'taille' => 24]) ?>
    <h1 class="admin__titre"><?= e($d['nom_complet']) ?></h1>
    <?= composant('statut', ['statut' => $d['statut']]) ?>
  </div>
  <p class="dossier__resume t-donnee t-secondaire"><?= e(mb_strtoupper(implode(' · ', array_filter([$d['numero'], $d['commune'], mb_strtolower($specs, 'UTF-8') !== '' ? $specs : null,
      $d['envoye_le'] !== null ? 'envoyé le ' . $jour($d['envoye_le']) . ' à ' . Format::heure($d['envoye_le']) : null, $enAttente ? 'attente ' . $d['jauge']['heures'] . ' h / ' . $d['jauge']['delai'] : null])), 'UTF-8')) ?></p>
</header>

<form class="dossier__corps" id="decision" method="post" action="/admin/dossiers/<?= e($id) ?>/decision" data-examen data-apercu="/admin/dossiers/<?= e($id) ?>/apercu" data-prenom="<?= e($d['prenom']) ?>" novalidate>
  <?= csrf_champ() ?>
  <div class="dossier__comparaison">
    <div class="dossier__visionneuses">
      <?= composant('visionneuse', ['nom' => 'portrait', 'titre' => 'PHOTO DE PROFIL', 'complement' => $d['pieces']['portrait'] !== null && $d['envoye_le'] !== null ? 'REÇUE LE ' . $d['envoye_le']->format('d/m') . ' · ' . Format::heure($d['envoye_le']) : null,
          'src' => '/admin/dossiers/' . $id . '/pieces/portrait', 'alt' => 'Photo de profil de ' . $d['nom_complet']]) ?>
      <?= composant('visionneuse', ['nom' => 'piece', 'titre' => 'PIÈCE D’IDENTITÉ · RECTO', 'src' => '/admin/dossiers/' . $id . '/pieces/cni_recto', 'alt' => 'Pièce d’identité de ' . $d['nom_complet'],
          'segment' => '<span class="segment segment--plein segment--32 visionneuse__segment" role="radiogroup" aria-label="Face de la pièce d’identité"><label><input type="radio" name="face" value="cni_recto" checked data-face><span>Recto</span></label><label><input type="radio" name="face" value="cni_verso" data-face><span>Verso</span></label></span>']) ?>
    </div>

    <section class="dossier__pieces" aria-labelledby="pieces-titre">
      <div class="admin__rubrique">
        <p class="t-etiquette t-secondaire">Les 6 pièces · 5 obligatoires + atelier facultatif</p>
        <h2 class="t-titre-3" id="pieces-titre">Pièce par pièce</h2>
      </div>
      <ul class="dossier__lignes" role="list">
        <?php foreach (ExamenDossierService::PIECES as $t): ?>
          <?= composant('ligne-piece', ['type' => $t, 'piece' => $d['pieces'][$t], 'mecano' => $d['public_id'], 'decision' => $saisie['piece'][$t] ?? null, 'affichee' => $t === 'cni_recto' || $t === 'portrait',
              'etat' => $t === 'cni_recto' ? 'Affichée' : ($t === 'portrait' ? 'Comparée' : $consult($t)), 'desactive' => !$enAttente]) ?>
        <?php endforeach ?>
      </ul>
    </section>

    <section class="reprises" data-reprises<?= $aReprendre === [] ? ' hidden' : '' ?> aria-label="Pièces à reprendre">
      <?php foreach (ExamenDossierService::PIECES as $i => $t): if ($d['pieces'][$t] === null) { continue; } ?>
        <div class="reprise" data-reprise="<?= e($t) ?>"<?= ($saisie['piece'][$t] ?? '') === 'a_reprendre' ? '' : ' hidden' ?>>
          <p class="reprise__entete t-etiquette"><?= icone('triangle', 20) ?><span>À reprendre · <?= e(mb_strtoupper(ExamenDossierService::NOMS[$t][3], 'UTF-8')) ?></span><span class="t-secondaire">Pièce <?= $i + 1 ?> sur 6</span></p>
          <fieldset class="reprise__motifs">
            <legend class="t-label">Motif</legend>
            <div class="reprise__chips">
              <?php foreach (ExamenDossierService::MOTIFS as $code => $libelle): ?>
                <label class="etiquette-choix etiquette-choix--40"><input type="radio" name="motif[<?= e($t) ?>]" value="<?= e($code) ?>"<?= ($saisie['motif'][$t] ?? '') === $code ? ' checked' : '' ?><?= !$enAttente ? ' disabled' : '' ?>><span><?= icone('coche', 18) ?><?= e($libelle) ?></span></label>
              <?php endforeach ?>
            </div>
          </fieldset>
          <?= composant('champ', ['nom' => 'precision[' . $t . ']', 'label' => 'Précision pour ' . $d['prenom'] . ' (facultatif)', 'valeur' => (string) ($saisie['precision'][$t] ?? ''), 'aide' => 'Elle s’ajoute au message, telle quelle.', 'max' => 300, 'desactive' => !$enAttente]) ?>
        </div>
      <?php endforeach ?>
      <div class="reprise__apercu">
        <p class="t-etiquette t-secondaire">Aperçu exact du message · SMS et espace mécano</p>
        <p class="reprise__message t-base" data-apercu-texte aria-live="polite"><?= e(ExamenDossierService::message($d['prenom'], array_map(static fn (string $t): array => ['motif' => $saisie['motif'][$t] ?? 'photo_floue', 'precision' => ''], array_combine($aReprendre, $aReprendre) ?: []))) ?></p>
      </div>
    </section>

    <section class="dossier__journal" aria-labelledby="journal-titre">
      <div class="admin__rubrique">
        <p class="t-etiquette t-secondaire">Journal d’audit · aucun téléchargement · chaque consultation est enregistrée</p>
        <h2 class="t-titre-3" id="journal-titre">Ce qui s’est passé sur ce dossier</h2>
      </div>
      <ol class="journal" role="list" data-journal>
        <?php foreach ($journal as $l): ?>
          <li><time class="t-donnee t-secondaire" datetime="<?= e($l['le']->format('c')) ?>"><?= e($court($l['le'])) ?></time><span class="t-label"><?= e($l['qui']) ?></span><span class="t-petit"><?= e($l['texte']) ?></span></li>
        <?php endforeach ?>
      </ol>
    </section>
  </div>

  <aside class="decision" aria-labelledby="decision-titre">
    <div class="admin__rubrique">
      <p class="t-etiquette t-secondaire">Décision</p>
      <h2 class="t-titre-2" id="decision-titre" data-decision-titre><?= $conformes ?> pièce<?= $conformes > 1 ? 's' : '' ?> conforme<?= $conformes > 1 ? 's' : '' ?> sur 5</h2>
    </div>
    <table class="decision__concordances">
      <thead><tr class="t-etiquette t-secondaire"><th scope="col">Concordance</th><th scope="col">Déclaré</th><th scope="col">Sur la pièce</th></tr></thead>
      <tbody>
        <?php foreach ([['nom', 'Nom', $d['declare']['nom'], mb_strtoupper($d['declare']['nom'], 'UTF-8')], ['prenom', 'Prénom', $d['declare']['prenom'], mb_strtoupper($d['declare']['prenom'], 'UTF-8')],
            ['ne_le', 'Né le', $d['declare']['ne_le']?->format('d/m/Y') ?? '—', $d['declare']['ne_le']?->format('d.m.Y') ?? '—']] as [$cle, $libelle, $declare, $piece]): ?>
          <tr>
            <th scope="row"><label class="ligne-choix ligne-choix--48"><input type="checkbox" name="concordance[<?= e($cle) ?>]" value="1" data-concordance<?= !$enAttente ? ' disabled' : '' ?>><span><?= e($libelle) ?></span></label></th>
            <td class="<?= $cle === 'ne_le' ? 't-donnee' : 't-petit' ?>"><?= e($declare) ?></td>
            <td class="t-donnee"><?= e($piece) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <label class="ligne-choix"><input type="checkbox" name="visage" value="1" data-visage<?= !$enAttente ? ' disabled' : '' ?>><span>Le visage correspond à la pièce</span></label>

    <div class="decision__actions">
      <?php if ($enAttente): ?>
        <p class="decision__info t-petit t-secondaire" data-decision-info><?= icone('info', 20) ?><span>Validation impossible : <?= $aReprendre === [] ? 'des pièces restent à examiner' : 'le permis est à reprendre' ?>. Elle se débloque quand les 5 pièces sont conformes.</span></p>
        <button type="submit" class="bouton bouton--contour-faible bouton--44 bouton--plein" name="action" value="valider" data-valider aria-disabled="true"><?= icone('cadenas') ?><span class="bouton__libelle">Valider le dossier</span></button>
        <button type="submit" class="bouton bouton--secondaire bouton--44 bouton--plein" name="action" value="renvoyer" data-renvoyer<?= $aReprendre === [] ? ' hidden' : '' ?>><span class="bouton__libelle" data-renvoyer-libelle>Renvoyer pour correction (<?= count($aReprendre) ?> pièce<?= count($aReprendre) > 1 ? 's' : '' ?>)</span></button>
        <div class="decision__refus">
          <button type="button" class="lien-icone lien--erreur t-gras" data-refuser><?= icone('fermer', 20) ?><span>Refuser définitivement</span></button>
          <p class="t-micro t-secondaire">Une confirmation avec motif vous sera demandée.</p>
        </div>
      <?php else: ?>
        <p class="decision__info t-petit t-secondaire"><?= icone('info', 20) ?><span>Ce dossier n’est plus en attente (<?= e(mb_strtolower($sujet, 'UTF-8')) ?>) : il ne peut plus être décidé ici.<?= $d['motif_refus'] ? ' Motif du refus : ' . e($d['motif_refus']) : '' ?></span></p>
      <?php endif ?>
    </div>

    <div class="decision__raccourcis">
      <p class="t-etiquette t-secondaire">Raccourcis clavier</p>
      <ul role="list">
        <?php foreach ([['J / K', 'Pièce'], ['C', 'Conforme'], ['R', 'À reprendre'], ['Z', 'Zoom'], ['V', 'Valider']] as [$touche, $libelle]): ?>
          <li><span class="touche t-etiquette"><?= e($touche) ?></span><span class="t-etiquette t-secondaire"><?= e($libelle) ?></span></li>
        <?php endforeach ?>
      </ul>
    </div>
  </aside>

  <dialog class="refus" data-refus aria-labelledby="refus-titre">
    <h2 class="t-titre-3" id="refus-titre">Refuser définitivement le dossier de <?= e($d['nom_complet']) ?> ?</h2>
    <p class="t-petit t-secondaire">Le refus est définitif et le mécano en est informé avec ce motif. Ses pièces sont effacées au bout de 6 mois.</p>
    <?= composant('champ', ['nom' => 'motif_refus', 'label' => 'Motif du refus', 'type' => 'textarea', 'max' => 500, 'lignes' => 3]) ?>
    <div class="refus__actions">
      <button type="button" class="bouton bouton--contour bouton--44" data-refus-annuler><span class="bouton__libelle">Annuler</span></button>
      <button type="submit" class="bouton bouton--danger-confirme bouton--44" name="action" value="refuser"><span class="bouton__libelle">Refuser définitivement</span></button>
    </div>
  </dialog>
</form>
