<?php
/**
 * C5c · « Koffi vous dit s'il vient. » (Figma 270:1905) : l'appel est terminé, on attend la réponse du mécano (RG-I2) qui s'affiche ici dès qu'il raccroche.
 * Le client peut aussi la donner lui-même. « Appel interrompu » quand la connexion s'est perdue (non dessiné : même page, autre barre).
 * @var array<string, mixed> $contact
 * @var string $retour
 * @var ?string $duree     « 2 min 10 »
 * @var string $heure_fin
 * @var bool $interrompu
 */
use App\Core\Format;

$c = $contact;
$bandeau = implode(' · ', array_filter([$interrompu ? 'Appel interrompu' : 'Appel terminé', $duree, $heure_fin]));
$ligne = implode(' · ', array_filter([$c['distance_m'] !== null ? Format::distance($c['distance_m']) : null, $c['quartier_mecano']]));
?>
<div class="appel appel--clair" data-appel-fin="termine" data-contact="<?= e($c['public_id']) ?>">
  <header class="appel__barre">
    <a class="bouton-icone" href="<?= e($retour) ?>" aria-label="Retour à la liste"><?= icone('retour') ?></a>
    <p class="t-etiquette t-secondaire"><?= e($bandeau) ?></p>
  </header>

  <div class="appel__contenu">
    <div class="appel__qui">
      <span class="retour__portrait">
        <?php if (!empty($c['portrait'])): ?><img src="<?= e($c['portrait']['src']) ?>" srcset="<?= e($c['portrait']['srcset']) ?>" sizes="72px" width="72" height="72" alt="" style="background:<?= e($c['portrait']['couleur']) ?>;object-position:<?= e($c['portrait']['cadrage']) ?>"><?php endif ?>
        <?= composant('poincon', ['taille' => 22, 'surPhoto' => true]) ?>
      </span>
      <div>
        <p class="t-titre-3"><?= e($c['nom_complet']) ?></p>
        <p class="t-donnee t-secondaire"><?= e($ligne) ?></p>
      </div>
    </div>

    <h1 class="appel__titre t-titre-1" tabindex="-1"><?= e($c['prenom']) ?> vous dit s’il vient.</h1>
    <?= composant('etat-appel', ['voyant' => 'recherche', 'texte' => 'En attente de sa réponse', 'attrs' => ['data-attente' => '', 'class' => 'etat-appel--encre']]) ?>
    <p class="t-base t-secondaire" aria-live="polite"><?= e(Format::typo('Restez sur cette page : sa réponse s’affiche ici dès qu’il raccroche. Rien à faire de votre côté.')) ?></p>

    <section class="appel__reponses" aria-label="Votre réponse">
      <p class="t-etiquette t-secondaire">Vous pouvez aussi nous le dire</p>
      <?= composant('reponse-retour', ['contact' => $c['public_id'], 'issue' => 'arrive', 'libelle' => 'Il m’a dit qu’il arrive', 'compacte' => true]) ?>
      <?= composant('reponse-retour', ['contact' => $c['public_id'], 'issue' => 'pas_entendus', 'libelle' => 'On ne s’est pas entendus', 'compacte' => true]) ?>
    </section>

    <a class="appel__plus-tard lien t-label" href="<?= e($retour) ?>">Plus tard</a>
  </div>
</div>
