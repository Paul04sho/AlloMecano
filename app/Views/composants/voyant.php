<?php
/** Voyant d'état (Figma 49:144). @var string $etat disponible | indisponible | erreur | recherche */ ?>
<span class="voyant voyant--<?= e($etat ?? 'disponible') ?>" aria-hidden="true"></span>
