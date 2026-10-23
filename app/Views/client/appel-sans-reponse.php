<?php
/**
 * C5c · « Koffi ne répond pas. » (Figma 270:1846) : après 30 s sans réponse (RG-A5), et les cas voisins de RG-I1 / RG-A7 que Figma ne dessine pas :
 * mécano occupé ou refus, micro refusé ou connexion impossible. Le suivant est proposé tout de suite ; le numéro du même mécano en secours.
 * @var array<string, mixed> $contact
 * @var string $statut      manque | occupe | echoue
 * @var string $retour
 * @var ?array<string, mixed> $suivant
 * @var ?string $numero     E.164, donné parce que le contact existe (RG-A1)
 * @var int $sonnerie_s
 */
use App\Core\Format;

$c = $contact;
$nbsp = Format::INSECABLE;
$typo = static fn (string $t): string => e(Format::typo($t));
$micro = $c['fin_par'] === 'micro_refuse';
$etat = match ($statut) {
    'occupe' => ['indisponible', $c['prenom'] . ' est déjà en ligne', $c['prenom'] . ' est déjà en ligne.', 'Il parle à un autre client.'],
    'echoue' => ['erreur', $micro ? 'Micro refusé' : 'Connexion impossible', 'L’appel n’a pas pu se connecter.', $micro ? 'Autorisez le micro dans votre navigateur, ou appelez ' . $c['prenom'] . ' sur son numéro.' : 'Le réseau n’a pas tenu.'],
    default => ['indisponible', $sonnerie_s . $nbsp . 's sans réponse', $c['prenom'] . ' ne répond pas.', 'Il est peut-être sous un capot.'],
};
[$voyant, $ligneEtat, $titre, $debut] = $etat;
$suite = $suivant !== null ? ' ' . $suivant['prenom'] . ' est disponible à ' . Format::distance($suivant['distance_m']) . ' : appelez-le tout de suite.' : ' Personne d’autre n’est disponible près de vous pour l’instant.';
$pose = $statut === 'echoue' && $micro ? '' : $suite; // micro refusé : d'abord le réglage, le suivant reste en dessous
$formulaire = static fn (string $mecano, string $canal, string $contenu): string => composant('formulaire-contact', [
    'mecano' => $mecano, 'recherche' => $c['recherche'], 'canal' => $canal, 'precedent' => $c['public_id'], 'position' => $c['position_partagee'], 'contenu' => $contenu]);
?>
<div class="appel appel--clair" data-appel-fin="sans-reponse" data-contact="<?= e($c['public_id']) ?>">
  <header class="appel__barre">
    <a class="bouton-icone" href="<?= e($retour) ?>" aria-label="Retour à la liste"><?= icone('retour') ?></a>
    <p class="t-etiquette t-secondaire">Appel à <?= e($c['nom_complet']) ?> · <?= e($c['heure']) ?></p>
  </header>

  <div class="appel__contenu">
    <?= composant('etat-appel', ['voyant' => $voyant, 'texte' => $ligneEtat]) ?>
    <h1 class="appel__titre t-titre-1" tabindex="-1"><?= e($titre) ?></h1>
    <p class="t-base t-secondaire"><?= $typo($debut . $pose) ?></p>

    <?php if ($suivant !== null): ?>
      <section class="appel-suivant" aria-label="Mécano suivant">
        <div class="appel-suivant__ligne">
          <div class="appel-suivant__borne">
            <?= composant('repere-carte', ['type' => 'numerote', 'rang' => $suivant['rang'], 'largeur' => 44]) ?>
            <?= borne(Format::km($suivant['distance_m']), 'km', 'l') ?>
          </div>
          <div class="appel-suivant__texte">
            <p class="t-etiquette t-secondaire"><?= e($suivant['rubrique']) ?></p>
            <a class="appel-suivant__nom t-titre-3" href="<?= e('/mecano/' . $suivant['slug'] . '?r=' . $c['recherche']) ?>"><?= e($suivant['prenom'] . ' ' . $suivant['nom']) ?></a>
            <p class="t-petit t-secondaire"><?= e(implode(' · ', $suivant['specialites'])) ?></p>
            <p class="t-donnee"><?= e(Format::note($suivant['note'])) ?> ★ · <?= (int) $suivant['avis'] ?><?= $nbsp ?>avis</p>
          </div>
          <?php if (!empty($suivant['portrait'])): ?><img class="appel-suivant__portrait" src="<?= e($suivant['portrait']['src']) ?>" srcset="<?= e($suivant['portrait']['srcset']) ?>" sizes="80px" width="80" height="100" alt="" style="background:<?= e($suivant['portrait']['couleur']) ?>;object-position:<?= e($suivant['portrait']['cadrage']) ?>"><?php endif ?>
        </div>
        <div class="appel-suivant__actions">
          <?= $formulaire($suivant['public_id'], 'app', composant('bouton', ['libelle' => 'Appeler ' . $suivant['prenom'], 'icone' => 'telephone', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']])) ?>
          <?= $formulaire($suivant['public_id'], 'whatsapp', composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire', 'hauteur' => 56, 'attrs' => ['type' => 'submit']])) ?>
        </div>
      </section>
    <?php endif ?>

    <?php if ($numero !== null): ?>
      <section class="appel-secours" aria-label="Appeler <?= e($c['prenom']) ?> sur son numéro">
        <?= $formulaire($c['mecano'], 'tel', composant('bouton', ['libelle' => 'Appeler ' . $c['prenom'] . ' sur son numéro', 'icone' => 'telephone', 'type' => 'contour', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']])) ?>
        <p class="t-donnee t-secondaire"><?= e(Format::telephone($numero)) ?> · appel normal, au tarif de votre opérateur</p>
      </section>
    <?php endif ?>

    <a class="appel__lien lien-icone t-label" href="<?= e($retour) ?>"><?= icone('liste', 20) ?><span>Revoir la liste des mécanos</span></a>
  </div>
</div>
