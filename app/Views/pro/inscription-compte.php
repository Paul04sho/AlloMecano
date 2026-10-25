<?php
/**
 * M1a · Inscription 1/4 · Compte (Figma 95:154) : « Créons votre compte. » Google, ou prénom, nom, téléphone, e-mail et mot de passe (RG-C1).
 * Le compte se crée à « Continuer » et sert de brouillon (RG-DO1). Avant, ce qui est saisi reste dans le navigateur.
 * @var array<string, mixed> $saisie
 * @var array<string, string> $erreurs
 * @var bool $modification   le compte existe déjà : on corrige ses coordonnées (« Modifier » de M1d), sans mot de passe
 * @var bool $google         le bouton « Continuer avec Google » est proposé (configuré, pas encore choisi)
 * @var bool $viaGoogle      le compte vient de Google : pas de mot de passe
 * @var string $retour       envoi : on revient à M1d après la correction
 */
use App\Core\Format;

$typo = static fn (string $t): string => Format::typo($t);
?>
<div class="pro inscription avec-socle">
  <?= composant('barre-parcours', ['etape' => 1, 'total' => 4, 'retour' => $modification ? '/pro/inscription/' . ($retour === 'envoi' ? 'envoi' : 'metier') : '/pro/devenir', 'annuler' => '/', 'annulerLibelle' => 'Plus tard']) ?>
  <?= composant('parcours-brouillon', ['pieces' => ['cni_recto' => 'manquante', 'cni_verso' => 'manquante', 'permis' => 'manquante', 'diplome' => 'manquante', 'portrait' => 'manquante', 'atelier' => 'facultative'], 'heure' => null]) ?>

  <form class="inscription__formulaire" method="post" action="/pro/inscription/compte" novalidate data-inscription-compte data-brouillon-cle="<?= $modification ? '' : 'inscription-compte' ?>">
    <?= csrf_champ() ?>
    <?php if ($retour !== ''): ?><input type="hidden" name="retour" value="<?= e($retour) ?>"><?php endif ?>
    <div class="inscription__titre">
      <h1 class="t-titre-1"><?= e($modification ? 'Vos coordonnées.' : 'Créons votre compte.') ?></h1>
      <p class="t-petit t-secondaire"><?= e($typo('Cinq minutes. Rien n’est montré aux clients avant la vérification de votre dossier.')) ?></p>
    </div>

    <?php if ($google): ?>
      <a class="bouton bouton--contour bouton--56 bouton--plein" href="/connexion/google?inscription=1"><span class="bouton__libelle">Continuer avec Google</span></a>
      <p class="inscription__ou t-etiquette t-secondaire" aria-hidden="true"><span>ou</span></p>
    <?php endif ?>

    <div class="inscription__champs">
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'valeur' => (string) ($saisie['prenom'] ?? ''), 'autocomplete' => 'given-name', 'erreur' => $erreurs['prenom'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'nom', 'label' => 'Nom', 'valeur' => (string) ($saisie['nom'] ?? ''), 'autocomplete' => 'family-name', 'erreur' => $erreurs['nom'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'valeur' => (string) ($saisie['telephone'] ?? ''), 'aide' => 'Les clients vous appelleront sur ce numéro.', 'erreur' => $erreurs['telephone'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'email', 'label' => 'E-mail', 'type' => 'email', 'valeur' => (string) ($saisie['email'] ?? ''), 'aide' => 'Pour récupérer votre mot de passe.', 'autocomplete' => 'email', 'inputmode' => 'email', 'erreur' => $erreurs['email'] ?? null]) ?>
      <?php if (!$viaGoogle): ?>
        <div class="inscription__mdp" data-formulaire-mdp>
          <?= composant('champ', ['nom' => 'mot_de_passe', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'erreur' => $erreurs['mot_de_passe'] ?? null]) ?>
          <ul class="inscription__regles" role="list" aria-live="polite">
            <?= composant('regle-vivante', ['regle' => 'longueur', 'libelle' => '8 caractères minimum']) ?>
            <?= composant('regle-vivante', ['regle' => 'chiffre', 'libelle' => 'Au moins 1 chiffre']) ?>
          </ul>
        </div>
      <?php endif ?>
    </div>

    <?php if (!$modification): ?>
      <p class="inscription__connexion t-base"><span class="t-secondaire">Déjà inscrit ?</span> <a class="lien" href="/connexion?suivant=<?= e(rawurlencode('/pro')) ?>">Me connecter</a></p>
    <?php endif ?>

    <?= composant('socle', ['actions' => composant('bouton', ['libelle' => 'Continuer', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Création…', 'attrs' => ['type' => 'submit']])]) ?>
  </form>
</div>
