<?php
/**
 * C7 · Signaler (Figma 89:1612, rempli 210:1774) : motif, texte, photo facultative, prénom et téléphone « pour vous recontacter » (RG-S1 à RG-S3).
 * @var array<string, mixed> $cible
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $saisie
 */
use App\Core\Format;
use App\Services\Parametres;
use App\Services\SignalementService;

$c = $cible;
$retour = '/mecano/' . rawurlencode($c['slug']);
$urgence = Parametres::texte('urgence.police', '170');
$sans = fn (string $t): string => e(Format::typo($t));
?>
<form class="avis-page signalement avec-socle" method="post" action="/signaler/<?= e($c['slug']) ?>" enctype="multipart/form-data" novalidate data-signalement-formulaire>
  <?= csrf_champ() ?>
  <input type="hidden" name="contact" value="<?= e($c['contact']) ?>">
  <?= composant('barre-formulaire', ['icone' => 'retour', 'retour' => $retour, 'rubrique' => 'Signalement', 'action' => 'Annuler']) ?>

  <div class="avis-page__corps signalement__corps">
    <div class="signalement__qui">
      <span class="retour__portrait avis-page__portrait">
        <?php if (!empty($c['portrait'])): ?><img src="<?= e($c['portrait']['src']) ?>" srcset="<?= e($c['portrait']['srcset']) ?>" sizes="56px" width="56" height="56" alt="" style="background:<?= e($c['portrait']['couleur']) ?>;object-position:<?= e($c['portrait']['cadrage']) ?>"><?php endif ?>
        <?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?>
      </span>
      <h1 class="t-titre-1">Signaler <?= e($c['nom_complet']) ?></h1>
    </div>
    <p class="t-base t-secondaire signalement__intro"><?= $sans('Dites-nous ce qui s’est passé. Une personne de l’équipe lit votre message.') ?></p>

    <?= composant('note', ['type' => 'danger', 'message' => '<span>' . $sans('En danger maintenant ? Appelez le ' . $urgence . '.') . '</span>',
        'actions' => composant('bouton', ['libelle' => 'Appeler le ' . $urgence, 'type' => 'danger', 'icone' => 'telephone', 'hauteur' => 44, 'href' => 'tel:' . $urgence])]) ?>

    <fieldset class="signalement__motifs">
      <legend class="t-etiquette t-secondaire">Motif</legend>
      <h2 class="t-titre-2" id="motif-titre"><?= $sans('Qu’est-ce qui ne va pas ?') ?></h2>
      <div class="signalement__lignes" role="radiogroup" aria-labelledby="motif-titre"<?= isset($erreurs['motif']) ? ' aria-invalid="true" aria-describedby="motif-erreur"' : '' ?>>
        <?php foreach (SignalementService::MOTIFS as $code => $libelle): ?>
          <?= composant('ligne-choix', ['nom' => 'motif', 'valeur' => $code, 'libelle' => Format::typo($libelle), 'coche' => ($saisie['motif'] ?? '') === $code, 'attrs' => ['data-motif' => $code]]) ?>
        <?php endforeach ?>
      </div>
      <?php if (isset($erreurs['motif'])): ?><p class="champ__message" id="motif-erreur" role="alert"><?= icone('triangle') ?><span><?= e($erreurs['motif']) ?></span></p><?php endif ?>
    </fieldset>

    <?= composant('champ', ['nom' => 'description', 'label' => 'Racontez-nous', 'type' => 'textarea', 'valeur' => (string) ($saisie['description'] ?? ''), 'max' => SignalementService::DESCRIPTION_MAX, 'lignes' => 4,
        'exemple' => 'Ce qui s’est passé, où, à quelle heure.', 'aide' => Format::typo('Obligatoire seulement si vous choisissez « Autre ».'), 'erreur' => $erreurs['description'] ?? null]) ?>

    <?= composant('champ-photo', ['nom' => 'photo', 'label' => 'Une photo (facultatif)', 'aide' => 'Allégée avant l’envoi. La position enregistrée dans la photo est retirée.', 'erreur' => $erreurs['photo'] ?? null]) ?>

    <section class="avis-page__bloc signalement__contact">
      <h2 class="t-etiquette t-secondaire">Pour vous recontacter</h2>
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Votre prénom', 'valeur' => (string) ($saisie['prenom'] ?? ''), 'aide' => 'Pré-rempli depuis votre recherche.', 'max' => 40, 'autocomplete' => 'given-name', 'erreur' => $erreurs['prenom'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'telephone', 'label' => 'Votre téléphone', 'type' => 'tel', 'valeur' => (string) ($saisie['telephone'] ?? ''), 'aide' => 'Pour vous rappeler si besoin. Jamais transmis au mécano.', 'erreur' => $erreurs['telephone'] ?? null]) ?>
    </section>

    <p class="t-petit t-secondaire"><?= $sans('On lit chaque signalement sous ' . Parametres::int('signalement.lecture_h', 24) . ' h. ' . $c['prenom'] . ' ne saura pas qui l’a signalé.') ?></p>
  </div>

  <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'Envoyer le signalement', 'icone' => 'drapeau', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Envoi…', 'attrs' => ['type' => 'submit']])]) ?>
</form>
