<?php
/**
 * M4 · Modifier une section de « Mon profil » (le crayon d'une section ; pas dessiné dans Figma : mêmes champs et mêmes textes que l'inscription M1b).
 * Photo et identité (RG-DO7 : nom ou portrait changé, le dossier repasse en vérification), spécialités, zone et rayon, tarifs, présentation, photos de l'atelier, téléphone et WhatsApp, mot de passe.
 * @var string $section
 * @var string $libelle
 * @var array<string, mixed> $f        la fiche
 * @var array<string, mixed> $b        les valeurs enregistrées (InscriptionService::brouillon)
 * @var array<string, mixed> $saisie   ce qui vient d'être envoyé, quand le formulaire revient avec des erreurs
 * @var array<string, string> $erreurs
 * @var list<array{code: string, libelle: string}> $specialites
 * @var list<array<string, mixed>> $quartiers
 * @var list<int> $rayons
 */
use App\Core\Format;
use App\Services\InscriptionService;
use App\Services\ProfilMecanoService;

$valeur = static fn (string $cle, mixed $defaut) => array_key_exists($cle, $saisie) ? $saisie[$cle] : $defaut;
$montant = static fn (?int $n): string => $n !== null && $n > 0 ? Format::nombre($n) : '';
$multipart = in_array($section, ['identite', 'atelier'], true);
?>
<div class="profil-modifier">
  <header class="activer__barre">
    <a class="activer__retour" href="/pro/profil" aria-label="Retour à mon profil"><?= icone('retour', 24) ?></a>
    <p class="t-etiquette t-secondaire">Mon profil · <?= e($libelle) ?></p>
  </header>

  <form class="profil-modifier__formulaire" method="post" action="/pro/profil/<?= e($section) ?>"<?= $multipart ? ' enctype="multipart/form-data"' : '' ?> novalidate>
    <?= csrf_champ() ?>
    <h1 class="t-titre-1"><?= e($libelle) ?></h1>
    <?php if ($erreurs !== []): ?><?= composant('note', ['type' => 'erreur', 'message' => e((string) reset($erreurs))]) ?><?php endif ?>

    <?php if ($section === 'identite'): ?>
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'valeur' => (string) $valeur('prenom', $f['prenom']), 'erreur' => $erreurs['prenom'] ?? null, 'autocomplete' => 'given-name']) ?>
      <?= composant('champ', ['nom' => 'nom', 'label' => 'Nom', 'valeur' => (string) $valeur('nom', $f['nom']), 'erreur' => $erreurs['nom'] ?? null, 'autocomplete' => 'family-name']) ?>
      <div class="champ<?= isset($erreurs['portrait']) ? ' champ--erreur' : '' ?>">
        <label class="champ__label" for="portrait">Nouvelle photo de vous (facultatif)</label>
        <input class="profil-modifier__fichier" type="file" id="portrait" name="portrait" accept="image/jpeg,image/png,image/webp" capture="user">
        <?php if (isset($erreurs['portrait'])): ?><p class="champ__message"><?= icone('triangle') ?><span><?= e($erreurs['portrait']) ?></span></p><?php endif ?>
        <p class="champ__aide">De face, en plein jour, sans lunettes de soleil.</p>
      </div>
      <?= composant('note', ['type' => 'securite', 'message' => 'Changer de photo relance la vérification&nbsp;: vous perdez le badge pendant 48&nbsp;h au plus. Changer de nom aussi.']) ?>

    <?php elseif ($section === 'specialites'): ?>
      <section class="inscription__groupe inscription__groupe--premier" aria-labelledby="specialites-titre">
        <div class="inscription__entete"><h2 class="t-titre-3" id="specialites-titre">Spécialités</h2><p class="t-petit t-secondaire">Choisissez tout ce que vous faites. Les clients les lisent sous votre nom.</p></div>
        <?php $coches = array_key_exists('specialites', $saisie) ? (array) $saisie['specialites'] : $b['specialites']; ?>
        <div class="etiquettes-choix" role="group" aria-labelledby="specialites-titre">
          <?php foreach ($specialites as $s): ?><?= composant('etiquette-choix', ['nom' => 'specialites[]', 'valeur' => $s['code'], 'libelle' => $s['libelle'], 'coche' => in_array($s['code'], $coches, true)]) ?><?php endforeach ?>
        </div>
        <?php if (isset($erreurs['specialites'])): ?><p class="champ__message"><?= icone('triangle') ?><span><?= e($erreurs['specialites']) ?></span></p><?php endif ?>
      </section>
      <section class="inscription__groupe" aria-labelledby="vehicules-titre">
        <div class="inscription__entete"><h2 class="t-titre-3" id="vehicules-titre">Véhicules</h2><p class="t-petit t-secondaire">Ceux que vous réparez.</p></div>
        <?= composant('segment', ['nom' => 'vehicules', 'options' => ['voiture' => 'Voiture', 'moto' => 'Moto', 'les_deux' => 'Les deux'], 'valeur' => (string) $valeur('vehicules', $b['vehicules'] ?? 'voiture'), 'decritPar' => 'vehicules-titre']) ?>
      </section>
      <section class="inscription__groupe" aria-labelledby="experience-titre">
        <div class="inscription__entete"><h2 class="t-titre-3" id="experience-titre">Expérience</h2><p class="t-petit t-secondaire">Depuis combien d’années vous réparez.</p></div>
        <?= composant('stepper', ['nom' => 'annees', 'valeur' => (int) $valeur('annees', $b['annees']), 'min' => 0, 'max' => InscriptionService::ANNEES_MAX, 'unite' => 'ans', 'libelle' => 'Années d’expérience']) ?>
      </section>

    <?php elseif ($section === 'zone'): ?>
      <p class="t-petit t-secondaire">Les clients voient votre commune, jamais votre adresse exacte.</p>
      <?= composant('champ', ['nom' => 'quartier', 'label' => 'Commune et quartier de l’atelier', 'valeur' => (string) $valeur('quartier', $b['quartier_libelle']), 'liste' => 'quartiers-liste', 'autocomplete' => 'off', 'erreur' => $erreurs['quartier'] ?? null]) ?>
      <datalist id="quartiers-liste"><?php foreach ($quartiers as $q): ?><option value="<?= e(InscriptionService::libelleQuartier($q)) ?>"><?php endforeach ?></datalist>
      <?= composant('curseur-rayon', ['nom' => 'rayon', 'valeur' => (int) $valeur('rayon', $b['rayon']), 'rayons' => $rayons]) ?>

    <?php elseif ($section === 'tarifs'): ?>
      <p class="t-petit t-secondaire">Le prix final se discute avec le client : ces chiffres rassurent, ils n’engagent pas.</p>
      <?php $inclus = array_key_exists('tarif_diagnostic', $saisie) ? isset($saisie['deplacement_inclus']) : $b['deplacement_inclus']; ?>
      <?= composant('champ', ['nom' => 'tarif_diagnostic', 'label' => 'Diagnostic, à partir de', 'valeur' => (string) $valeur('tarif_diagnostic', $montant($b['tarif_diagnostic'])), 'suffixe' => 'FCFA', 'inputmode' => 'numeric', 'autocomplete' => 'off', 'erreur' => $erreurs['tarif_diagnostic'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'tarif_deplacement', 'label' => 'Déplacement', 'valeur' => $inclus ? '' : (string) $valeur('tarif_deplacement', $montant($b['tarif_deplacement'])), 'suffixe' => 'FCFA', 'inputmode' => 'numeric', 'autocomplete' => 'off', 'desactive' => $inclus, 'erreur' => $erreurs['tarif_deplacement'] ?? null]) ?>
      <?= composant('ligne-choix', ['nom' => 'deplacement_inclus', 'valeur' => '1', 'libelle' => 'Déplacement inclus dans le diagnostic', 'type' => 'checkbox', 'coche' => $inclus]) ?>

    <?php elseif ($section === 'presentation'): ?>
      <p class="t-petit t-secondaire">En une phrase, ce que vous faites de mieux.</p>
      <?= composant('champ', ['nom' => 'presentation', 'label' => 'Phrase de présentation', 'labelCache' => true, 'type' => 'textarea', 'valeur' => (string) $valeur('presentation', $b['presentation']), 'max' => InscriptionService::PRESENTATION_MAX, 'lignes' => 3, 'erreur' => $erreurs['presentation'] ?? null]) ?>

    <?php elseif ($section === 'atelier'): ?>
      <p class="t-petit t-secondaire">Jusqu’à <?= ProfilMecanoService::PHOTOS_ATELIER_MAX ?> photos. Lumière du jour, atelier visible, pas de plaque lisible.</p>
      <ul class="profil-modifier__photos" role="list">
        <?php foreach ($f['atelier'] as $p): ?>
          <li><?= composant('photo-publique', ['photo' => $p, 'alt' => 'Une photo de votre atelier', 'sizes' => '173px', 'classe' => 'photo-publique--petite']) ?>
            <button class="actions-avis__lien t-label" type="submit" name="retirer" value="<?= e($p['reference']) ?>" formnovalidate><span>Retirer cette photo</span></button></li>
        <?php endforeach ?>
      </ul>
      <?php if (count($f['atelier']) < ProfilMecanoService::PHOTOS_ATELIER_MAX): ?>
        <div class="champ<?= isset($erreurs['photo']) ? ' champ--erreur' : '' ?>">
          <label class="champ__label" for="photo">Ajouter une photo</label>
          <input class="profil-modifier__fichier" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment">
          <?php if (isset($erreurs['photo'])): ?><p class="champ__message"><?= icone('triangle') ?><span><?= e($erreurs['photo']) ?></span></p><?php endif ?>
        </div>
      <?php endif ?>

    <?php elseif ($section === 'contacts'): ?>
      <?= composant('champ', ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'valeur' => (string) $valeur('telephone', Format::telephoneLocal($f['telephone'])), 'erreur' => $erreurs['telephone'] ?? null, 'aide' => 'Les clients vous appellent sur ce numéro, et vous vous connectez avec.']) ?>
      <?= composant('champ', ['nom' => 'whatsapp', 'label' => 'WhatsApp, s’il est différent (facultatif)', 'type' => 'tel', 'valeur' => (string) $valeur('whatsapp', $f['whatsapp'] !== null ? Format::telephoneLocal($f['whatsapp']) : ''), 'erreur' => $erreurs['whatsapp'] ?? null, 'aide' => 'Votre numéro WhatsApp peut être différent de votre téléphone.']) ?>

    <?php elseif ($section === 'mot-de-passe'): ?>
      <?= composant('champ', ['nom' => 'actuel', 'label' => 'Mot de passe actuel', 'type' => 'password', 'erreur' => $erreurs['actuel'] ?? null, 'autocomplete' => 'current-password']) ?>
      <?= composant('champ', ['nom' => 'nouveau', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'erreur' => $erreurs['nouveau'] ?? null, 'autocomplete' => 'new-password', 'aide' => '8 caractères au minimum, dont 1 chiffre.']) ?>
    <?php endif ?>

    <?php if ($section !== 'atelier' || count($f['atelier']) < ProfilMecanoService::PHOTOS_ATELIER_MAX): ?>
      <?= composant('socle', ['actions' => composant('bouton', ['libelle' => $section === 'atelier' ? 'Ajouter la photo' : 'Enregistrer', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Enregistrement…', 'attrs' => ['type' => 'submit']])]) ?>
    <?php endif ?>
  </form>
</div>
