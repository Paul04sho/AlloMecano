<?php
/**
 * Ligne de pièce (Figma 168:290) : vignette, nom, état (affichée, consultée à telle heure, à reprendre), et le choix « Conforme | À reprendre » (segment de 36).
 * Filet gauche de 4 : encre pour la pièce affichée dans la visionneuse, erreur pour une pièce à reprendre.
 * @var string $type
 * @var ?array<string, mixed> $piece   null si la pièce n'a pas été fournie
 * @var string $mecano     public_id du mécano (adresse de la vignette du portrait)
 * @var ?string $decision  conforme | a_reprendre | null
 * @var string $etat       texte Mono sous le nom (« Affichée », « Consultée · 17:04 »…)
 * @var bool $affichee     la pièce est dans la visionneuse
 * @var bool $desactive    le dossier n'est plus en attente : lecture seule
 */
use App\Services\ExamenDossierService;

$nom = ExamenDossierService::NOMS[$type][0];
$decision ??= null;
$classes = 'ligne-piece' . (!empty($affichee) ? ' ligne-piece--affichee' : '') . ($decision === 'a_reprendre' ? ' ligne-piece--a-reprendre' : '') . ($piece === null ? ' ligne-piece--absente' : '');
?>
<li class="<?= $classes ?>" data-piece="<?= e($type) ?>">
  <?php if ($piece === null): ?>
    <span class="ligne-piece__vignette ligne-piece__vignette--absente" aria-hidden="true"><?= icone('atelier', 20) ?></span>
    <div class="ligne-piece__texte"><p class="t-label"><?= e($nom) ?></p><p class="ligne-piece__etat t-etiquette t-secondaire">Facultatif · non fourni</p></div>
  <?php else: ?>
    <button type="button" class="ligne-piece__ouvrir" data-afficher="<?= e($type) ?>" data-titre="<?= e(mb_strtoupper(ExamenDossierService::NOMS[$type][3], 'UTF-8')) ?>" aria-label="Afficher <?= e(mb_strtolower($nom, 'UTF-8')) ?> dans la visionneuse">
      <span class="ligne-piece__vignette" aria-hidden="true">
        <?php if ($type === 'cni_recto'): ?><?= composant('specimen-cni', ['classe' => 'ligne-piece__cni']) ?>
        <?php elseif ($type === 'cni_verso'): ?><i></i><i></i><i></i>
        <?php elseif ($type === 'portrait'): ?><img src="/admin/dossiers/<?= e($mecano) ?>/portrait" width="29" height="36" alt="" loading="lazy">
        <?php else: ?><?= icone($type === 'permis' ? 'permis' : ($type === 'diplome' ? 'diplome' : 'atelier'), 20) ?><?php endif ?>
      </span>
      <span class="ligne-piece__texte"><span class="t-label"><?= e($nom) ?></span>
        <span class="ligne-piece__etat t-etiquette<?= $decision === 'a_reprendre' ? ' ligne-piece__etat--erreur' : ' t-secondaire' ?>" data-etat><?= e($decision === 'a_reprendre' ? 'À reprendre' : $etat) ?></span></span>
    </button>
    <div class="segment segment--plein segment--36 ligne-piece__choix" role="radiogroup" aria-label="Décision pour <?= e(mb_strtolower($nom, 'UTF-8')) ?>">
      <label><input type="radio" name="piece[<?= e($type) ?>]" value="conforme"<?= $decision === 'conforme' ? ' checked' : '' ?><?= !empty($desactive) ? ' disabled' : '' ?>><span>Conforme</span></label>
      <label><input type="radio" name="piece[<?= e($type) ?>]" value="a_reprendre"<?= $decision === 'a_reprendre' ? ' checked' : '' ?><?= !empty($desactive) ? ' disabled' : '' ?>><span>À reprendre</span></label>
    </div>
  <?php endif ?>
</li>
