<?php
/**
 * A4 · Signalements (Figma 179:555) : colonne des signalements (ouverts ou classés), détail du signalement choisi, fenêtre de classement.
 * @var string $onglet     ouverts | classes
 * @var list<array<string, mixed>> $liste
 * @var string $choisi     public_id du signalement ouvert à droite
 * @var ?array<string, mixed> $detail
 * @var array{ouverts: int, critiques: int, en_retard: int, classes_mois: int, cible_h: int} $decompte
 * @var \DateTimeImmutable $maintenant
 * @var ?string $dialogue  classer
 * @var ?string $numero    numéro de client qui vient d'être affiché
 * @var ?string $numeroRef
 */
use App\Core\Format;
use App\Services\SignalementAdminService;

$pl = static fn (int $n, string $mot): string => $n . ' ' . $mot . ($n > 1 ? 's' : '');
$adresse = static fn (array $en_plus = []): string => '/admin/signalements?' . http_build_query(array_filter(
    array_merge(['onglet' => $onglet === 'classes' ? 'classes' : null, 's' => $choisi !== '' ? $choisi : null], $en_plus),
    static fn ($v) => $v !== null && $v !== ''
));
$entete = 'Signalements · ' . ($decompte['ouverts'] === 0 ? 'aucun ouvert' : $pl($decompte['ouverts'], 'ouvert') . ' · ' . ($decompte['critiques'] > 0 ? 'dont ' . $pl($decompte['critiques'], 'critique') : 'aucun critique'))
    . ' · délai cible : ' . $decompte['cible_h'] . Format::INSECABLE . 'h pour un critique';
