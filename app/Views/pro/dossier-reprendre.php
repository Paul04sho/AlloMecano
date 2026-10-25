<?php
/**
 * M2b · Pièce à reprendre (Figma 112:903) : le motif de l'équipe cité (RG-DO4), le verdict de chaque pièce, et un socle qui rouvre l'appareil photo.
 * Le dossier repart tout seul chez l'équipe quand il ne reste plus rien à reprendre.
 * @var array<string, mixed> $profil
 * @var string $numero
 * @var array{pieces: list<array{type: string, etat: string, motif: ?string}>, a_reprendre: list<string>, verifiees_le: ?\DateTimeImmutable} $verdicts
 * @var ?string $portrait   la photo de vous (floutée), quand elle est à reprendre
 */
use App\Core\Format;
use App\Services\TableauMecanoService;

$libelles = ['cni_recto' => 'CNI recto', 'cni_verso' => 'CNI verso', 'permis' => 'Permis de conduire', 'diplome' => 'Diplôme ou attestation', 'portrait' => 'Photo de vous'];
$boutons = ['portrait' => 'Reprendre ma photo', 'cni_recto' => 'Reprendre la CNI recto', 'cni_verso' => 'Reprendre la CNI verso', 'permis' => 'Reprendre le permis', 'diplome' => 'Reprendre le diplôme'];
$reste = $verdicts['a_reprendre'];
$n = count($reste);
$gardees = count(array_filter($verdicts['pieces'], static fn (array $p): bool => $p['etat'] === 'conforme'));
$lettres = [1 => 'une', 'deux', 'trois', 'quatre', 'cinq'];
$premiere = $reste[0] ?? 'portrait';
?>
<div class="tableau tableau--reprendre">
  <section class="reprendre" aria-labelledby="reprendre-titre">
    <p class="reprendre__rubrique t-etiquette"><?= composant('voyant', ['etat' => 'erreur']) ?>Dossier à reprendre · N° <?= e($numero) ?></p>
    <h1 class="t-titre-1" id="reprendre-titre"><?= $n ?> pièce<?= $n > 1 ? 's' : '' ?> à reprendre</h1>
    <p class="t-base">Ça arrive souvent. Corrigez-<?= $n > 1 ? 'les' : 'la' ?> et renvoyez&#8239;: votre dossier repasse en priorité.</p>
  </section>

  <section class="reprendre__pieces" aria-labelledby="pieces-titre">
    <p class="t-etiquette t-secondaire" id="pieces-titre">Vos pièces<?= $verdicts['verifiees_le'] !== null ? ' · vérifiées le ' . e(TableauMecanoService::jourMois($verdicts['verifiees_le'])) : '' ?></p>
    <ul class="reprendre__verdicts" role="list">
      <?php foreach ($verdicts['pieces'] as $p): ?>
        <?= composant('ligne-verdict', ['type' => $p['type'], 'libelle' => $libelles[$p['type']], 'etat' => $p['etat'] === 'manquante' ? 'a_reprendre' : $p['etat'], 'motif' => $p['motif'],
            'vignette' => $p['type'] === 'portrait' && $p['etat'] === 'a_reprendre' ? $portrait : null]) ?>
      <?php endforeach ?>
    </ul>
    <?php if ($gardees > 0): ?>
      <p class="t-petit t-secondaire"><?= $gardees === 1 ? 'Votre autre pièce est gardée' : 'Vos ' . $lettres[$gardees] . ' autres pièces sont gardées' ?>&#8239;: vous n’avez rien d’autre à renvoyer.</p>
    <?php endif ?>
  </section>
</div>
<?= composant('socle', ['info' => 'L’appareil photo s’ouvre avec le guide', 'actions' => composant('reprise-piece', ['type' => $premiere, 'libelle' => $boutons[$premiere], 'icone' => 'camera', 'action' => '/pro/inscription/pieces/' . $premiere])]) ?>
