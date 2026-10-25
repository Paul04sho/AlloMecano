<?php
/**
 * M2b · Compte suspendu (Figma 113:945) : bloc bitume au filet erreur, la durée, le motif cité, puis « Et ensuite ? » et le support WhatsApp (RG-D6).
 * Tant que la suspension dure, aucune disponibilité (RG-D6) : elle se lève toute seule à l'échéance, ou par l'équipe.
 * @var array<string, mixed> $profil
 * @var ?array{depuis: \DateTimeImmutable, fin: ?\DateTimeImmutable, motif: string} $suspension
 * @var string $whatsapp
 */
use App\Services\TableauMecanoService;

$s = $suspension;
$duree = $s === null ? 'En pause.' : ($s['fin'] === null ? 'En pause jusqu’à nouvel ordre.' : 'En pause pour ' . TableauMecanoService::dureeSuspension($s['depuis'], $s['fin']) . '.');
$suite = $s !== null && $s['fin'] !== null
    ? 'Jusqu’au ' . TableauMecanoService::jourMois($s['fin']) . ', vous n’apparaissez plus dans les recherches. Votre compte se réactive tout seul ensuite.'
    : 'Vous n’apparaissez plus dans les recherches. Une personne de l’équipe vous écrira.';
?>
<div class="tableau tableau--suspendu">
  <?= composant('en-tete-pro', ['prenom' => $profil['prenom'], 'ligne' => $profil['en_tete'], 'avatar' => $avatar, 'verifie' => false]) ?>
  <?= composant('bloc-suspension', ['depuis' => $s !== null ? TableauMecanoService::jourMois($s['depuis']) : '', 'duree' => $duree, 'motif' => $s['motif'] ?? 'Le motif vous a été communiqué par l’équipe.', 'suite' => $suite]) ?>
  <section class="tableau__ensuite" aria-labelledby="ensuite-titre">
    <div class="tableau__entete">
      <p class="t-etiquette t-secondaire">Et ensuite&#8239;?</p>
      <h2 class="t-titre-2" id="ensuite-titre">Expliquez-nous ce qui s’est passé.</h2>
    </div>
    <p class="t-base t-secondaire">Une personne de l’équipe lit chaque message et vous répond sous 24&#8239;h, sur WhatsApp.</p>
    <a class="lien-icone t-gras tableau__lien" href="/cgu"><span>Lire les règles de la communauté</span><?= icone('chevron', 20) ?></a>
  </section>
</div>
<?= composant('socle', ['info' => 'Support WhatsApp · réponse sous 24 h', 'actions' => composant('bouton', ['libelle' => 'Contacter l’équipe', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'icone' => 'whatsapp', 'href' => $whatsapp, 'attrs' => ['target' => '_blank', 'rel' => 'noopener']])]) ?>
