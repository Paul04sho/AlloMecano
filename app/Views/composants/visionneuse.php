<?php
/**
 * Visionneuse d'une pièce (Figma 168:224 · 168:251) : titre et complément, panneau 4:5 avec la pièce filigranée par le serveur (RG-P6),
 * puis zoom −/+, pivoter de 90° et luminosité. Aucun téléchargement : pas de lien vers le fichier, pas de menu contextuel.
 * @var string $nom        « portrait » | « cni »… clé lue par le JS
 * @var string $titre      « PHOTO DE PROFIL »
 * @var ?string $complement « REÇUE LE 20/10 · 10:12 »
 * @var ?string $segment    HTML du segment Recto | Verso (visionneuse de la pièce d'identité)
 * @var string $src        adresse de la pièce (route filigranée)
 * @var string $alt
 */
?>
<figure class="visionneuse" data-visionneuse="<?= e($nom) ?>">
  <figcaption class="visionneuse__titre t-etiquette">
    <span data-visionneuse-titre><?= e($titre) ?></span>
    <?php if (!empty($segment)): ?><?= $segment ?><?php elseif (!empty($complement)): ?><span class="t-secondaire"><?= e($complement) ?></span><?php endif ?>
  </figcaption>
  <div class="visionneuse__panneau" data-panneau>
    <img src="<?= e($src) ?>" alt="<?= e($alt) ?>" draggable="false" data-image>
  </div>
  <div class="visionneuse__outils" role="group" aria-label="Outils de la visionneuse : <?= e($titre) ?>">
    <button type="button" class="visionneuse__outil" data-zoom="-1" aria-label="Réduire"><?= icone('moins', 20) ?></button>
    <output class="visionneuse__zoom t-donnee" data-zoom-valeur>100 %</output>
    <button type="button" class="visionneuse__outil" data-zoom="1" aria-label="Agrandir"><?= icone('plus', 20) ?></button>
    <button type="button" class="visionneuse__outil" data-pivoter>Pivoter 90°</button>
    <button type="button" class="visionneuse__outil" data-luminosite>Luminosité</button>
  </div>
</figure>
