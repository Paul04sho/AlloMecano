<?php
/**
 * M2a · Dossier en vérification (Figma 110:867) : l'écrou des pièces, le fil de suivi (envoyé, en vérification, validé), ce que l'équipe vérifie,
 * l'interrupteur verrouillé (RG-D1), la photo facultative de l'atelier (RG-DO2) et deux lignes d'action. La réponse est annoncée sous 48 h (RG-DO3).
 * @var array<string, mixed> $profil
 * @var ?array{src: string, cadrage?: string} $avatar
 * @var array{numero: string, envoye_le: \DateTimeImmutable, ouvert_le: ?\DateTimeImmutable, reponse_avant: \DateTimeImmutable, pieces: int} $suivi
 * @var array<string, string> $etats
 */
use App\Core\Format;
use App\Services\TableauMecanoService;

$quand = static fn (\DateTimeImmutable $d): string => TableauMecanoService::jourMois($d) . ', ' . Format::heure($d);
$atelier = ($etats['atelier'] ?? '') === 'envoyee';
?>
<div class="tableau tableau--dossier">
  <?= composant('en-tete-pro', ['prenom' => $profil['prenom'], 'ligne' => $profil['en_tete'], 'avatar' => $avatar, 'verifie' => false]) ?>

  <section class="dossier-statut" aria-labelledby="dossier-titre">
    <p class="dossier-statut__rubrique t-etiquette"><span class="dossier-statut__bande" aria-hidden="true"></span>Dossier en vérification · N° <?= e($suivi['numero']) ?></p>
    <?= composant('ecrou-dossier', ['pieces' => $etats, 'taille' => 120]) ?>
    <div class="dossier-statut__texte">
      <h1 class="t-titre-1" id="dossier-titre">Votre dossier est en vérification.</h1>
      <p class="t-base t-secondaire">Rien à faire de votre côté. Vous recevrez la réponse par SMS et par e-mail.</p>
    </div>
  </section>

  <section class="tableau__suivi" aria-labelledby="suivi-titre">
    <p class="t-etiquette t-secondaire" id="suivi-titre">Suivi de votre dossier</p>
    <ol class="tableau__etapes" role="list">
      <?= composant('etape-suivi', ['etat' => 'fait', 'date' => $quand($suivi['envoye_le']), 'titre' => 'Dossier envoyé',
          'texte' => $suivi['pieces'] . ' pièces reçues. ' . ($atelier ? 'La photo de votre atelier est jointe.' : 'La photo de l’atelier est facultative.')]) ?>
      <?= composant('etape-suivi', ['etat' => 'en_cours', 'date' => $quand($suivi['ouvert_le'] ?? $suivi['envoye_le']) . ' · en cours', 'titre' => 'En vérification',
          'texte' => 'Une personne compare votre photo à votre pièce d’identité.', 'echeance' => 'Réponse avant le ' . $quand($suivi['reponse_avant'])]) ?>
      <?= composant('etape-suivi', ['etat' => 'a_venir', 'date' => 'À venir', 'titre' => 'Validé',
          'texte' => 'Vous pourrez vous rendre disponible et apparaître aux clients.', 'derniere' => true]) ?>
    </ol>
  </section>

  <section class="tableau__verifions" aria-labelledby="verifions-titre">
    <?= composant('photo', ['aProduire' => ['titre' => 'Photo à produire · IMG-08', 'texte' => 'Mains qui serrent un écrou de roue'], 'alt' => 'Des mains serrent un écrou de roue.', 'classe' => 'tableau__photo']) ?>
    <p class="t-etiquette t-secondaire">Ce que nous vérifions</p>
    <h2 class="t-titre-2" id="verifions-titre">Chaque pièce est regardée par une personne.</h2>
    <p class="t-base t-secondaire">Votre visage, votre pièce d’identité, votre permis et votre diplôme. Rien n’est validé par une machine seule.</p>
  </section>

  <?= composant('interrupteur-dispo', ['etat' => 'verrouille']) ?>

  <div class="tableau__action">
    <?= composant('reprise-piece', ['type' => 'atelier', 'libelle' => $atelier ? 'Remplacer la photo de mon atelier' : 'Ajouter la photo de mon atelier', 'icone' => 'camera', 'action' => '/pro/inscription/pieces/atelier']) ?>
    <p class="t-petit t-secondaire">Facultatif. Les clients la voient sur votre fiche.</p>
  </div>

  <ul class="tableau__actions" role="list">
    <?= composant('ligne-action', ['libelle' => 'Voir ma fiche comme un client', 'icone' => 'oeil', 'href' => '/pro/apercu']) ?>
    <?= composant('ligne-action', ['libelle' => 'Lire les règles de la communauté', 'icone' => 'fichier', 'href' => '/cgu']) ?>
  </ul>
</div>
