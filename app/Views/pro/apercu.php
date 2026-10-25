<?php
/**
 * Aperçu de sa ligne avant validation (pas dessiné dans Figma) : ce que les clients verront, avec l'étiquette « En attente » à la place du poinçon (RG-DO8).
 * Après validation, « Voir ma fiche comme un client » ouvre la vraie fiche.
 * @var array<string, mixed> $profil
 * @var ?array{src: string, cadrage?: string} $avatar
 * @var ?array<string, mixed> $b           le brouillon : spécialités, quartier, présentation, tarifs
 * @var array<string, string> $specialites code → libellé
 */
use App\Core\Format;

$specs = array_map(static fn (string $c): string => $specialites[$c] ?? $c, $b['specialites'] ?? []);
?>
<div class="tableau tableau--dossier">
  <a class="lien-icone t-gras tableau__lien" href="/pro"><?= icone('retour', 20) ?><span>Mon espace</span></a>
  <section class="apercu" aria-labelledby="apercu-titre">
    <p class="t-etiquette t-secondaire">Ce que les clients verront</p>
    <div class="en-tete-pro">
      <span class="en-tete-pro__avatar" aria-hidden="true"><?php if ($avatar): ?><img src="<?= e($avatar['src']) ?>" width="40" height="40" alt="" <?= !empty($avatar['cadrage']) ? 'style="object-position:' . e($avatar['cadrage']) . '"' : '' ?>><?php else: ?><?= icone('portrait', 24) ?><?php endif ?></span>
      <div class="en-tete-pro__texte"><p class="t-titre-2"><?= e($profil['prenom'] . ' ' . $profil['nom']) ?></p><p class="t-petit t-secondaire"><?= e($profil['en_tete']) ?></p></div>
    </div>
    <?= composant('etiquette-verifie', ['etat' => 'attente']) ?>
    <h1 class="t-titre-2" id="apercu-titre">Votre fiche apparaîtra ici dès la validation.</h1>
    <?php if ($specs !== []): ?><p class="t-base"><?= e(implode(', ', $specs)) ?></p><?php endif ?>
    <?php if (!empty($b['presentation'])): ?><p class="t-base t-secondaire">«&#8239;<?= e($b['presentation']) ?>&#8239;»</p><?php endif ?>
    <?php if (($b['tarif_diagnostic'] ?? null) !== null): ?><p class="t-donnee t-secondaire">Diagnostic <?= e(Format::fcfa((int) $b['tarif_diagnostic'])) ?></p><?php endif ?>
  </section>
</div>
