<?php
/**
 * Ligne de signalement (Figma 179:642 · 179:654 · 179:666) : gravité en Mono, motif en Corps/Gras, mécano et quartier, ancienneté et numéro.
 * Marque à gauche : voyant rouge pour un critique, triangle pour un important, rien pour un normal. La ligne ouverte a un filet bitume de 4 px.
 * @var array{public_id: string, numero: string, gravite: string, titre: string, mecano: string, quartier: string, quand: string} $signalement
 * @var string $href
 * @var bool $actif   la ligne dont le détail est ouvert
 */
use App\Services\SignalementAdminService;

$s = $signalement;
$gravite = $s['gravite'];
$libelle = SignalementAdminService::GRAVITE_LIBELLES[$gravite] ?? 'Normal';
?>
<li class="ligne-signalement ligne-signalement--<?= e($gravite) ?><?= !empty($actif) ? ' est-active' : '' ?>">
  <a href="<?= e($href) ?>"<?= !empty($actif) ? ' aria-current="true"' : '' ?>>
    <span class="ligne-signalement__marque" aria-hidden="true">
      <?php if ($gravite === 'critique'): ?><?= composant('voyant', ['etat' => 'erreur']) ?><?php elseif ($gravite === 'important'): ?><?= icone('triangle', 20) ?><?php endif ?>
    </span>
    <span class="ligne-signalement__texte">
      <span class="ligne-signalement__gravite t-etiquette"><?= e($libelle) ?></span>
      <span class="t-gras"><?= e($s['titre']) ?></span>
      <span class="t-petit t-secondaire"><?= e($s['mecano'] . ($s['quartier'] !== '' ? ' · ' . $s['quartier'] : '')) ?></span>
      <span class="t-etiquette t-secondaire"><?= e($s['quand'] . ' · ' . $s['numero']) ?></span>
    </span>
    <?= icone('chevron', 20) ?>
  </a>
</li>
