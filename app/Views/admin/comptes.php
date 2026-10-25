<?php
/**
 * A3 · Comptes (Figma 175:310) : mécaniciens et numéros des clients, recherche, table triée sur la dernière activité, tiroir d'un compte,
 * fenêtre de suspension (178:432).
 * @var string $onglet
 * @var string $recherche
 * @var array{lignes: list<array<string, mixed>>, total: int, page: int, pages: int} $liste
 * @var array{mecaniciens: int, clients: int} $decompte
 * @var ?array<string, mixed> $fiche
 * @var ?string $dialogue  suspendre | supprimer
 * @var ?string $numero    numéro de client qui vient d'être affiché
 * @var ?string $numeroRef
 * @var ?string $signalement  signalement que la suspension va traiter (venu d'A4)
 */
use App\Core\Format;
use App\Services\CompteService;

$nb = static fn (int $n): string => Format::nombre($n);
$adresse = static fn (array $en_plus = []): string => '/admin/comptes?' . http_build_query(array_filter(
    array_merge(['onglet' => $onglet === 'clients' ? 'clients' : null, 'q' => $recherche !== '' ? $recherche : null, 'page' => $liste['page'] > 1 ? $liste['page'] : null, 'compte' => $fiche['public_id'] ?? null], $en_plus),
    static fn ($v) => $v !== null && $v !== ''
));
$fin = ($liste['page'] - 1) * CompteService::PAR_PAGE + count($liste['lignes']);
?>
<div class="comptes<?= $fiche !== null ? ' comptes--tiroir' : '' ?>">
  <header class="admin__entete">
    <p class="t-etiquette t-secondaire"><?= e('Comptes · ' . $nb($decompte['mecaniciens']) . ' mécaniciens · ' . $nb($decompte['clients']) . ' numéros clients') ?></p>
    <h1 class="admin__titre">Comptes</h1>
  </header>

  <form class="comptes__outils" method="get" action="/admin/comptes" role="search">
    <?php if ($onglet === 'clients'): ?><input type="hidden" name="onglet" value="clients"><?php endif ?>
    <nav class="comptes__onglets" aria-label="Type de compte">
      <a href="/admin/comptes"<?= $onglet === 'mecaniciens' ? ' aria-current="page"' : '' ?>>Mécaniciens · <?= e($nb($decompte['mecaniciens'])) ?></a>
      <a href="/admin/comptes?onglet=clients"<?= $onglet === 'clients' ? ' aria-current="page"' : '' ?>>Numéros clients</a>
    </nav>
    <?= composant('champ', ['nom' => 'q', 'label' => 'Rechercher un compte', 'valeur' => $recherche, 'exemple' => $onglet === 'clients' ? 'Prénom ou téléphone' : 'Nom, téléphone ou n° de compte', 'autocomplete' => 'off']) ?>
  </form>

  <?php if ($onglet === 'mecaniciens'): ?>
    <table class="comptes__table">
      <caption class="sr-only">Comptes des mécaniciens, dernière activité d’abord</caption>
      <thead>
        <tr class="t-etiquette t-secondaire">
          <th scope="col"><span class="sr-only">Portrait</span></th>
          <th scope="col">Nom · commune</th>
          <th scope="col">Statut</th>
          <th scope="col" class="droite">Note</th>
          <th scope="col" class="droite">Appels<br>30 j</th>
          <th scope="col" class="droite">Signalements</th>
          <th scope="col" class="derniere">Dernière<br>activité</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($liste['lignes'] as $l): $ouvert = $fiche !== null && $fiche['public_id'] === $l['public_id']; ?>
          <tr class="compte<?= $ouvert ? ' compte--ouvert' : '' ?>" data-ligne>
            <td class="compte__portrait">
              <span class="ligne-dossier__portrait<?= $l['photo'] || $l['portrait_piece'] ? '' : ' ligne-dossier__portrait--vide' ?>">
                <?php if ($l['photo']): ?><img src="<?= e($l['photo']['src']) ?>" width="40" height="40" alt="" loading="lazy" style="object-position:<?= e($l['photo']['cadrage']) ?>">
                <?php elseif ($l['portrait_piece']): ?><img src="/admin/dossiers/<?= e($l['public_id']) ?>/portrait" width="40" height="40" alt="" loading="lazy">
                <?php else: ?><?= icone('portrait', 20) ?><?php endif ?>
                <?php if ($l['statut'] === 'valide'): ?><?= composant('poincon', ['taille' => 18, 'surPhoto' => true]) ?><?php endif ?>
              </span>
            </td>
            <th scope="row" class="compte__nom"><a href="<?= e($adresse(['compte' => $l['public_id']])) ?>" data-ouvrir<?= $ouvert ? ' aria-current="true"' : '' ?>><span class="t-gras"><?= e($l['nom_complet']) ?></span><span class="t-petit t-secondaire"><?= e($l['commune']) ?></span></a></th>
            <td><?= composant('statut', ['statut' => $l['statut']]) ?></td>
            <td class="droite t-donnee<?= $l['note'] === null ? ' t-secondaire' : '' ?>"><?= $l['note'] !== null ? e(Format::nombre($l['note'], 1)) : '—' ?></td>
            <td class="droite t-donnee"><?= (int) $l['appels'] ?></td>
            <td class="droite t-donnee<?= $l['signalements'] > 0 ? ' erreur' : ' t-secondaire' ?>"><?= (int) $l['signalements'] ?></td>
            <td class="derniere t-petit t-secondaire"><?= e($l['activite']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="comptes__table comptes__table--clients">
      <caption class="sr-only">Numéros des clients ayant laissé leur téléphone : chaque affichage est enregistré</caption>
      <thead><tr class="t-etiquette t-secondaire"><th scope="col">Prénom</th><th scope="col">Téléphone</th><th scope="col" class="droite">Contacts</th><th scope="col" class="derniere">Dernière visite</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
      <tbody>
        <?php foreach ($liste['lignes'] as $l): ?>
          <tr class="compte" data-ligne>
            <th scope="row" class="compte__nom"><span class="t-gras"><?= e($l['prenom']) ?></span></th>
            <td class="t-donnee"><?= $numeroRef === $l['ref'] && $numero !== null ? '<strong>' . e($numero) . '</strong>' : e($l['masque']) ?></td>
            <td class="droite t-donnee"><?= (int) $l['contacts'] ?></td>
            <td class="derniere t-petit t-secondaire"><?= e($l['activite']) ?></td>
            <td class="compte__action"><form method="post" action="/admin/clients/<?= e($l['ref']) ?>/numero"><?= csrf_champ() ?><input type="hidden" name="q" value="<?= e($recherche) ?>"><input type="hidden" name="page" value="<?= (int) $liste['page'] ?>">
              <button type="submit" class="lien t-petit">Afficher le numéro</button></form></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
  <?php if ($liste['lignes'] === []): ?><p class="admin__vide t-base t-secondaire"><?= e($recherche !== '' ? 'Aucun compte ne correspond à « ' . $recherche . ' ».' : ($onglet === 'clients' ? 'Aucun client n’a laissé son numéro.' : 'Aucun compte pour l’instant.')) ?></p><?php endif ?>

  <footer class="comptes__pied">
    <p class="t-etiquette t-secondaire"><?= $onglet === 'clients' ? 'Chaque numéro affiché est enregistré au journal' : 'Tri : dernière activité · ↑ ↓ parcourir · Entrée ouvrir' ?></p>
    <?php if ($liste['total'] > 0): ?>
      <p class="t-etiquette"><?= (int) $fin ?> sur <?= e($nb($liste['total'])) ?></p>
      <?php if ($liste['page'] > 1): ?><a class="dossiers__pas dossiers__pas--prec" href="<?= e($adresse(['page' => $liste['page'] - 1 > 1 ? $liste['page'] - 1 : null])) ?>" aria-label="Page précédente"><?= icone('chevron', 20) ?></a><?php endif ?>
      <?php if ($liste['page'] < $liste['pages']): ?><a class="dossiers__pas" href="<?= e($adresse(['page' => $liste['page'] + 1])) ?>" aria-label="Page suivante"><?= icone('chevron', 20) ?></a><?php endif ?>
    <?php endif ?>
  </footer>
</div>

<?php if ($fiche !== null): $f = $fiche; $suspendu = $f['statut'] === 'suspendu'; ?>
  <aside class="tiroir" aria-labelledby="tiroir-titre" data-tiroir>
    <header class="tiroir__entete">
      <p class="t-etiquette t-secondaire"><?= e($f['numero'] . ' · inscrit le ' . mb_strtoupper(trim((string) preg_replace('/\s\d{4}$/', '', Format::date($f['inscrit_le']))), 'UTF-8') . ' ' . $f['inscrit_le']->format('Y')) ?></p>
      <a class="bouton-icone" href="<?= e($adresse(['compte' => null])) ?>" aria-label="Fermer le tiroir"><?= icone('fermer') ?></a>
    </header>
    <div class="tiroir__identite">
      <?php if ($f['photo']): ?><img class="tiroir__photo" src="<?= e(str_replace('-160.', '-360.', $f['photo']['src'])) ?>" srcset="<?= e($f['photo']['srcset']) ?>" sizes="160px" width="160" height="200" alt="Portrait de <?= e($f['nom_complet']) ?>" style="object-position:<?= e($f['photo']['cadrage']) ?>;background:<?= e($f['photo']['couleur']) ?>">
      <?php else: ?><span class="tiroir__photo tiroir__photo--vide" aria-hidden="true"><?= icone('portrait', 24) ?></span><?php endif ?>
      <div class="tiroir__infos">
        <h2 class="t-titre-2" id="tiroir-titre"><?= e($f['nom_complet']) ?></h2>
        <?= composant('statut', ['statut' => $f['statut']]) ?>
        <p class="t-etiquette t-secondaire"><?= e(mb_strtoupper(implode(' · ', array_filter([$f['commune'], $f['specialites'][0] ?? null])), 'UTF-8')) ?></p>
        <p class="t-donnee"><?= $f['note'] !== null ? e(Format::nombre($f['note'], 1)) . ' ★ · ' . (int) $f['nb_avis'] . ' avis' : 'Pas encore d’avis' ?></p>
        <p class="t-donnee"><?= e(Format::telephone($f['telephone'])) ?></p>
        <?php if ($f['verifie_le'] !== null): ?><p class="t-petit t-secondaire"><?= e(Format::typo('Visage vérifié le ' . Format::date($f['verifie_le']) . ', comparé à sa pièce d’identité.')) ?></p><?php endif ?>
      </div>
    </div>
    <dl class="tiroir__chiffres">
      <div><dt class="t-etiquette t-secondaire">Appels · 30 j</dt><dd class="t-borne-l"><?= (int) $f['appels'] ?></dd></div>
      <div><dt class="t-etiquette t-secondaire">Avis</dt><dd class="t-borne-l"><?= (int) $f['nb_avis'] ?></dd></div>
      <div class="<?= $f['signalements'] > 0 ? 'erreur' : '' ?>"><dt class="t-etiquette<?= $f['signalements'] > 0 ? '' : ' t-secondaire' ?>"><?= $f['signalements'] > 1 ? 'Signalements' : 'Signalement' ?></dt><dd class="t-borne-l"><?= (int) $f['signalements'] ?></dd></div>
    </dl>
    <section class="tiroir__historique" aria-labelledby="historique-titre">
      <p class="t-etiquette t-secondaire">Historique complet · <?= $f['suspensions'] === 0 ? 'aucune suspension' : (int) $f['suspensions'] . ' suspension' . ($f['suspensions'] > 1 ? 's' : '') ?></p>
      <h3 class="t-titre-3" id="historique-titre">Historique</h3>
      <ol role="list">
        <?php foreach ($f['historique'] as $ev): ?>
          <li>
            <?= icone($ev['icone'], 20) ?>
            <div><p class="t-etiquette t-secondaire"><?= e(mb_strtoupper(trim((string) preg_replace('/\s\d{4}$/', '', Format::date($ev['le']))), 'UTF-8') . ' ' . $ev['le']->format('Y') . ' · ' . Format::heure($ev['le']) . ($ev['ref'] ? ' · ' . $ev['ref'] : '')) ?></p>
              <p class="t-petit"><?= e(Format::typo($ev['texte'])) ?></p></div>
          </li>
        <?php endforeach ?>
      </ol>
    </section>
    <div class="tiroir__actions">
      <?php if ($suspendu): ?>
        <form method="post" action="/admin/comptes/<?= e(rawurlencode($f['public_id'])) ?>/lever"><?= csrf_champ() ?>
          <button type="submit" class="bouton bouton--contour bouton--44 bouton--plein"><span class="bouton__libelle">Lever la suspension</span></button></form>
      <?php else: ?>
        <a class="bouton bouton--contour bouton--44 bouton--plein<?= $f['statut'] === 'valide' ? '' : ' est-desactive' ?>" href="<?= e($adresse(['compte' => $f['public_id'], 'suspendre' => '1'])) ?>"<?= $f['statut'] === 'valide' ? ' data-suspendre' : ' aria-disabled="true"' ?>><span class="bouton__libelle">Suspendre le compte</span></a>
      <?php endif ?>
      <a class="bouton bouton--danger bouton--44 bouton--plein" href="<?= e($adresse(['compte' => $f['public_id'], 'supprimer' => '1'])) ?>" data-supprimer><span class="bouton__libelle">Supprimer</span></a>
    </div>
  </aside>

  <?php if ($f['statut'] === 'valide'): ?>
    <dialog class="modale coupe-l" data-modale="suspendre" aria-labelledby="suspendre-titre"<?= $dialogue === 'suspendre' ? ' open' : '' ?>>
      <form method="post" action="/admin/comptes/<?= e(rawurlencode($f['public_id'])) ?>/suspendre" data-apercu="/admin/comptes/<?= e(rawurlencode($f['public_id'])) ?>/apercu">
        <?= csrf_champ() ?>
        <?php if (!empty($signalement)): ?><input type="hidden" name="signalement" value="<?= e($signalement) ?>"><?php endif ?>
        <header class="modale__entete"><p class="t-etiquette t-secondaire"><?= e('Suspendre · ' . $f['numero'] . ' · ' . mb_strtoupper($f['nom_complet'], 'UTF-8')) ?></p>
          <a class="bouton-icone" href="<?= e($adresse(['compte' => $f['public_id']])) ?>" data-fermer aria-label="Fermer"><?= icone('fermer') ?></a></header>
        <h2 class="t-titre-2" id="suspendre-titre">Suspendre ce compte ?</h2>
        <p class="t-petit t-secondaire">Sa fiche disparaît des résultats tout de suite. Ses avis restent en ligne. Il est prévenu par SMS et par e-mail.</p>
        <?= composant('champ', ['nom' => 'motif', 'label' => 'Motif', 'aide' => 'Cité tel quel dans le message envoyé au mécanicien.', 'max' => 500, 'requis' => true]) ?>
        <fieldset class="modale__durees">
          <legend class="t-label">Durée</legend>
          <?php foreach (CompteService::DUREES as $code => [$libelle]): ?>
            <?= composant('ligne-choix', ['nom' => 'duree', 'valeur' => $code, 'libelle' => Format::typo($libelle), 'attrs' => ['required' => 'required']]) ?>
          <?php endforeach ?>
        </fieldset>
        <div class="modale__apercu">
          <p class="t-etiquette t-secondaire">Aperçu du message · SMS et e-mail</p>
          <p class="modale__message t-petit" data-apercu-texte aria-live="polite"><?= e(CompteService::messageSuspension($f['prenom'], '', 'indefinie')) ?></p>
        </div>
        <div class="modale__actions">
          <a class="bouton bouton--contour bouton--44" href="<?= e($adresse(['compte' => $f['public_id']])) ?>" data-fermer><span class="bouton__libelle">Annuler</span></a>
          <button type="submit" class="bouton bouton--secondaire bouton--44"><?= icone('cadenas') ?><span class="bouton__libelle">Suspendre</span></button>
        </div>
      </form>
    </dialog>
  <?php endif ?>

  <dialog class="modale coupe-l" data-modale="supprimer" aria-labelledby="supprimer-titre"<?= $dialogue === 'supprimer' ? ' open' : '' ?>>
    <form method="post" action="/admin/comptes/<?= e(rawurlencode($f['public_id'])) ?>/supprimer">
      <?= csrf_champ() ?>
      <header class="modale__entete"><p class="t-etiquette t-secondaire"><?= e('Supprimer · ' . $f['numero'] . ' · ' . mb_strtoupper($f['nom_complet'], 'UTF-8')) ?></p>
        <a class="bouton-icone" href="<?= e($adresse(['compte' => $f['public_id']])) ?>" data-fermer aria-label="Fermer"><?= icone('fermer') ?></a></header>
      <h2 class="t-titre-2" id="supprimer-titre">Supprimer ce compte ?</h2>
      <p class="t-petit t-secondaire">Son nom, son téléphone et ses pièces sont effacés tout de suite, sans retour possible. Sa fiche disparaît. Ses avis restent, sans nom.</p>
      <div class="modale__actions">
        <a class="bouton bouton--contour bouton--44" href="<?= e($adresse(['compte' => $f['public_id']])) ?>" data-fermer><span class="bouton__libelle">Annuler</span></a>
        <button type="submit" class="bouton bouton--danger-confirme bouton--44"><span class="bouton__libelle">Supprimer définitivement</span></button>
      </div>
    </form>
  </dialog>
<?php endif ?>
