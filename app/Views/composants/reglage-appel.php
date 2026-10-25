<?php
/**
 * Réglage de « Activer les appels » (Figma 272:1813 · 272:1820 · 272:1835) : le nœud, le titre en Corps/Gras, une phrase, et l'action du réglage en cours.
 * Fait : « Fait · icône ajoutée », sans bouton. En cours : titre en encre et bouton contour de 48. À faire : titre en gris, bouton grisé.
 * Le JavaScript passe un réglage d'un état à l'autre selon ce que le téléphone permet (data-reglage).
 * @var string $cle      installer | notifications | essai
 * @var string $etat     done | now | todo
 * @var string $titre
 * @var string $texte    phrase tant que le réglage n'est pas fait
 * @var string $fait     phrase quand il est fait
 * @var string $libelle  libellé du bouton
 * @var string $icone    icône du bouton
 */
?>
<li class="reglage-appel reglage-appel--<?= e($etat) ?>" data-reglage="<?= e($cle) ?>" data-etat="<?= e($etat) ?>" data-texte="<?= e($texte) ?>" data-fait="<?= e($fait) ?>">
  <span class="reglage-appel__noeud" data-noeud><?= composant('noeud-reglage', ['etat' => $etat]) ?></span>
  <div class="reglage-appel__corps">
    <p class="reglage-appel__titre t-gras"><?= e($titre) ?></p>
    <p class="t-petit t-secondaire" data-phrase><?= e($etat === 'done' ? $fait : $texte) ?></p>
    <?= composant('bouton', ['libelle' => $libelle, 'type' => 'contour', 'hauteur' => 48, 'plein' => true, 'icone' => $icone, 'desactive' => $etat === 'todo', 'attrs' => ['data-action' => $cle] + ($etat === 'done' ? ['hidden' => 'hidden'] : [])]) ?>
  </div>
</li>
