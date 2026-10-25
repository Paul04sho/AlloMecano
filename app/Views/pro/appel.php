<?php
/**
 * M5 · Appel entrant (Figma 271:1480), en appel (271:1644) puis « Vous y allez ? » (272:1744).
 * Une seule page : le serveur rend l'état de l'appel ; le JavaScript mène le WebRTC (js/pages/pro-appel.js) et passe d'une vue à l'autre sans recharger.
 * Il sonne 30 s au plus (RG-A5). « Décrocher » envoie la réponse WebRTC (RG-A6). Une fois raccroché, « J'y vais » le passe en intervention (RG-I2, RG-D4).
 * @var array<string, mixed> $d       l'appel vu du mécano (AppelService::detailMecano)
 * @var string $phase                 entrant | suite
 * @var ?array{valeur: string, unite: string, texte: string} $km
 * @var string $lieu                  « Riviera 2, près de la pharmacie des Jardins »
 * @var int $sonnerie_s
 * @var int $echec_s
 * @var ?string $fin                  heure de fin de l'appel
 * @var ?string $duree                « 2 min 10 »
 * @var bool $dev                     poste de développement : micro silencieux possible (?silence=1)
 * @var ?string $itineraire           lien vers l'application de cartes
 */
use App\Core\Format;
use App\Services\Parametres;

