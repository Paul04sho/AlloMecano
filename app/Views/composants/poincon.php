<?php
/**
 * Poinçon « Vérifié » (Figma 49:166) : écrou bitume, coche béton. Tailles 14, 18, 22, 48.
 * @var int $taille
 * @var ?string $titre texte lu par les lecteurs d'écran
 */
$taille ??= 18; ?>
<span class="poincon" style="width:<?= (int) $taille ?>px;height:<?= (int) $taille ?>px"<?= !empty($titre) ? ' role="img" aria-label="' . e($titre) . '"' : ' aria-hidden="true"' ?>>
  <svg viewBox="0 0 24 24"><path d="M12 1.5l9.1 5.25v10.5L12 22.5l-9.1-5.25V6.75z" fill="currentColor"/><path d="M7.4 12.3l3.1 3.1 6.1-6.3" fill="none" stroke="var(--coche)" stroke-width="2.4" stroke-linecap="square"/></svg>
</span>
