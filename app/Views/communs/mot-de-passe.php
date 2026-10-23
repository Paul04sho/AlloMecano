<?php
/**
 * X2 · Mot de passe oublié (Figma 129:112 · 129:153 · 130:147 · 130:215 · 131:190) : cinq états d'un même parcours en 3 étapes (RG-C3).
 * @var string $etape      email | envoye | nouveau | termine | expire
 * @var array<string, string> $erreurs
 * @var array<string, mixed> $infos      email, email_masque, jeton, envoye_le (selon l'étape)
 */
use App\Core\Format;

$d = $infos;
$typo = fn (string $t): string => e(Format::typo($t));
$barres = ['email' => 1, 'envoye' => 2, 'nouveau' => 3, 'expire' => 3];
?>
<div class="mdp mdp--<?= e($etape) ?>">
  <?= composant('barre-parcours', ['etape' => $barres[$etape] ?? 3, 'total' => 3, 'rubrique' => 'Mot de passe', 'termine' => $etape === 'termine', 'retour' => $etape === 'nouveau' || $etape === 'expire' ? '/connexion' : ($etape === 'envoye' ? '/mot-de-passe' : '/connexion'), 'annuler' => '/connexion']) ?>

  <?php if ($etape === 'email'): ?>
    <form class="mdp__corps" method="post" action="/mot-de-passe" novalidate>
      <?= csrf_champ() ?>
      <h1 class="t-titre-1"><?= $typo('Mot de passe oublié ?') ?></h1>
      <p class="t-base"><?= $typo('Entrez votre e-mail : on vous envoie un lien valable 30 minutes.') ?></p>
      <?= composant('champ', ['nom' => 'email', 'label' => 'E-mail', 'type' => 'email', 'valeur' => (string) ($d['email'] ?? ''), 'aide' => 'Celui donné à l’inscription.', 'autocomplete' => 'email', 'inputmode' => 'email', 'erreur' => $erreurs['email'] ?? null]) ?>
      <?= composant('bouton', ['libelle' => 'Recevoir le lien', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Envoi…', 'attrs' => ['type' => 'submit']]) ?>
      <a class="lien t-gras mdp__lien" href="/connexion">Retour à la connexion</a>
      <div class="mdp__sans-email">
        <p class="t-petit t-secondaire"><?= $typo('Pas d’e-mail sur votre compte ?') ?></p>
        <a class="lien-icone t-gras" href="/contact"><span>Nous contacter</span><?= icone('chevron', 20) ?></a>
      </div>
    </form>

  <?php elseif ($etape === 'envoye'): ?>
    <section class="mdp__corps" data-renvoi="<?= (int) $d['attente'] ?>">
      <h1 class="t-titre-1">Regardez vos e-mails.</h1>
      <div class="mdp__lignes">
        <p class="t-base">Un lien vient de partir vers</p>
        <p class="t-donnee"><?= e($d['email_masque']) ?></p>
      </div>
      <p class="t-base t-secondaire"><?= $typo('Il est valable 30 minutes. Pas reçu ? Vérifiez vos indésirables.') ?></p>
      <form method="post" action="/mot-de-passe/renvoyer" class="mdp__renvoi">
        <?= csrf_champ() ?>
        <p class="t-petit t-secondaire" aria-live="polite" data-renvoi-texte<?= (int) $d['attente'] === 0 ? ' hidden' : '' ?>>Nouvel envoi possible dans <span class="t-donnee" data-renvoi-minuteur><?= e(sprintf('%d:%02d', intdiv((int) $d['attente'], 60), (int) $d['attente'] % 60)) ?></span></p>
        <?= composant('bouton', ['libelle' => 'Renvoyer', 'type' => 'secondaire', 'hauteur' => 56, 'plein' => true, 'desactive' => (int) $d['attente'] > 0, 'chargement' => 'Envoi…', 'attrs' => ['type' => 'submit', 'data-renvoi-bouton' => '1']]) ?>
      </form>
      <a class="lien t-gras mdp__lien" href="/mot-de-passe">Changer d’adresse</a>
    </section>

  <?php elseif ($etape === 'nouveau'): ?>
    <form class="mdp__corps" method="post" action="/mot-de-passe/nouveau/<?= e($d['jeton']) ?>" novalidate data-formulaire-mdp>
      <?= csrf_champ() ?>
      <h1 class="t-titre-1">Nouveau mot de passe</h1>
      <p class="t-base">Pour le compte <span class="t-donnee"><?= e($d['email_masque']) ?></span>.</p>
      <div class="mdp__champ">
        <?= composant('champ', ['nom' => 'mot_de_passe', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'erreur' => $erreurs['mot_de_passe'] ?? null]) ?>
        <ul class="mdp__regles" role="list" aria-live="polite">
          <?= composant('regle-vivante', ['regle' => 'longueur', 'libelle' => '8 caractères au moins']) ?>
          <?= composant('regle-vivante', ['regle' => 'chiffre', 'libelle' => 'Au moins 1 chiffre']) ?>
        </ul>
      </div>
      <div class="mdp__champ">
        <?= composant('champ', ['nom' => 'confirmation', 'label' => 'Confirmez le mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'erreur' => $erreurs['confirmation'] ?? null]) ?>
        <ul class="mdp__regles" role="list" aria-live="polite">
          <?= composant('regle-vivante', ['regle' => 'identiques', 'libelle' => 'Les deux sont identiques']) ?>
        </ul>
      </div>
      <?= composant('bouton', ['libelle' => 'Enregistrer', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Enregistrement…', 'attrs' => ['type' => 'submit']]) ?>
    </form>

  <?php elseif ($etape === 'termine'): ?>
    <section class="mdp__corps mdp__corps--fait">
      <?= composant('poincon', ['taille' => 96]) ?>
      <h1 class="t-display-xl">C’est fait.</h1>
      <p class="t-base"><?= $typo('Votre nouveau mot de passe est enregistré. Servez-vous-en dès maintenant pour vous connecter.') ?></p>
      <?= composant('bouton', ['libelle' => 'Me connecter', 'hauteur' => 56, 'plein' => true, 'href' => '/connexion']) ?>
    </section>

  <?php else: /* expire */ ?>
    <form class="mdp__corps" method="post" action="/mot-de-passe/nouveau/<?= e($d['jeton']) ?>/renvoyer" novalidate>
      <?= csrf_champ() ?>
      <p class="mdp__rubrique t-etiquette t-secondaire"><?= icone('horloge', 20) ?><span>Lien envoyé à <?= e(Format::heure($d['envoye_le'])) ?> · valable 30 min</span></p>
      <h1 class="t-titre-1">Ce lien a expiré.</h1>
      <p class="t-base"><?= $typo('Par sécurité, un lien ne sert que 30 minutes. Demandez-en un nouveau : il partira vers') ?> <span class="t-donnee"><?= e($d['email_masque']) ?></span>.</p>
      <?= composant('bouton', ['libelle' => 'Recevoir un nouveau lien', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Envoi…', 'attrs' => ['type' => 'submit']]) ?>
      <a class="lien t-gras mdp__lien" href="/connexion">Retour à la connexion</a>
    </form>
  <?php endif ?>
</div>
