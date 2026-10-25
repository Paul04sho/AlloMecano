<?php
/**
 * M2 · Tableau de bord, disponible (Figma 107:678) ou indisponible (109:781), clair et sombre (187:3276).
 * En-tête, interrupteur de disponibilité (RG-D1), position (RG-D2), bornes du jour, dernières demandes, dernier avis, conseil de la semaine.
 * @var array<string, mixed> $profil
 * @var ?array{src: string, cadrage?: string} $avatar
 * @var array{jour: string, appels: int, vues: int, note: ?float, nb_avis: int} $jour
 * @var list<array{valeur: string, unite: string, libelle: string}> $bornes
 * @var array{quand: string, total: int, titre: string, lignes: list<array<string, mixed>>} $liste
 * @var ?array<string, mixed> $avis
 * @var string $zone      « 10 km · Riviera 3 »
 * @var string $position  la ligne sous l'interrupteur
 */
use App\Core\Format;

$dispo = $profil['etat'] !== 'indisponible'; // en intervention : toujours en ligne, pas pour les nouveaux contacts (RG-D4)
$depuis = $profil['depuis'] !== null ? Format::heure($profil['depuis']) : '';
$nbsp = Format::INSECABLE;
?>
<div class="tableau" data-tableau>
  <?= composant('en-tete-pro', ['prenom' => $profil['prenom'], 'ligne' => $profil['en_tete'], 'avatar' => $avatar, 'verifie' => true]) ?>

  <section class="tableau__dispo" aria-label="Disponibilité">
    <div data-interrupteur-conteneur>
      <?= composant('interrupteur-dispo', ['etat' => $dispo ? 'disponible' : 'indisponible', 'depuis' => $depuis, 'zone' => $zone]) ?>
    </div>
    <p class="tableau__position"><?= icone('repere', 20) ?><span class="t-petit t-secondaire" data-position aria-live="polite"><?= e($position) ?></span></p>
    <div data-position-action<?= $dispo ? '' : ' hidden' ?>>
      <?= composant('bouton', ['libelle' => 'Mettre à jour ma position', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'icone' => 'viseur', 'attrs' => ['data-maj-position' => '']]) ?>
    </div>
  </section>

  <section class="tableau__jour" aria-labelledby="jour-titre">
    <p class="t-etiquette t-secondaire" id="jour-titre">Aujourd’hui · <?= e($jour['jour']) ?></p>
    <div data-bornes><?= composant('bornes-jour', ['bornes' => $bornes]) ?></div>
  </section>

  <section class="tableau__demandes" aria-labelledby="demandes-titre">
    <div class="tableau__entete">
      <p class="t-etiquette t-secondaire">Dernières demandes</p>
      <?php if ($liste['total'] > 0): ?><h2 class="t-titre-2" id="demandes-titre"><?= e($liste['titre']) ?></h2>
      <?php else: ?><h2 class="t-titre-2" id="demandes-titre">Aucun client ne vous a encore appelé.</h2><?php endif ?>
    </div>
    <?php if ($liste['total'] > 0): ?>
      <ul class="tableau__lignes" role="list"><?php foreach ($liste['lignes'] as $d): ?><?= composant('ligne-demande', ['demande' => $d]) ?><?php endforeach ?></ul>
      <a class="lien-icone t-gras tableau__lien" href="/pro/demandes"><span><?= $liste['total'] > 1 ? 'Voir les ' . $liste['total'] . ' demandes' : 'Voir la demande' ?></span><?= icone('chevron', 20) ?></a>
    <?php else: ?>
      <p class="t-base t-secondaire">Les demandes arrivent dès que vous êtes disponible et qu’un client vous appelle.</p>
    <?php endif ?>
  </section>

  <section class="tableau__avis" aria-labelledby="avis-titre">
    <?php if ($avis !== null): ?>
      <p class="t-etiquette t-secondaire" id="avis-titre">Dernier avis · <?= e(Format::nombre((float) $profil['note'], 1)) ?> sur 5 · <?= (int) $profil['nb_avis'] ?> avis</p>
      <?= composant('avis', $avis + ['prenomMecano' => $profil['prenom']]) ?>
    <?php endif ?>
    <a class="lien-icone t-gras tableau__lien" href="/pro/apercu"><span>Voir ma fiche comme un client</span><?= icone('chevron', 20) ?></a>
  </section>

  <section class="tableau__conseil" aria-labelledby="conseil-titre">
    <p class="t-etiquette t-secondaire" id="conseil-titre">Le conseil de la semaine</p>
    <?= composant('photo', [
        'image' => 'img/accueil/img-05', 'largeurs' => [302, 604], 'sizes' => '358px', 'largeur' => 604, 'hauteur' => 756, 'classe' => 'tableau__photo',
        'alt' => 'Un mécanicien en tenue de travail tient une clé à molette devant un atelier.',
    ]) ?>
    <p class="t-titre-3">Un client en panne n’attend pas&#8239;: <span class="tableau__souligne">décrochez vite</span>, ou passez indisponible.</p>
  </section>
</div>
