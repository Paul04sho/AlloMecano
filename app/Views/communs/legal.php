<?php
/**
 * X5 · CGU (Figma 143:372) et Confidentialité (144:424) : un titre, la date de mise à jour, un bloc d'essentiel ou un tableau, les articles, le PDF.
 * @var string $quoi   cgu | confidentialite
 * @var array<string, mixed> $doc  voir TextesLegaux
 * @var int $poidsPdf  taille du PDF en octets
 */
use App\Core\Format;
use App\Services\TextesLegaux;

$typo = fn (string $t): string => e(Format::typo($t));
$maj = 'Mis à jour le ' . TextesLegaux::MAJ;
?>
<article class="legal conteneur">
  <header class="legal__entete">
    <p class="t-etiquette t-secondaire"><?= e($doc['rubrique']) ?></p>
    <h1 class="t-titre-1"><?= $typo($doc['titre']) ?></h1>
    <p class="t-etiquette t-secondaire"><?= e($maj) ?></p>
  </header>

  <?php if ($quoi === 'cgu'): ?>
    <section class="legal__essentiel sur-sombre" aria-labelledby="essentiel">
      <h2 class="t-titre-2" id="essentiel">L’essentiel en 5 lignes</h2>
      <ol role="list">
        <?php foreach ($doc['essentiel'] as $i => $ligne): ?><li><span class="t-borne-l" aria-hidden="true"><?= $i + 1 ?></span><span class="t-base"><?= $typo($ligne) ?></span></li><?php endforeach ?>
      </ol>
    </section>
    <details class="legal__sommaire">
      <summary><?= icone('liste', 20) ?><span class="t-label">Sommaire</span><span class="t-etiquette t-secondaire"><?= count($doc['articles']) ?> articles</span><?= icone('chevron', 20) ?></summary>
      <ol role="list">
        <?php foreach ($doc['articles'] as $i => $a): ?><li><a class="lien" href="#article-<?= $i + 1 ?>"><?= $typo($a['titre']) ?></a></li><?php endforeach ?>
      </ol>
    </details>
  <?php else: ?>
    <p class="t-lead"><?= $typo($doc['intro']) ?></p>
    <section class="legal__donnees" aria-labelledby="donnees">
      <h2 class="t-etiquette t-secondaire" id="donnees">Vos données</h2>
      <table>
        <thead><tr><th scope="col" class="t-etiquette t-secondaire">Donnée</th><th scope="col" class="t-etiquette t-secondaire">Pourquoi</th><th scope="col" class="t-etiquette t-secondaire">Combien de temps</th></tr></thead>
        <tbody>
          <?php foreach ($doc['tableau'] as [$donnee, $pourquoi, $duree]): ?>
            <tr><th scope="row" class="t-label"><?= $typo($donnee) ?></th><td class="t-petit"><?= $typo($pourquoi) ?></td><td class="t-petit t-gras-mobile"><?= $typo($duree) ?></td></tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </section>
    <?= composant('note', ['type' => 'info', 'message' => $typo($doc['note'])]) ?>
  <?php endif ?>

  <?php foreach ($doc['articles'] as $i => $a): ?>
    <section class="legal__article" id="article-<?= $i + 1 ?>" aria-labelledby="titre-article-<?= $i + 1 ?>">
      <p class="t-etiquette t-secondaire">Article <?= $i + 1 ?></p>
      <h2 class="t-titre-3" id="titre-article-<?= $i + 1 ?>"><?= $typo($a['titre']) ?></h2>
      <?php foreach ($a['paragraphes'] as $p): ?><p class="t-base"><?= $typo($p) ?><?php if (isset($a['mono'])): ?> <span class="t-donnee"><?= e($a['mono']) ?></span><?php endif ?></p><?php endforeach ?>
      <?php if (!empty($a['lien'])): ?><a class="lien-icone t-gras" href="<?= e($a['lien']['href']) ?>"><span><?= e($a['lien']['libelle']) ?></span><?= icone('chevron', 20) ?></a><?php endif ?>
    </section>
  <?php endforeach ?>

  <footer class="legal__pied">
    <p class="t-etiquette t-secondaire"><?= e($maj) ?></p>
    <a class="lien-icone t-gras" href="/<?= e($quoi) ?>.pdf" download><?= icone('fichier', 20) ?><span>Télécharger en PDF</span><span class="t-etiquette t-secondaire">· <?= e(Format::nombre((int) ceil($poidsPdf / 1024))) ?> Ko</span></a>
  </footer>
</article>
