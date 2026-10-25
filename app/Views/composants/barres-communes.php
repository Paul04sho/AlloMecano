<?php
/**
 * « Disponibles par commune » (Figma 164:238) : une barre par commune, longueur proportionnelle à la plus fournie, nombre en Mono à droite.
 * « aucun pour l'instant » quand une commune ouverte n'a personne ; « BIENTÔT · PAS ENCORE COUVERTE » pour une commune pas encore ouverte.
 * @var list<array{nom: string, etat: string, nombre: int}> $communes  voir AccueilService::communes()
 */
$max = max(1, ...array_map(static fn (array $c): int => $c['nombre'], $communes ?: [['nombre' => 1]]));
?>
<ul class="barres-communes" role="list">
  <?php foreach ($communes as $c): ?>
    <li class="barres-communes__ligne<?= $c['etat'] === 'disponibles' ? '' : ' barres-communes__ligne--vide' ?>">
      <span class="barres-communes__nom"><?= e($c['nom']) ?></span>
      <span class="barres-communes__piste">
        <?php if ($c['etat'] === 'disponibles'): ?><i class="barres-communes__barre" style="width:<?= round(100 * $c['nombre'] / $max, 1) ?>%"></i>
        <?php elseif ($c['etat'] === 'aucun'): ?><span>aucun pour l’instant</span>
        <?php else: ?><span class="t-etiquette">Bientôt · pas encore couverte</span><?php endif ?>
      </span>
      <span class="barres-communes__nombre t-etiquette"><?= $c['etat'] === 'bientot' ? '' : (int) $c['nombre'] ?></span>
    </li>
  <?php endforeach ?>
</ul>
