<?php
/**
 * M1c · Inscription 3/4 · Vos pièces (Figma 102:434) : l'écrou se remplit pièce par pièce, une pochette par pièce, le guide « Réussir votre photo ».
 * « Continuer » reste éteint, avec la phrase qui dit ce qui manque, tant que les cinq pièces obligatoires ne sont pas là (RG-DO2, RG-DO3).
 * @var array<string, mixed> $b
 * @var array{etats: array<string, string>, courantes: array<string, array<string, mixed>>, obligatoires_envoyees: int, obligatoires: int, manquantes: list<string>} $etat
 */
use App\Core\Format;
use App\Services\InscriptionService;

$noms = ['cni_recto' => 'CNI recto', 'cni_verso' => 'CNI verso', 'permis' => 'Permis de conduire', 'diplome' => 'Diplôme ou attestation', 'portrait' => 'Photo de vous', 'atelier' => 'Atelier (facultatif)'];
$manque = $etat['manquantes'];
$complet = $manque === [];
$etatPochette = static function (array $courantes, string $type): string {
    $c = $courantes[$type] ?? null;
    return $c === null ? 'manquante' : ($c['statut'] === 'a_reprendre' ? 'a_reprendre' : ($c['statut'] === 'validee' ? 'conforme' : 'envoyee'));
};
?>
<div class="pro inscription avec-socle" data-inscription-pieces>
  <?= composant('barre-parcours', ['etape' => 3, 'total' => 4, 'retour' => '/pro/inscription/metier', 'annuler' => '/', 'annulerLibelle' => 'Plus tard']) ?>
  <?= composant('parcours-brouillon', ['heure' => Format::heure($b['modifie_le'])]) ?>

  <div class="inscription__formulaire">
    <div class="pieces__dossier">
      <span data-pieces-ecrou><?= composant('ecrou-dossier', ['pieces' => $etat['etats'], 'taille' => 96]) ?></span>
      <div class="pieces__compteur">
        <p class="t-etiquette" data-pieces-compte><?= (int) $etat['obligatoires_envoyees'] ?> / <?= (int) $etat['obligatoires'] ?> obligatoires</p>
        <p class="t-petit t-secondaire" data-pieces-phrase><?= e($complet ? 'Toutes les pièces obligatoires sont là. L’atelier est facultatif.' : 'Il manque ' . InscriptionService::phraseManque($manque) . '. L’atelier est facultatif.') ?></p>
      </div>
    </div>

    <div class="inscription__titre">
      <h1 class="t-titre-1">Vos pièces</h1>
      <p class="t-petit t-secondaire">Une photo nette de chaque pièce suffit. Chaque pièce envoyée remplit une facette de l’écrou.</p>
    </div>

    <template data-specimen><?= composant('specimen-cni', ['classe' => 'pochette__specimen']) ?></template>
    <ul class="pieces__pochettes" role="list">
      <?php foreach ($noms as $type => $libelle): $c = $etat['courantes'][$type] ?? null; ?>
        <?= composant('pochette-piece', ['type' => $type, 'libelle' => $libelle, 'etat' => $etatPochette($etat['courantes'], $type), 'taille' => $c['taille_octets'] ?? null, 'motif' => $c['motif'] ?? null, 'action' => '/pro/inscription/pieces/' . $type]) ?>
      <?php endforeach ?>
    </ul>

    <section class="pieces__guide" aria-labelledby="guide-titre">
      <div class="rubrique">
        <p class="t-etiquette">Pour la photo de vous</p>
        <h2 class="t-titre-2" id="guide-titre">Réussir votre photo</h2>
        <p class="t-petit t-secondaire">Elle sera comparée à votre pièce d’identité, puis montrée aux clients.</p>
      </div>
      <?= composant('guide-photo', []) ?>
    </section>

    <p class="pieces__confidentialite t-etiquette t-secondaire"><?= icone('cadenas', 16) ?><span>Seule l’équipe de vérification voit vos documents</span></p>

    <?= composant('socle', [
        'info' => $complet ? null : 'Il manque ' . InscriptionService::phraseManque($manque),
        'actions' => composant('bouton', ['libelle' => 'Continuer', 'type' => 'principal', 'hauteur' => 56, 'plein' => true, 'href' => '/pro/inscription/envoi', 'desactive' => !$complet, 'attrs' => ['data-pieces-continuer' => '']]),
    ]) ?>
  </div>
</div>