$sombre = $phase === 'entrant';
$prenom = $d['prenom'];
$nom = $prenom ?? 'Un client';
$legende = ($prenom ?? 'Client') . ($km !== null ? ' · ' . $km['texte'] : '');
$autoH = Parametres::int('intervention.fin_auto_h', 2);
?>
<div class="appel-pro <?= $sombre ? 'appel-pro--sombre sur-sombre' : 'appel-pro--clair' ?>" data-appel-pro data-contact="<?= e($d['contact']) ?>" data-phase="<?= e($phase) ?>" data-prenom="<?= e($prenom ?? '') ?>"
     data-sonnerie="<?= $sonnerie_s ?>" data-echec="<?= $echec_s ?>" data-depuis="<?= (int) $d['depuis_s'] ?>"<?= $dev ? ' data-dev' : '' ?>>

  <?php /* ---------- M5 · entrant (271:1480) et en appel (271:1644) : même fond sombre, mêmes informations ---------- */ ?>
  <div class="appel-pro__vue appel-pro__vue--appel" data-vue="appel"<?= $sombre ? '' : ' hidden' ?>>
    <header class="appel-pro__entete">
      <p class="appel-pro__rubrique t-etiquette" data-phase-sonne><?= composant('voyant', ['etat' => 'recherche']) ?><span>Appel entrant · allomécano</span></p>
      <p class="appel-pro__ligne" data-phase-ligne hidden><span class="appel-pro__en-ligne t-etiquette">En ligne</span><span class="appel__chrono" data-chrono>00:00</span></p>
      <p class="appel-pro__titre">
        <span class="t-titre-1" data-titre><span data-phase-sonne>Un client à</span><span data-phase-ligne hidden><?= e($prenom ?? 'Le client') ?>, à</span></span>
        <?php if ($km !== null): ?><span class="appel-pro__borne"><span class="t-borne-xl"><?= e($km['valeur']) ?></span><span class="t-etiquette"><?= e(mb_strtoupper($km['unite'], 'UTF-8')) ?></span></span><?php endif ?>
      </p>
      <?php if ($lieu !== ''): ?><p class="t-lead"><?= e($lieu) ?></p><?php endif ?>
      <div class="appel-pro__panne">
        <?php if ($d['code_panne'] !== null): ?><?= composant('voyant-de-bord', ['code' => (string) $d['code_panne'], 'allume' => true]) ?><?php endif ?>
        <div><p class="t-gras"><?= e($d['resume']) ?></p><?php if (!empty($d['precisions'])): ?><p class="t-petit appel-pro__precisions">«&#8239;<?= e($d['precisions']) ?>&#8239;»</p><?php endif ?></div>
      </div>
    </header>

    <?php if ($d['position'] !== null): ?>
      <?= composant('carte-client', ['lat' => $d['position']['lat'], 'lon' => $d['position']['lon'], 'precision' => $d['precision_m'], 'legende' => $legende, 'libelle' => 'Position approximative du client']) ?>
    <?php else: ?>
      <p class="appel-pro__sans-carte t-petit">Le client n’a pas partagé sa position.</p>
    <?php endif ?>

    <div class="appel-pro__bas" data-phase-sonne>
      <p class="t-etiquette appel-pro__attente" aria-live="polite">Ça sonne depuis <span data-chrono>0:00</span> · <?= $sonnerie_s ?>&nbsp;s au maximum</p>
      <?= composant('bouton', ['libelle' => 'Refuser', 'icone' => 'fermer', 'type' => 'contour-beton', 'hauteur' => 56, 'plein' => true, 'attrs' => ['data-refuser' => '']]) ?>
      <?= composant('bouton', ['libelle' => 'Décrocher', 'icone' => 'telephone', 'type' => 'principal', 'hauteur' => 64, 'plein' => true, 'chargement' => 'Connexion…', 'attrs' => ['data-decrocher' => '']]) ?>
    </div>
    <div class="appel-pro__bas" data-phase-ligne hidden>
      <div class="appel__commandes appel-pro__commandes">
        <?= composant('commande-appel', ['icone' => 'micro', 'libelle' => 'Micro', 'attrs' => ['data-commande' => 'micro']]) ?>
        <?= composant('commande-appel', ['icone' => 'haut-parleur', 'libelle' => 'Haut-parleur', 'attrs' => ['data-commande' => 'haut-parleur']]) ?>
        <?php if ($itineraire !== null): ?><?= composant('commande-appel', ['icone' => 'itineraire', 'libelle' => 'Itinéraire', 'href' => $itineraire]) ?><?php endif ?>
      </div>
      <?= composant('bouton', ['libelle' => 'Raccrocher', 'icone' => 'raccrocher', 'type' => 'danger', 'hauteur' => 64, 'plein' => true, 'attrs' => ['data-raccrocher' => '']]) ?>
    </div>
    <audio data-audio autoplay playsinline></audio>
  </div>

  <?php /* ---------- M5 · Vous y allez ? (272:1744) ---------- */ ?>
  <div class="appel-pro__vue appel-pro__vue--suite" data-vue="suite"<?= $sombre ? ' hidden' : '' ?>>
    <header class="appel-pro__barre">
      <a class="activer__retour" href="/pro" aria-label="Retour à mon espace"><?= icone('retour', 24) ?></a>
      <p class="t-etiquette t-secondaire" data-resume-fin>Appel terminé<?= $duree !== null ? ' · ' . e($duree) : '' ?><?= $fin !== null ? ' · ' . e($fin) : '' ?></p>
    </header>
    <div class="appel-pro__suite">
      <h1 class="t-display-l" id="suite-titre">Vous y allez&#8239;?</h1>
      <p class="t-base t-secondaire"><?= e($prenom ?? 'Le client') ?> attend votre réponse&#8239;: elle s’affiche sur son écran tout de suite.</p>
      <dl class="appel-pro__intervention">
        <?php if ($lieu !== ''): ?><?= composant('ligne-intervention', ['libelle' => 'Lieu', 'valeur' => $lieu]) ?><?php endif ?>
        <?php if ($km !== null): ?><?= composant('ligne-intervention', ['libelle' => 'Distance', 'valeur' => $km['texte'] . ' à vol d’oiseau']) ?><?php endif ?>
        <?= composant('ligne-intervention', ['libelle' => 'Panne', 'valeur' => $d['resume']]) ?>
        <?php if (!empty($d['precisions'])): ?><?= composant('ligne-intervention', ['libelle' => 'Ce que le client a dit', 'valeur' => '« ' . $d['precisions'] . ' »']) ?><?php endif ?>
      </dl>
      <?php if ($itineraire !== null): ?><a class="appel-pro__itineraire t-label" href="<?= e($itineraire) ?>" target="_blank" rel="noopener"><?= icone('itineraire', 20) ?><span>Ouvrir l’itinéraire</span></a><?php endif ?>
      <?= composant('note', ['type' => 'info', 'message' => 'Avec « J’y vais », vous passez « en intervention » : les autres clients ne vous voient plus. Vous redevenez disponible d’un geste, ou tout seul après ' . $autoH . '&nbsp;h.']) ?>
    </div>
    <?= composant('socle', ['info' => 'Le client voit votre réponse aussitôt', 'actions' =>
        composant('bouton', ['libelle' => 'J’y vais', 'icone' => 'coche', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'chargement' => 'Un instant…', 'attrs' => ['data-issue' => 'j_y_vais']])
        . composant('bouton', ['libelle' => 'Pas pour moi', 'type' => 'contour-beton', 'hauteur' => 56, 'attrs' => ['data-issue' => 'pas_pour_moi']]),
    ]) ?>
  </div>
</div>
