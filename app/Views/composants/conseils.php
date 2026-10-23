<?php
/**
 * Conseils en attendant le mécano (Figma 86:1588) : poinçon 18, une phrase, filet. Ils se posent l'un après l'autre à l'arrivée.
 * @var list<string> $conseils
 */
use App\Core\Format;
?>
<ul class="conseils" role="list" data-conseils>
  <?php foreach ($conseils as $c): ?>
    <li class="conseils__ligne"><?= composant('poincon', ['taille' => 18]) ?><span class="t-base"><?= e(Format::typo($c)) ?></span></li>
  <?php endforeach ?>
</ul>
