<?php
/**
 * Pochette de pièce (Figma 54:603) : vignette ou pictogramme, nom en Titre/3, état en Mono, actions « Prendre en photo » (capture=environment) et « Fichier ».
 * États : manquante · envoi (jauge en marquage) · envoyee (poids compressé, Remplacer) · a_reprendre (filet gauche erreur de 4 px et motif cité) · conforme (poinçon).
 * La pièce d'identité n'est jamais montrée en vignette : on y voit le spécimen. L'envoi reprend tout seul après une coupure (« Envoi repris »), côté JS.
 * @var string $type       cni_recto | cni_verso | permis | diplome | portrait | atelier
 * @var string $libelle
 * @var string $etat       manquante | envoi | envoyee | a_reprendre | conforme
 * @var ?int $taille       poids après recompression, en octets
 * @var ?string $motif     motif cité quand la pièce est à reprendre
 * @var string $action     adresse d'envoi sans JavaScript
 */
use App\Core\Format;

$icones = ['cni_recto' => 'cni', 'cni_verso' => 'cni', 'permis' => 'permis', 'diplome' => 'diplome', 'portrait' => 'portrait', 'atelier' => 'atelier'];
$ko = isset($taille) ? Format::nombre((int) max(1, round($taille / 1024))) . Format::INSECABLE . 'Ko' : '';
$etats = ['manquante' => 'Manquante', 'envoi' => 'Envoi', 'envoyee' => 'Envoyée · ' . $ko, 'a_reprendre' => 'À reprendre', 'conforme' => 'Conforme'];
$vignette = in_array($type, ['cni_recto', 'cni_verso'], true) && in_array($etat, ['envoyee', 'conforme', 'a_reprendre'], true);
$demandeEnvoi = in_array($etat, ['manquante', 'a_reprendre'], true);
?>
<li class="pochette pochette--<?= e(str_replace('_', '-', $etat)) ?>" data-pochette="<?= e($type) ?>" data-etat="<?= e($etat) ?>">
  <form class="pochette__corps" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-pochette-form>
    <?= csrf_champ() ?>
    <div class="pochette__entete">
      <span class="pochette__vignette" aria-hidden="true"><?= $vignette ? composant('specimen-cni', ['classe' => 'pochette__specimen']) : icone($icones[$type] ?? 'fichier', 24) ?></span>
      <div class="pochette__texte">
        <p class="t-titre-3"><?= e($libelle) ?></p>
        <p class="pochette__etat t-etiquette" data-pochette-etat aria-live="polite"><?php if ($etat === 'conforme'): ?><?= composant('poincon', ['taille' => 14]) ?><?php endif ?><span><?= e($etats[$etat]) ?></span></p>
      </div>
      <input class="pochette__champ" type="file" name="fichier_remplacer" id="piece-<?= e($type) ?>-remplacer" accept="image/jpeg,image/png,image/webp" data-pochette-champ>
      <label class="pochette__remplacer t-label" for="piece-<?= e($type) ?>-remplacer"<?= $etat === 'envoyee' ? '' : ' hidden' ?>>Remplacer</label>
    </div>
    <?php if ($etat === 'a_reprendre' && !empty($motif)): ?><p class="pochette__motif t-petit"><?= e(Format::typo($motif)) ?></p><?php endif ?>
    <div class="pochette__jauge" aria-hidden="true" hidden><?php for ($i = 0; $i < 10; $i++): ?><i></i><?php endfor ?></div>
    <div class="pochette__actions"<?= $demandeEnvoi ? '' : ' hidden' ?>>
      <input class="pochette__champ" type="file" name="fichier_photo" id="piece-<?= e($type) ?>-photo" accept="image/jpeg,image/png,image/webp" capture="environment" data-pochette-champ>
      <label class="bouton bouton--secondaire bouton--48" for="piece-<?= e($type) ?>-photo"><?= icone('camera') ?><span class="bouton__libelle">Prendre en photo</span></label>
      <input class="pochette__champ" type="file" name="fichier_fichier" id="piece-<?= e($type) ?>-fichier" accept="image/jpeg,image/png,image/webp" data-pochette-champ>
      <label class="bouton bouton--contour bouton--48" for="piece-<?= e($type) ?>-fichier"><?= icone('fichier') ?><span class="bouton__libelle">Fichier</span></label>
    </div>
    <noscript><button type="submit" class="bouton bouton--principal bouton--48"><span class="bouton__libelle">Envoyer cette pièce</span></button></noscript>
  </form>
</li>
