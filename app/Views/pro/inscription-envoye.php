<?php
/**
 * M1e · Dossier envoyé (Figma 105:656) : l'écrou rempli, « En attente », la réponse sous 48 h, le numéro du dossier, un lien vers l'espace.
 * @var string $numero       « 0412 »
 * @var \DateTimeImmutable $envoye_le
 * @var array<string, string> $etats
 * @var int $delai           48
 */
use App\Core\Format;

$nbsp = Format::INSECABLE;
?>
<div class="pro envoye">
  <header class="envoye__barre"><a class="barre-mecano__logo" href="/pro" aria-label="allomécano, mon espace"><svg viewBox="0 0 197 40" role="img" aria-label="allomécano"><use href="<?= e(asset('icones/sprite.svg')) ?>#i-logo"></use></svg></a></header>
  <main class="envoye__contenu" id="contenu">
    <?= composant('ecrou-dossier', ['pieces' => $etats, 'taille' => 120]) ?>
    <?= composant('statut', ['statut' => 'en_attente']) ?>
    <h1 class="t-display-l">Dossier envoyé.</h1>
    <p class="t-base"><?= e(Format::typo('Réponse sous ' . $delai . $nbsp . 'h, par SMS et par e-mail. En attendant, préparez votre première journée.')) ?></p>
    <p class="t-donnee t-secondaire"><?= e('Dossier n° ' . $numero . ' · envoyé le ' . Format::date($envoye_le) . ' à ' . Format::heure($envoye_le)) ?></p>
    <a class="lien-icone t-gras envoye__lien" href="/pro"><span>Voir mon espace</span><?= icone('chevron', 20) ?></a>
    <?= composant('photo', [
        'image' => 'img/accueil/img-13', 'largeurs' => [328, 656], 'sizes' => '358px', 'largeur' => 656, 'hauteur' => 874, 'classe' => 'envoye__photo',
        'alt' => 'Un mécanicien en tenue de travail tient une clé à molette, dans son atelier.', 'legende' => 'Votre première journée · 07:30',
    ]) ?>
    <a class="lien-icone t-gras envoye__lien" href="/pro"><span>Voir mon espace</span><?= icone('chevron', 20) ?></a>
  </main>
</div>
