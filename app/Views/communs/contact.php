<?php
/**
 * X4 · Contact (Figma 140:298) : trouvez un mécano plutôt que d'écrire en cas de panne, trois canaux, puis le formulaire (RG-CT1).
 * @var array<string, mixed> $saisie
 * @var array<string, string> $erreurs
 * @var array{whatsapp: string, telephone: string, email: string} $canaux
 */
use App\Core\Format;
use App\Services\MessageContactService;

$typo = fn (string $t): string => e(Format::typo($t));
$lignes = [
    ['icone' => 'whatsapp', 'titre' => 'WhatsApp', 'detail' => 'Le plus rapide · ', 'valeur' => Format::telephone($canaux['whatsapp']), 'href' => 'https://wa.me/' . ltrim($canaux['whatsapp'], '+')],
    ['icone' => 'telephone', 'titre' => 'Téléphone', 'detail' => '', 'valeur' => Format::telephone($canaux['telephone']), 'href' => 'tel:' . $canaux['telephone']],
    ['icone' => 'email', 'titre' => 'E-mail', 'detail' => '', 'valeur' => $canaux['email'], 'href' => 'mailto:' . $canaux['email']],
];
?>
<article class="contact">
  <section class="contact__panne sur-sombre" aria-label="En panne maintenant">
    <p class="t-titre-3"><?= $typo('En panne maintenant ? Ne nous écrivez pas : trouvez un mécano.') ?></p>
    <a class="lien-icone t-gras" href="/position"><span>Trouver un mécano</span><?= icone('chevron', 20) ?></a>
  </section>
  <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-08', 'texte' => 'Mains qui serrent un écrou de roue'], 'alt' => 'Des mains serrent un écrou de roue.', 'legende' => 'DU LUNDI AU SAMEDI · 7 H – 20 H', 'classe' => 'contact__photo']) ?>

  <div class="contact__corps conteneur">
    <h1 class="t-titre-1">Écrivez-nous, on répond.</h1>
    <p class="t-base t-secondaire"><?= $typo('On répond du lundi au samedi, de 7 h à 20 h. Une question, un souci avec un mécano, une idée : on lit tout.') ?></p>

    <ul class="contact__canaux" role="list">
      <?php foreach ($lignes as $l): ?>
        <li><a class="contact__canal" href="<?= e($l['href']) ?>"<?= str_starts_with($l['href'], 'https') ? ' rel="noopener" target="_blank"' : '' ?>>
          <?= icone($l['icone'], 24) ?>
          <span class="contact__canal-texte"><span class="t-titre-3"><?= e($l['titre']) ?></span><span class="<?= $l['detail'] !== '' ? 't-petit t-secondaire' : 't-donnee t-secondaire' ?>"><?= e($l['detail']) ?><?= $l['detail'] !== '' ? '<span class="t-donnee">' . e($l['valeur']) . '</span>' : e($l['valeur']) ?></span></span>
          <?= icone('chevron', 20) ?>
        </a></li>
      <?php endforeach ?>
    </ul>

    <form class="contact__formulaire" method="post" action="/contact" novalidate>
      <?= csrf_champ() ?>
      <div>
        <p class="t-etiquette t-secondaire">Formulaire</p>
        <h2 class="t-titre-2">Ou laissez-nous un message.</h2>
      </div>
      <div class="contact__vous-etes">
        <p class="t-label" id="vous-etes">Vous êtes</p>
        <?= composant('segment', ['nom' => 'profil', 'options' => ['client' => 'Client', 'mecano' => 'Mécanicien', 'partenaire' => 'Presse ou partenaire'], 'valeur' => (string) ($saisie['profil'] ?? 'client'), 'decritPar' => 'vous-etes']) ?>
        <?php if (isset($erreurs['profil'])): ?><p class="champ__message" role="alert"><?= icone('triangle') ?><span><?= e($erreurs['profil']) ?></span></p><?php endif ?>
      </div>
      <?= composant('champ', ['nom' => 'prenom', 'label' => 'Prénom', 'valeur' => (string) ($saisie['prenom'] ?? ''), 'max' => 80, 'autocomplete' => 'given-name', 'erreur' => $erreurs['prenom'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'contact', 'label' => 'Téléphone ou e-mail', 'valeur' => (string) ($saisie['contact'] ?? ''), 'aide' => 'Pour vous répondre, rien d’autre.', 'autocomplete' => 'email', 'erreur' => $erreurs['contact'] ?? null]) ?>
      <?= composant('champ', ['nom' => 'message', 'label' => 'Message', 'type' => 'textarea', 'valeur' => (string) ($saisie['message'] ?? ''), 'max' => MessageContactService::MESSAGE_MAX, 'lignes' => 5, 'erreur' => $erreurs['message'] ?? null]) ?>
      <?php if (!empty($limite)): ?><?= composant('note', ['type' => 'erreur', 'message' => e($limite)]) ?><?php endif ?>
      <div class="contact__envoi">
        <p class="t-petit t-secondaire">Réponse sous 24 h ouvrées.</p>
        <?= composant('bouton', ['libelle' => 'Envoyer', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Envoi…', 'attrs' => ['type' => 'submit']]) ?>
        <p class="t-micro t-secondaire">Vos coordonnées servent seulement à vous répondre.</p>
      </div>
    </form>
  </div>
</article>
