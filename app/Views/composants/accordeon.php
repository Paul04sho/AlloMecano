<?php
/**
 * Accordéon (Figma 92:251 · 92:263) : ligne de 56 minimum, question en Corps/Gras, chevron qui tourne d'un quart de tour à l'ouverture, réponse en Corps/Base secondaire.
 * Natif (details/summary) : clavier, lecteur d'écran et sans JavaScript.
 * @var string $question
 * @var string $reponse   texte brut
 * @var bool $ouvert
 */
use App\Core\Format;

$ouvert ??= false;
?>
<details class="accordeon"<?= $ouvert ? ' open' : '' ?>>
  <summary class="accordeon__ligne"><span class="t-gras"><?= e(Format::typo($question)) ?></span><?= icone('chevron', 24) ?></summary>
  <p class="accordeon__reponse t-base t-secondaire"><?= e(Format::typo($reponse)) ?></p>
</details>