$gravite = $detail['gravite'] ?? 'normal';
?>
<div class="signalements">
  <header class="admin__entete">
    <p class="t-etiquette t-secondaire"><?= e(Format::typo($entete)) ?></p>
    <h1 class="admin__titre">Signalements</h1>
  </header>

  <div class="signalements__colonnes">
    <section class="signalements__liste" aria-label="Liste des signalements">
      <?= composant('onglets-admin', ['nom' => 'Signalements ouverts ou classés', 'onglets' => [
          ['libelle' => 'Ouverts · ' . $decompte['ouverts'], 'href' => '/admin/signalements', 'actif' => $onglet === 'ouverts'],
          ['libelle' => 'Classés · 30 jours', 'href' => '/admin/signalements?onglet=classes', 'actif' => $onglet === 'classes'],
      ]]) ?>
      <p class="signalements__tri t-etiquette t-secondaire"><?= $onglet === 'classes' ? 'Tri : derniers classés d’abord' : 'Tri : gravité, puis ancienneté' ?></p>

      <?php if ($onglet === 'ouverts' && $decompte['en_retard'] > 0): ?>
        <?= composant('bande-alerte', ['texte' => Format::typo($pl($decompte['en_retard'], 'critique') . ($decompte['en_retard'] > 1 ? ' non traités' : ' non traité') . ' depuis plus de ' . $decompte['cible_h'] . Format::INSECABLE . 'h')]) ?>
      <?php endif ?>

      <?php if ($liste === []): ?>
        <p class="admin__vide t-base t-secondaire"><?= $onglet === 'classes' ? 'Aucun signalement classé ces 30 derniers jours.' : 'Aucun signalement à traiter pour l’instant.' ?></p>
      <?php else: ?>
        <ul class="signalements__lignes" role="list">
          <?php foreach ($liste as $s): ?>
            <?= composant('ligne-signalement', ['signalement' => $s, 'href' => $adresse(['s' => $s['public_id'], 'classer' => null]), 'actif' => $s['public_id'] === $choisi]) ?>
          <?php endforeach ?>
        </ul>
      <?php endif ?>

      <?php if ($onglet === 'ouverts' && $decompte['classes_mois'] > 0): ?>
        <a class="lien-icone signalements__classes" href="/admin/signalements?onglet=classes"><span class="t-gras">Voir les <?= e($pl($decompte['classes_mois'], 'signalement')) ?> <?= $decompte['classes_mois'] > 1 ? 'classés' : 'classé' ?> ce mois-ci</span><?= icone('chevron', 20) ?></a>
      <?php endif ?>
    </section>

    <?php if ($detail !== null): $d = $detail; $m = $d['mecano']; $c = $d['client']; ?>
      <article class="signalement" aria-labelledby="signalement-titre">
        <p class="signalement__gravite signalement__gravite--<?= e($gravite) ?> t-etiquette">
          <?php if ($gravite === 'critique'): ?><?= composant('voyant', ['etat' => 'erreur']) ?><?php elseif ($gravite === 'important'): ?><?= icone('triangle', 20) ?><?php endif ?>
          <span><?= e(Format::typo(SignalementAdminService::GRAVITE_LIBELLES[$gravite] . ' · ' . $d['numero'] . ' · ' . $d['recu'] . ' · ' . $d['ecoule'])) ?></span>
        </p>
        <h2 class="t-titre-2" id="signalement-titre"><?= e($d['titre']) ?></h2>

        <div class="signalement__mecano">
          <span class="signalement__portrait<?= $m['photo'] || $m['portrait_piece'] ? '' : ' signalement__portrait--vide' ?>">
            <?php if ($m['photo']): ?><img src="<?= e($m['photo']['src']) ?>" srcset="<?= e($m['photo']['srcset']) ?>" sizes="56px" width="56" height="56" alt="" style="object-position:<?= e($m['photo']['cadrage']) ?>">
            <?php elseif ($m['portrait_piece']): ?><img src="/admin/dossiers/<?= e($m['public_id']) ?>/portrait?t=360" width="56" height="56" alt="">
            <?php else: ?><?= icone('portrait', 24) ?><?php endif ?>
            <?php if ($m['statut'] === 'valide'): ?><?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?><?php endif ?>
          </span>
          <div class="signalement__identite">
            <p class="t-etiquette t-secondaire">Mécano concerné</p>
            <p class="t-titre-3"><?= e($m['nom_complet']) ?></p>
            <p class="t-donnee"><?= e(Format::typo(implode(' · ', array_filter([
                $m['note'] !== null ? Format::nombre($m['note'], 1) . ' ★ · ' . (int) $m['nb_avis'] . ' avis' : 'Pas encore d’avis',
                $m['quartier'],
                'signalements passés : ' . ($m['passes'] === 0 ? 'aucun' : (int) $m['passes']),
            ])))) ?></p>
          </div>
          <div class="signalement__lien">
            <?= composant('statut', ['statut' => $m['statut']]) ?>
            <a class="lien-icone" href="/admin/comptes?compte=<?= e(rawurlencode($m['public_id'])) ?>"><span class="t-gras">Voir le compte</span><?= icone('chevron', 20) ?></a>
            <?php if ($d['motif'] === 'identite'): ?>
              <a class="lien-icone" href="/admin/dossiers/<?= e(rawurlencode($m['public_id'])) ?>"><span class="t-gras">Comparer photo et pièce</span><?= icone('chevron', 20) ?></a>
            <?php endif ?>
          </div>
        </div>

        <div class="signalement__bloc">
          <p class="t-etiquette t-secondaire">Récit du client</p>
          <?php if ($d['description'] !== null && $d['description'] !== ''): ?>
            <p class="t-base">«<?= Format::INSECABLE ?><?= e((string) $d['description']) ?><?= Format::INSECABLE ?>»</p>
          <?php else: ?>
            <p class="t-base t-secondaire">Aucun récit : le client a seulement choisi le motif.</p>
          <?php endif ?>
        </div>

        <div class="signalement__colonnes">
          <div class="signalement__bloc">
            <p class="t-etiquette t-secondaire">Client</p>
            <p class="t-gras"><?= e($c['prenom'] !== '' ? $c['prenom'] : 'Client sans prénom') ?></p>
            <?php if ($c['masque'] !== null): ?>
              <div class="signalement__numero">
                <?php if ($numero !== null && $numeroRef === $c['ref']): ?>
                  <p class="t-donnee" aria-live="polite"><strong><?= e($numero) ?></strong></p>
                <?php else: ?>
                  <p class="t-donnee"><?= e($c['masque']) ?></p>
                  <form method="post" action="/admin/clients/<?= e((string) $c['ref']) ?>/numero"><?= csrf_champ() ?><input type="hidden" name="retour" value="<?= e($adresse()) ?>">
                    <button type="submit" class="lien-icone"><?= icone('oeil', 20) ?><span class="t-gras">Afficher</span></button></form>
                <?php endif ?>
              </div>
              <p class="t-etiquette t-secondaire">Chaque affichage est journalisé</p>
            <?php else: ?>
              <p class="t-donnee t-secondaire">Numéro non communiqué</p>
            <?php endif ?>
          </div>
          <div class="signalement__bloc">
            <p class="t-etiquette t-secondaire">Contexte de la mise en relation</p>
            <dl class="signalement__contexte">
              <?php foreach ([['Appel', $d['contexte']['appel']], ['Panne', $d['contexte']['panne']], ['Lieu', $d['contexte']['lieu']]] as [$cle, $valeur]): ?>
                <div><dt class="t-etiquette t-secondaire"><?= e($cle) ?></dt><dd class="t-petit"><?= e(Format::typo($valeur ?? 'Non conservé')) ?></dd></div>
              <?php endforeach ?>
              <div><dt class="t-etiquette t-secondaire">Photo jointe</dt>
                <dd class="t-petit"><?php if ($d['contexte']['photo']): ?><a class="lien" href="/admin/signalements/<?= e(rawurlencode($d['public_id'])) ?>/photo" target="_blank" rel="noopener">Voir la photo</a><?php else: ?>Aucune<?php endif ?></dd></div>
            </dl>
          </div>
        </div>

        <?php if ($d['ouvert']): ?>
          <div class="signalement__actions">
            <div class="signalement__principales">
              <?php if ($m['suspendable']): ?>
                <?= composant('bouton', ['libelle' => 'Traiter : suspendre le mécanicien', 'type' => 'principal', 'hauteur' => 44, 'icone' => 'cadenas', 'href' => '/admin/comptes?compte=' . rawurlencode($m['public_id']) . '&suspendre=1&signalement=' . rawurlencode($d['public_id'])]) ?>
              <?php else: ?>
                <?= composant('bouton', ['libelle' => 'Traiter : suspendre le mécanicien', 'type' => 'principal', 'hauteur' => 44, 'icone' => 'cadenas', 'href' => '#', 'desactive' => true, 'attrs' => ['class' => 'est-desactive']]) ?>
              <?php endif ?>
              <?= composant('bouton', ['libelle' => 'Contacter le mécanicien', 'type' => 'secondaire', 'hauteur' => 44, 'icone' => 'telephone', 'href' => 'tel:' . Format::telephoneE164($m['telephone'])]) ?>
            </div>
            <?php if (!$m['suspendable']): ?><p class="t-etiquette t-secondaire">Ce compte n’est pas actif : il ne peut pas être suspendu.</p><?php endif ?>
            <div class="signalement__classer">
              <?= composant('bouton', ['libelle' => 'Classer sans suite', 'type' => 'contour', 'hauteur' => 44, 'href' => $adresse(['classer' => '1']), 'attrs' => ['data-classer' => '1']]) ?>
              <p class="t-etiquette t-secondaire">Une note est obligatoire pour classer</p>
            </div>
          </div>

          <dialog class="modale coupe-l" data-modale="classer" aria-labelledby="classer-titre"<?= $dialogue === 'classer' ? ' open' : '' ?>>
            <form method="post" action="/admin/signalements/<?= e(rawurlencode($d['public_id'])) ?>/classer">
              <?= csrf_champ() ?>
              <header class="modale__entete"><p class="t-etiquette t-secondaire"><?= e('Classer · ' . $d['numero']) ?></p>
                <a class="bouton-icone" href="<?= e($adresse(['classer' => null])) ?>" data-fermer aria-label="Fermer"><?= icone('fermer') ?></a></header>
              <h2 class="t-titre-2" id="classer-titre">Classer sans suite ?</h2>
              <p class="t-petit t-secondaire">Le mécanicien n’est pas prévenu et ne saura pas qui l’a signalé. Le client voit son signalement comme traité.</p>
              <?= composant('champ', ['nom' => 'note', 'label' => 'Note', 'type' => 'textarea', 'aide' => 'Elle reste au journal, avec votre nom.', 'max' => SignalementAdminService::NOTE_MAX, 'requis' => true]) ?>
              <div class="modale__actions">
                <a class="bouton bouton--contour bouton--44" href="<?= e($adresse(['classer' => null])) ?>" data-fermer><span class="bouton__libelle">Annuler</span></a>
                <button type="submit" class="bouton bouton--secondaire bouton--44"><span class="bouton__libelle">Classer sans suite</span></button>
              </div>
            </form>
          </dialog>
        <?php else: ?>
          <div class="signalement__decision">
            <p class="t-etiquette"><?= e(Format::typo(($d['statut'] === 'sanction' ? 'Sanction décidée' : 'Classé sans suite') . ($d['traite_par'] !== null ? ' par ' . $d['traite_par'] : '') . ($d['traite_le'] !== null ? ' · ' . trim((string) preg_replace('/\s\d{4}$/', '', Format::date($d['traite_le']))) . ' à ' . Format::heure($d['traite_le']) : ''))) ?></p>
            <?php if ($d['decision'] !== null && $d['decision'] !== ''): ?><p class="t-base"><?= e($d['decision']) ?></p><?php endif ?>
          </div>
        <?php endif ?>
      </article>
    <?php endif ?>
  </div>
</div>
