<?php
/**
 * M1a · Inscription, étape 1/4 — Compte.
 * @var array{prenom:string,nom:string,telephone:string,email:string,heure:string,mis_a_jour:int} $brouillon
 * @var array<string,string> $erreurs
 * @var ?string $message
 * @var ?array{sub:string,email:string,prenom:string,nom:string} $google
 */
use App\Core\Format;

$prenom = (string) ($brouillon['prenom'] ?? '');
$nom = (string) ($brouillon['nom'] ?? '');
$telephone = Format::telephoneLocal((string) ($brouillon['telephone'] ?? ''));
$email = (string) ($brouillon['email'] ?? '');
$heure = (string) ($brouillon['heure'] ?? gmdate('H:i'));
?>
<div class="m1-inscription">
  <header class="m1-navigation">
    <a href="/pro/devenir" class="m1-navigation__retour" aria-label="Retour à la page Devenir mécano" data-m1-navigation>
      <?= icone('retour', 20) ?>
    </a>
    <p class="m1-navigation__etape t-etiquette"><span>Étape 1 / 4</span><span aria-hidden="true">·</span><span>Compte</span></p>
    <a href="/pro/devenir" class="m1-navigation__plus-tard t-petit" data-m1-navigation>Plus tard</a>
  </header>

  <div class="m1-progression" role="progressbar" aria-label="Progression de l’inscription" aria-valuemin="1" aria-valuemax="4" aria-valuenow="1">
    <span class="m1-progression__current"></span><span></span><span></span><span></span>
  </div>

  <div class="m1-brouillon" role="status" aria-live="polite">
    <span class="m1-brouillon__texte t-micro" data-m1-brouillon-statut>Brouillon enregistré à <time data-m1-heure><?= e($heure) ?></time></span>
    <span class="m1-brouillon__icone" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="24" height="24"><path d="M12 2 21 7.2v9.6L12 22 3 16.8V7.2Z" fill="var(--surface-creuse)" stroke="var(--hachure)" stroke-width="1.4"/><path d="m9 12 2 2 4-4" fill="none" stroke="var(--encre-2)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </span>
  </div>

  <div class="m1-contenu">
    <h1 class="t-titre-2 m1-titre">Créons votre compte.</h1>
    <p class="t-petit m1-introduction">Cinq minutes. Rien n’est montré aux clients avant la vérification de votre dossier.</p>

    <?php if (!empty($message)): ?>
      <p class="m1-message t-petit" role="status"><?= e($message) ?></p>
    <?php endif ?>

    <?php if ($google !== null): ?>
      <div class="m1-google-verifie t-petit" role="status">
        <?= icone('coche', 18) ?>
        <span>Compte Google vérifié · <?= e($google['email']) ?></span>
      </div>
    <?php endif ?>

    <a class="m1-google t-bouton" href="/pro/inscription/google" data-m1-google>
      <svg class="m1-google__logo" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
        <path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11c-.5 2.5-1.9 4.6-4 6l6.5 5C41.3 35.5 43.6 30.5 43.6 24.5Z"/>
        <path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.8l-6.5-5c-1.8 1.2-4.1 2-7 2-5.3 0-9.8-3.6-11.4-8.4L5.9 33C9.2 39.6 16.1 44 24 44Z"/>
        <path fill="#FBBC05" d="M12.6 27.8a12 12 0 0 1 0-7.6L5.9 15a20 20 0 0 0 0 18l6.7-5.2Z"/>
        <path fill="#EA4335" d="M24 11.8c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 5.8 29.5 4 24 4 16.1 4 9.2 8.4 5.9 15l6.7 5.2c1.6-4.8 6.1-8.4 11.4-8.4Z"/>
      </svg>
      <span>Continuer avec Google</span>
    </a>

    <div class="m1-separateur" aria-hidden="true"><span></span><span class="t-micro">ou</span><span></span></div>

    <form id="form-compte-mecano" class="m1-formulaire" action="/pro/inscription/1" method="post" novalidate data-m1-formulaire data-google-verifie="<?= $google !== null ? '1' : '0' ?>">
      <?= csrf_champ() ?>

      <?= composant('champ', [
          'nom' => 'prenom', 'label' => 'Prénom', 'type' => 'text',
          'valeur' => $prenom, 'requis' => true, 'max' => 60, 'autocomplete' => 'given-name',
          'erreur' => $erreurs['prenom'] ?? null,
      ]) ?>

      <?= composant('champ', [
          'nom' => 'nom', 'label' => 'Nom', 'type' => 'text',
          'valeur' => $nom, 'requis' => true, 'max' => 60, 'autocomplete' => 'family-name',
          'erreur' => $erreurs['nom'] ?? null,
      ]) ?>

      <?= composant('champ', [
          'nom' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel',
          'valeur' => $telephone, 'requis' => true, 'aide' => 'Les clients vous appelleront sur ce numéro.',
          'erreur' => $erreurs['telephone'] ?? null, 'autocomplete' => 'tel-national',
      ]) ?>

      <?= composant('champ', [
          'nom' => 'email', 'label' => 'E-mail', 'type' => 'email',
          'valeur' => $email, 'requis' => true, 'max' => 190,
          'aide' => 'Pour récupérer votre mot de passe.',
          'erreur' => $erreurs['email'] ?? null, 'autocomplete' => 'email',
      ]) ?>

      <div class="m1-mot-de-passe">
        <?= composant('champ', [
            'nom' => 'mot_de_passe', 'label' => $google !== null ? 'Mot de passe (facultatif avec Google)' : 'Mot de passe',
            'type' => 'password', 'requis' => $google === null, 'autocomplete' => 'new-password',
            'erreur' => $erreurs['mot_de_passe'] ?? null, 'aide' => null,
        ]) ?>
        <ul class="m1-regles t-micro" role="list" aria-label="Règles du mot de passe" data-m1-regles>
          <li data-regle-longueur><?= icone('coche', 18) ?><span>8 caractères minimum</span></li>
          <li data-regle-chiffre><span class="m1-regles__case" aria-hidden="true"></span><span>Au moins 1 chiffre</span></li>
        </ul>
      </div>

      <p class="m1-connexion t-petit">Déjà inscrit ? <a href="/pro" class="lien">Me connecter</a></p>
    </form>
  </div>

  <footer class="m1-socle">
    <?= composant('bouton', [
        'libelle' => 'Continuer', 'type' => 'principal', 'hauteur' => 56, 'plein' => true,
        'chargement' => 'Vérification…',
        'attrs' => ['type' => 'submit', 'form' => 'form-compte-mecano', 'data-m1-continuer' => ''],
    ]) ?>
  </footer>
</div>
