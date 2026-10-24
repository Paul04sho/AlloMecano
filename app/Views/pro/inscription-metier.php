<?php
/**
 * M1b · Première étape de saisie du profil professionnel.
 * Cette vue est un raccord fonctionnel de transition ; la maquette détaillée M1b sera intégrée séparément.
 * @var array<string,mixed> $compte
 * @var ?string $message
 * @var ?string $metier
 * @var ?string $erreur
 */
$metier ??= (string) ($compte['metier'] ?? '');
$experience = (string) ($compte['annees_experience'] ?? '');
?>
<div class="m1-inscription m1-metier">
  <header class="m1-navigation">
    <a href="/pro/inscription/1" class="m1-navigation__retour" aria-label="Retour à l’étape précédente">
      <?= icone('retour', 20) ?>
    </a>
    <p class="m1-navigation__etape t-etiquette"><span>Étape 2 / 4</span><span aria-hidden="true">·</span><span>Métier</span></p>
    <a href="/pro/devenir" class="m1-navigation__plus-tard t-petit">Plus tard</a>
  </header>
  <div class="m1-progression" role="progressbar" aria-label="Progression de l’inscription" aria-valuemin="1" aria-valuemax="4" aria-valuenow="2">
    <span class="m1-progression__done"></span><span class="m1-progression__current"></span><span></span><span></span>
  </div>
  <div class="m1-brouillon" role="status">
    <span class="m1-brouillon__texte">Brouillon enregistré</span>
    <span class="m1-brouillon__icone" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="24" height="24"><path d="M12 2 21 7.2v9.6L12 22 3 16.8V7.2Z" fill="var(--surface-creuse)" stroke="var(--hachure)" stroke-width="1.4"/><path d="m9 12 2 2 4-4" fill="none" stroke="var(--encre-2)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </span>
  </div>
  <div class="m1-contenu">
    <h1 class="t-titre-2 m1-titre">Parlez-nous de votre métier.</h1>
    <p class="t-petit m1-introduction">Ces informations nous aident à préparer votre dossier. Vous pourrez compléter le reste avant sa vérification.</p>

    <?php if (!empty($message)): ?><p class="m1-message t-petit" role="status"><?= e($message) ?></p><?php endif ?>

    <div class="m1-metier__compte">
      <p class="t-etiquette">VOTRE COMPTE</p>
      <p class="t-titre-3"><?= e(trim((string) ($compte['prenom'] ?? '') . ' ' . (string) ($compte['nom'] ?? ''))) ?></p>
      <p class="t-petit"><?= e((string) ($compte['telephone'] ?? '')) ?></p>
      <p class="t-petit"><?= e((string) ($compte['email'] ?? '')) ?></p>
    </div>

    <form class="m1-metier__formulaire" action="/pro/inscription/2" method="post">
      <?= csrf_champ() ?>
      <div class="champ">
        <label class="champ__label" for="m1b-metier">Votre métier</label>
        <div class="champ__boite">
          <select id="m1b-metier" class="m1-metier__select" name="metier" required>
            <option value="">Choisir un métier</option>
            <?php foreach ([
                'Mécanique générale', 'Électricien auto', 'Mécanicien moto',
                'Vulcanisateur', 'Moteur et refroidissement', 'Dépannage et remorquage',
                'Carrosserie', 'Mécanicien poids lourds',
            ] as $option): ?>
              <option value="<?= e($option) ?>"<?= $option === $metier ? ' selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <?php if (!empty($erreur)): ?><p class="champ__message" role="alert"><?= e($erreur) ?></p><?php endif ?>
      </div>
      <div class="champ">
        <label class="champ__label" for="m1b-experience">Années d’expérience</label>
        <div class="champ__boite">
          <input class="m1-metier__nombre" id="m1b-experience" type="number" name="annees_experience" min="0" max="60" step="1" inputmode="numeric" value="<?= e($experience) ?>" required>
        </div>
        <p class="champ__aide">Indiquez le nombre d’années de pratique de votre métier.</p>
      </div>
      <p class="m1-metier__aide t-petit">Vos coordonnées restent dans votre brouillon jusqu’à la fin de l’inscription. Votre profil ne sera pas visible des clients avant vérification.</p>
      <?= composant('bouton', ['libelle' => 'Enregistrer mon métier', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'attrs' => ['type' => 'submit']]) ?>
    </form>
  </div>
</div>
