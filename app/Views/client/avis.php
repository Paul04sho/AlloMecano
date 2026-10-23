<?php
/**
 * C6b · Votre avis (Figma 87:1576) : note, étiquettes, commentaire, prénom et téléphone « pour publier votre avis » (vérifier un vrai appel, RG-AV1).
 * Publié tout de suite (RG-AV4) ; modifiable 24 h (RG-AV2).
 * @var array<string, mixed> $contact
 * @var list<array{id: int, code: string, libelle: string, polarite: string}> $etiquettes
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $saisie
 * @var bool $verrouille
 */
use App\Core\Format;
use App\Services\AvisService;

$c = $contact;
$retour = '/mecano/' . rawurlencode($c['slug']);
$jour = trim((string) preg_replace('/\s\d{4}$/', '', Format::date($c['cree_le'])));
$prenom = trim((string) ($saisie['prenom'] ?? ''));
?>
<form class="avis-page avec-socle" method="post" action="/avis/<?= e($c['public_id']) ?>" novalidate data-avis-formulaire>
  <?= csrf_champ() ?>
  <?= composant('barre-formulaire', ['icone' => 'fermer', 'retour' => $retour, 'rubrique' => 'Votre avis', 'action' => 'Plus tard']) ?>

  <div class="avis-page__corps">
    <div class="avis-page__qui">
      <span class="retour__portrait avis-page__portrait">
        <?php if (!empty($c['portrait'])): ?><img src="<?= e($c['portrait']['src']) ?>" srcset="<?= e($c['portrait']['srcset']) ?>" sizes="56px" width="56" height="56" alt="" style="background:<?= e($c['portrait']['couleur']) ?>;object-position:<?= e($c['portrait']['cadrage']) ?>"><?php endif ?>
        <?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?>
      </span>
      <div>
        <p class="t-titre-3"><?= e($c['nom_complet']) ?></p>
        <p class="t-donnee t-secondaire">Appelé le <?= e($jour) ?> à <?= e(Format::heure($c['cree_le'])) ?></p>
      </div>
    </div>

    <?php if (!empty($verrouille)): ?>
      <?= composant('note', ['type' => 'info', 'message' => e('Votre avis a été publié il y a plus de 24 h : il ne peut plus être modifié.')]) ?>
    <?php endif ?>

    <h1 class="t-titre-1">Comment ça s’est passé avec <?= e($c['prenom']) ?><?= Format::FINE ?>?</h1>

    <fieldset class="avis-page__bloc"<?= !empty($verrouille) ? ' disabled' : '' ?>>
      <legend class="t-etiquette t-secondaire">Votre note</legend>
      <?= composant('etoiles-saisie', ['nom' => 'note', 'valeur' => (int) ($saisie['note'] ?? 0), 'erreur' => $erreurs['note'] ?? null]) ?>
      <p class="t-petit t-secondaire">Touchez une étoile pour changer la note.</p>
    </fieldset>

    <fieldset class="avis-page__bloc"<?= !empty($verrouille) ? ' disabled' : '' ?>>
      <legend class="t-etiquette t-secondaire">En quelques mots</legend>
      <div class="etiquettes-choix">
        <?php foreach ($etiquettes as $e): ?>
          <label class="etiquette-choix etiquette-choix--44">
            <input type="checkbox" name="etiquettes[]" value="<?= $e['id'] ?>"<?= in_array($e['id'], (array) ($saisie['etiquettes'] ?? []), true) ? ' checked' : '' ?>>
            <span><?= icone('coche', 16) ?><?= e($e['libelle']) ?></span>
          </label>
        <?php endforeach ?>
      </div>
    </fieldset>

    <?= composant('champ', ['nom' => 'commentaire', 'label' => 'Un commentaire (facultatif)', 'type' => 'textarea', 'valeur' => (string) ($saisie['commentaire'] ?? ''), 'max' => AvisService::COMMENTAIRE_MAX, 'lignes' => 4,
        'erreur' => $erreurs['commentaire'] ?? null, 'desactive' => !empty($verrouille)]) ?>

    <section class="avis-page__bloc">
      <h2 class="t-etiquette t-secondaire">Pour publier votre avis</h2>
      <p class="t-petit t-secondaire"><?= e(Format::typo('Demandés ici seulement, pour vérifier que l’avis vient d’un vrai appel.')) ?></p>
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Votre prénom', 'valeur' => $prenom, 'aide' => 'Affiché avec votre avis.', 'max' => 40, 'autocomplete' => 'given-name',
          'erreur' => $erreurs['prenom'] ?? null, 'desactive' => !empty($verrouille)]) ?>
      <?= composant('champ', ['nom' => 'telephone', 'label' => 'Votre téléphone', 'type' => 'tel', 'valeur' => (string) ($saisie['telephone'] ?? ''), 'aide' => 'Jamais affiché sur le site.',
          'erreur' => $erreurs['telephone'] ?? null, 'desactive' => !empty($verrouille)]) ?>
      <p class="t-etiquette t-secondaire avis-page__mention" data-mention data-modele="Publié sous le prénom {prenom} · votre numéro n’est jamais affiché">Publié sous le prénom <?= e($prenom !== '' ? $prenom : '…') ?> · votre numéro n’est jamais affiché</p>
    </section>
  </div>

  <?php if (empty($verrouille)): ?>
    <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'Publier mon avis', 'icone' => 'etoile', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Publication…', 'attrs' => ['type' => 'submit']])]) ?>
  <?php endif ?>
</form>
