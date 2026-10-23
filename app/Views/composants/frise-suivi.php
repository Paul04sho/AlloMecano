<?php
/**
 * Fil de suivi (Figma 90:1681) : des étapes reliées par un trait vertical en tirets ; nœud plein = fait, nœud avec point = en cours, nœud vide = à venir.
 * @var list<array{etat: string, titre: string, detail: string}> $etapes  etat : fait | en_cours | a_venir
 */
$noeuds = [
    'fait' => '<path d="M7.69 0.09C7.9 -0.03 8.1 -0.03 8.31 0.09L14.69 4.92C14.9 5.05 15 5.23 15 5.47V13.53C15 13.77 14.9 13.95 14.69 14.08L8.31 18.91C8.1 19.03 7.9 19.03 7.69 18.91L1.31 14.08C1.1 13.95 1 13.77 1 13.53V5.47C1 5.23 1.1 5.05 1.31 4.92L7.69 0.09Z" fill="currentColor"/>',
    'en_cours' => '<path d="M7.69 0.09C7.9 -0.03 8.1 -0.03 8.31 0.09L14.69 4.92C14.9 5.05 15 5.23 15 5.47V13.53C15 13.77 14.9 13.95 14.69 14.08L8.31 18.91C8.1 19.03 7.9 19.03 7.69 18.91L1.31 14.08C1.1 13.95 1 13.77 1 13.53V5.47C1 5.23 1.1 5.05 1.31 4.92L7.69 0.09Z" fill="currentColor"/><circle cx="8" cy="9.5" r="3" fill="var(--surface)"/>',
    'a_venir' => '<path d="M7.69 1.09C7.9 0.97 8.1 0.97 8.31 1.09L13.69 4.2C13.9 4.32 14 4.5 14 4.74V14.26C14 14.5 13.9 14.68 13.69 14.8L8.31 17.91C8.1 18.03 7.9 18.03 7.69 17.91L2.31 14.8C2.1 14.68 2 14.5 2 14.26V4.74C2 4.5 2.1 4.32 2.31 4.2L7.69 1.09Z" fill="none" stroke="currentColor" stroke-width="2"/>',
];
$dernier = count($etapes) - 1;
?>
<ol class="frise-suivi" role="list">
  <?php foreach ($etapes as $i => $etape): ?>
    <li class="frise-suivi__etape frise-suivi__etape--<?= e(str_replace('_', '-', $etape['etat'])) ?>"<?= $etape['etat'] === 'en_cours' ? ' aria-current="step"' : '' ?>>
      <span class="frise-suivi__repere" aria-hidden="true">
        <svg class="frise-suivi__noeud" width="16" height="19" viewBox="0 0 16 19" focusable="false"><?= $noeuds[$etape['etat']] ?></svg>
        <?php if ($i < $dernier): ?><span class="frise-suivi__trait"></span><?php endif ?>
      </span>
      <span class="frise-suivi__texte">
        <span class="frise-suivi__titre t-gras"><?= e($etape['titre']) ?></span>
        <span class="frise-suivi__detail t-donnee"><?= e($etape['detail']) ?></span>
      </span>
    </li>
  <?php endforeach ?>
</ol>
