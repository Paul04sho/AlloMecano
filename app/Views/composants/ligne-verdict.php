<?php
/**
 * Verdict d'une pièce (Figma 112:930 · 112:976) : pictogramme de 40, nom en Titre/3 et, à droite, « Conforme » avec le poinçon.
 * Une pièce à reprendre montre sa photo floutée, la puce « À reprendre » et le motif de l'équipe cité (RG-DO4).
 * @var string $type     cni_recto | cni_verso | permis | diplome | portrait
 * @var string $libelle
 * @var string $etat     conforme | a_reprendre | envoyee
 * @var ?string $motif
 * @var ?string $vignette  photo floutée de la pièce (portrait seulement)
 */
use App\Core\Format;

$icones = ['cni_recto' => 'cni', 'cni_verso' => 'cni', 'permis' => 'permis', 'diplome' => 'diplome', 'portrait' => 'portrait'];
$motif ??= null;
$vignette ??= null;
?>
<li class="ligne-verdict ligne-verdict--<?= e(str_replace('_', '-', $etat)) ?>">
  <div class="ligne-verdict__ligne">
    <span class="ligne-verdict__pictogramme<?= $vignette !== null ? ' ligne-verdict__pictogramme--photo' : '' ?>" aria-hidden="true"><?= $vignette !== null ? '<img src="' . e($vignette) . '" width="40" height="50" alt="">' : icone($icones[$type] ?? 'fichier', 24) ?></span>
    <p class="t-titre-3"><?= e($libelle) ?></p>
    <?php if ($etat === 'conforme'): ?>
      <p class="ligne-verdict__etat t-etiquette"><?= composant('poincon', ['taille' => 14]) ?>Conforme</p>
    <?php elseif ($etat === 'a_reprendre'): ?><?= composant('statut', ['statut' => 'a_reprendre']) ?>
    <?php else: ?><p class="ligne-verdict__etat t-etiquette t-secondaire">Envoyée</p><?php endif ?>
  </div>
  <?php if ($etat === 'a_reprendre' && !empty($motif)): ?>
    <div class="ligne-verdict__motif"><p class="t-etiquette t-secondaire">Motif de l’équipe</p><p class="t-base">«<?= Format::INSECABLE ?><?= e($motif) ?><?= Format::INSECABLE ?>»</p></div>
  <?php endif ?>
</li>
