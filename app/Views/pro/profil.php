<?php
/**
 * M4 · Mon profil (Figma 120:1169) : la fiche telle que les clients la voient, avec un crayon par section, puis « Mon compte » (mot de passe, thème, déconnexion, suppression).
 * @var array<string, mixed> $f   ProfilMecanoService::fiche
 * @var int $max                  photos de l'atelier au plus
 */
use App\Core\Format;

$photo = $f['portrait'] ?? null;
$n = count($f['atelier']);
$autres = array_slice($f['atelier'], 1);
?>
<div class="tableau profil">
  <header class="tableau__entete">
    <p class="profil__etat t-etiquette t-secondaire"><?= icone('coche', 16) ?>Tout est enregistré</p>
    <h1 class="t-titre-1">Mon profil</h1>
    <p class="t-petit t-secondaire">Voici votre fiche telle que les clients la voient. Touchez un crayon pour modifier une section.</p>
  </header>

  <div class="profil__apercu">
    <section class="profil__section" aria-label="Photo et identité">
      <?= composant('entete-section', ['libelle' => 'Photo et identité', 'href' => '/pro/profil/identite']) ?>
      <div class="profil__identite">
        <span class="profil__portrait"><?php if ($photo !== null): ?><img src="<?= e(str_replace('-160.', '-360.', $photo['src'])) ?>" srcset="<?= e($photo['srcset']) ?>" sizes="112px" width="112" height="140" alt="Votre portrait" style="object-position:<?= e($photo['cadrage']) ?>;background:<?= e($photo['couleur']) ?>">
          <?php elseif ($f['portrait_piece']): ?><img src="/pro/mes-pieces/portrait" width="112" height="140" alt="Votre portrait"><?php else: ?><?= icone('portrait', 24) ?><?php endif ?></span>
        <div class="profil__nom">
          <h2 class="t-titre-2"><?= e($f['nom_complet']) ?></h2>
          <p class="t-petit t-secondaire"><?= e($f['metier']) ?></p>
          <?= composant('etiquette-verifie', ['etat' => 'verifie']) ?>
          <?= composant('statut', ['statut' => $f['etat'] === 'indisponible' ? 'indisponible' : ($f['etat'] === 'en_intervention' ? 'en_intervention' : 'disponible')]) ?>
        </div>
      </div>
      <?php if ($f['verifie_le'] !== null): ?><p class="t-donnee t-secondaire">Visage vérifié le <?= e($f['verifie_le']) ?>, comparé à votre pièce d’identité.</p><?php endif ?>
      <?= composant('note', ['type' => 'securite', 'message' => 'Changer de photo relance la vérification&nbsp;: vous perdez le badge pendant 48&nbsp;h au plus.']) ?>
    </section>

    <section class="profil__section" aria-label="Spécialités">
      <?= composant('entete-section', ['libelle' => 'Spécialités', 'href' => '/pro/profil/specialites']) ?>
      <div class="profil__etiquettes"><?php foreach ($f['specialites'] as $s): ?><?= composant('etiquette-lecture', ['libelle' => $s]) ?><?php endforeach ?><?php if ($f['specialites'] === []): ?><p class="t-petit t-secondaire">Choisissez ce que vous réparez : les clients le lisent sous votre nom.</p><?php endif ?></div>
    </section>

    <section class="profil__section" aria-label="Zone et rayon">
      <?= composant('entete-section', ['libelle' => 'Zone et rayon', 'href' => '/pro/profil/zone']) ?>
      <p class="t-base"><?= e($f['zone']) ?></p>
      <p class="profil__rayon"><span class="t-borne-l"><?= (int) $f['rayon'] ?></span><span class="t-etiquette">km</span><span class="t-petit t-secondaire">de rayon, à vol d’oiseau</span></p>
      <?php if ($f['lat'] !== null): ?><?= composant('carte-zone', ['lat' => $f['lat'], 'lon' => $f['lon'], 'rayon' => $f['rayon'], 'visage' => $photo['src'] ?? null]) ?><?php endif ?>
    </section>

    <section class="profil__section" aria-label="Tarifs indicatifs">
      <?= composant('entete-section', ['libelle' => 'Tarifs indicatifs', 'href' => '/pro/profil/tarifs']) ?>
      <?= composant('ligne-tarif', ['libelle' => 'Diagnostic, dès', 'montant' => $f['diagnostic']]) ?>
      <?= composant('ligne-tarif', ['libelle' => 'Déplacement', 'montant' => $f['deplacement'] !== null && $f['deplacement'] > 0 ? $f['deplacement'] : null, 'inclus' => $f['deplacement'] === 0]) ?>
      <p class="t-petit t-secondaire">Indicatif. Le prix final se discute avec le client, avant l’intervention.</p>
    </section>

    <section class="profil__section" aria-label="Présentation">
      <?= composant('entete-section', ['libelle' => 'Présentation', 'href' => '/pro/profil/presentation']) ?>
      <?php if (!empty($f['presentation'])): ?><p class="t-base"><?= e($f['presentation']) ?></p>
      <?php else: ?><p class="t-petit t-secondaire">En une phrase, ce que vous faites de mieux : les clients la lisent en haut de votre fiche.</p><?php endif ?>
    </section>

    <section class="profil__section" aria-label="Photos de l’atelier">
      <?= composant('entete-section', ['libelle' => 'Photos de l’atelier', 'suite' => '· ' . $n . ' / ' . $max, 'href' => '/pro/profil/atelier']) ?>
      <?php if ($n > 0): ?><?= composant('photo-publique', ['photo' => $f['atelier'][0], 'alt' => 'Votre atelier', 'legende' => 'Son atelier · ' . ($f['quartier'] ?? '')]) ?><?php endif ?>
      <?php if ($n < $max || $autres !== []): ?>
        <div class="profil__emplacements">
          <?php foreach ($autres as $p): ?><?= composant('photo-publique', ['photo' => $p, 'alt' => 'Votre atelier', 'sizes' => '173px', 'classe' => 'photo-publique--petite']) ?><?php endforeach ?>
          <?php for ($i = $n; $i < $max; $i++): ?><?= composant('emplacement-photo', ['href' => '/pro/profil/atelier']) ?><?php endfor ?>
        </div>
      <?php endif ?>
      <p class="t-petit t-secondaire">Lumière du jour, atelier visible, pas de plaque lisible.</p>
    </section>

    <section class="profil__section" aria-label="Téléphone et WhatsApp">
      <?= composant('entete-section', ['libelle' => 'Téléphone et WhatsApp', 'href' => '/pro/profil/contacts']) ?>
      <?= composant('ligne-numero', ['icone' => 'telephone', 'libelle' => 'Téléphone', 'numero' => Format::telephone($f['telephone'])]) ?>
      <?= composant('ligne-numero', ['icone' => 'whatsapp', 'libelle' => 'WhatsApp', 'numero' => Format::telephone($f['whatsapp'] ?? $f['telephone'])]) ?>
      <p class="t-petit t-secondaire">Votre numéro WhatsApp peut être différent de votre téléphone.</p>
    </section>
  </div>

  <section class="profil__compte" aria-labelledby="compte-titre">
    <h2 class="t-etiquette t-secondaire" id="compte-titre">Mon compte</h2>
    <ul class="profil__lignes" role="list">
      <?php if ($f['mot_de_passe']): ?><?= composant('ligne-compte', ['icone' => 'cadenas', 'titre' => 'Mot de passe', 'sous' => 'Modifié le ' . $f['mdp_le'], 'href' => '/pro/profil/mot-de-passe']) ?><?php endif ?>
      <li class="profil__theme">
        <p class="t-base">Thème</p>
        <?= composant('segment-theme', []) ?>
        <p class="t-petit t-secondaire">Auto suit le réglage de votre téléphone. Dehors, au soleil, le clair se lit mieux.</p>
      </li>
      <?= composant('ligne-compte', ['icone' => 'retour', 'titre' => 'Se déconnecter', 'action' => '/deconnexion']) ?>
    </ul>
    <div class="profil__suppression">
      <a class="profil__danger t-gras" href="/pro/profil/supprimer"><?= icone('triangle', 20) ?><span>Supprimer mon compte</span></a>
      <p class="t-petit t-secondaire">Il faudra taper votre nom pour confirmer. Votre fiche et vos avis seront effacés.</p>
    </div>
  </section>
</div>
