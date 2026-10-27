<?php
/**
 * Une étape de C6 : la question (82:1322), le mécano suivant (84:1394) ou « il arrive » (86:1488).
 * @var string $etape      question | suivant | arrive
 * @var array<string, mixed> $contact
 * @var string $heure      heure de la réponse (UTC = Abidjan)
 * @var ?array<string, mixed> $suivant  mécano suivant de la liste figée (RG-I5), null s'il n'y en a plus
 * @var bool $pasEntendus
 * @var bool $pasPourMoi  le mécano a répondu « Pas pour moi » (RG-I2)
 * @var array{sans: string, position: ?string} $message
 */
use App\Core\Format;

$c = $contact;
$nbsp = Format::INSECABLE;
$typo = static fn (string $t): string => e(Format::typo($t));
$retourListe = $c['recherche'] !== null ? '/resultats/' . $c['recherche'] : '/position';
$heureAppel = $c['heure'];
?>
<div class="retour__etape" data-etape="<?= e($etape) ?>">
<?php if ($etape === 'question'): ?>
  <span class="retour__portrait">
    <?php if (!empty($c['portrait'])): ?><img src="<?= e($c['portrait']['src']) ?>" srcset="<?= e($c['portrait']['srcset']) ?>" sizes="72px" width="72" height="72" alt="" style="background:<?= e($c['portrait']['couleur']) ?>;object-position:<?= e($c['portrait']['cadrage']) ?>"><?php endif ?>
    <?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?>
  </span>
  <p class="retour__rubrique t-etiquette t-secondaire">Appel à <?= e($c['nom_complet']) ?> · <?= e($heureAppel) ?></p>
  <h2 class="retour__titre t-titre-1" id="retour-titre" tabindex="-1">Avez-vous pu joindre <?= e($c['prenom']) ?><?= Format::FINE ?>?</h2>
  <p class="retour__texte t-base t-secondaire"><?= $typo('Sinon, on vous propose tout de suite le mécano disponible le plus proche après lui.') ?></p>
  <div class="retour__reponses">
    <?= composant('reponse-retour', ['contact' => $c['public_id'], 'issue' => 'arrive', 'libelle' => 'Oui, il arrive']) ?>
    <?= composant('reponse-retour', ['contact' => $c['public_id'], 'issue' => 'pas_entendus', 'libelle' => 'Oui, mais on ne s’est pas entendus']) ?>
    <?= composant('reponse-retour', ['contact' => $c['public_id'], 'issue' => 'pas_reponse', 'libelle' => 'Non, pas de réponse']) ?>
  </div>
  <a class="retour__plus-tard lien t-gras" href="<?= e('/mecano/' . $c['slug'] . ($c['recherche'] !== null ? '?r=' . $c['recherche'] : '')) ?>" data-plus-tard>Plus tard</a>
  <p class="retour__pied t-etiquette t-secondaire">Votre réponse nous aide à garder des mécanos joignables</p>

<?php elseif ($etape === 'suivant'): ?>
  <?php $rubrique = (!empty($pasPourMoi) ? $c['prenom'] . ' ne peut pas venir' : ($pasEntendus ? 'Pas d’accord avec ' . $c['prenom'] : $c['prenom'] . ' n’a pas répondu')) . ' · ' . $heure; ?>
  <p class="retour__rubrique t-etiquette t-secondaire"><?= e($rubrique) ?></p>
  <?php if ($suivant !== null): ?>
    <h2 class="retour__titre t-titre-1" id="retour-titre" tabindex="-1"><?= e($suivant['prenom']) ?> est à <?= e(Format::distance($suivant['distance_m'])) ?> et disponible.</h2>
    <p class="retour__texte t-base t-secondaire"><?= $typo('Vérifié lui aussi. Appelez-le directement, sans repasser par la liste.') ?></p>
    <?= composant('ligne-classement', [
        'format' => 'etendue', 'transition' => $suivant['slug'], 'href' => '/mecano/' . $suivant['slug'] . '?r=' . $c['recherche'], 'nom' => $suivant['prenom'] . ' ' . $suivant['nom'], 'portrait' => $suivant['portrait'] ?? ['src' => ''],
        'specialites' => $suivant['specialites'], 'specialitePanne' => $suivant['specialites_panne'], 'note' => $suivant['note'], 'avis' => $suivant['avis'],
        'rang' => $suivant['rang'], 'distanceM' => $suivant['distance_m'], 'autreRive' => $suivant['autre_rive'], 'rubrique' => $suivant['rubrique'], 'classe' => 'retour__suivant',
        'actions' => composant('formulaire-contact', ['mecano' => $suivant['public_id'], 'recherche' => $c['recherche'], 'canal' => 'app', 'precedent' => $c['public_id'], 'position' => $c['position_partagee'],
                'contenu' => composant('bouton', ['libelle' => 'Appeler ' . $suivant['prenom'], 'icone' => 'telephone', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']])])
            . composant('formulaire-contact', ['mecano' => $suivant['public_id'], 'recherche' => $c['recherche'], 'canal' => 'whatsapp', 'precedent' => $c['public_id'], 'position' => $c['position_partagee'],
                'contenu' => composant('bouton', ['libelle' => 'WhatsApp', 'icone' => 'whatsapp', 'type' => 'secondaire', 'hauteur' => 56, 'attrs' => ['type' => 'submit']])]),
    ]) ?>
  <?php else: ?>
    <h2 class="retour__titre t-titre-1" id="retour-titre" tabindex="-1">Personne d’autre n’est disponible près de vous pour l’instant.</h2>
    <p class="retour__texte t-base t-secondaire"><?= $typo('Les mécanos se rendent disponibles tout au long de la journée. Voici ce qu’on peut faire :') ?></p>
  <?php endif ?>
  <?= composant('formulaire-contact', ['mecano' => $c['mecano'], 'recherche' => $c['recherche'], 'canal' => 'tel', 'precedent' => $c['public_id'], 'position' => $c['position_partagee'], 'contenu' =>
      '<button type="submit" class="retour__lien lien-icone t-gras">' . icone('telephone', 20) . '<span>Réessayer ' . e($c['prenom']) . '</span></button>']) ?>
  <a class="retour__lien lien-icone t-gras" href="<?= e($retourListe) ?>"><?= icone('liste', 20) ?><span>Revoir la liste des mécanos</span></a>
  <?php if ($c['lieu']): ?><p class="retour__pied t-etiquette t-secondaire">À vol d’oiseau · depuis <?= e($c['lieu']) ?></p><?php endif ?>

<?php else: /* arrive */ ?>
  <p class="retour__rubrique t-etiquette t-secondaire"><?= e($c['prenom']) ?> arrive · appel de <?= e($heureAppel) ?></p>
  <h2 class="retour__titre t-titre-2" id="retour-titre" tabindex="-1">Restez à l’abri, il est en route.</h2>
  <?= composant('bloc-message', ['message' => $message, 'idInterrupteur' => 'position-retour']) ?>
  <p class="retour__rubrique retour__rubrique--conseils t-etiquette t-secondaire">En attendant <?= e($c['prenom']) ?></p>
  <?= composant('conseils', ['conseils' => ['Convenez du prix avant qu’il commence.', 'Ne payez rien d’avance.', 'Restez visible, feux de détresse allumés.']]) ?>
  <a class="retour__lien lien-icone lien--erreur t-gras" href="/signaler/<?= e($c['slug']) ?>?contact=<?= e($c['public_id']) ?>"><?= icone('drapeau', 20) ?><span>Il n’est jamais venu<?= Format::FINE ?>? Signaler</span></a>
<?php endif ?>
</div>
