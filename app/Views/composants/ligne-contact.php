<?php
/**
 * Une demande (Figma 116:991) : l'heure en Borne/L, le client et sa panne (voyant de bord de 20), « Rappeler » ; dessous, le canal, le quartier avec la distance, et le retour du client.
 * « Rappeler » n'existe que si le client a laissé son numéro, et plus quand il est masqué (après 48 h : cadenas et numéro à moitié caché). Un seul bouton jaune par écran.
 * @var array{heure: string, client: string, resume: string, code_panne: ?string, canal: string, lieu: ?string, distance: ?string, retour: string, principal: bool, rappel: ?string, masque: ?string} $demande
 */
$d = $demande;
$retours = ['joint' => 'Joint', 'non_joint' => 'Non joint', 'sans_retour' => 'Sans retour'];
$lieu = implode(' · ', array_filter([$d['lieu'], $d['distance'] !== null ? 'à ' . $d['distance'] : null]));
?>
<li class="ligne-contact">
  <div class="ligne-contact__haut">
    <p class="ligne-contact__heure t-borne-l"><?= e($d['heure']) ?></p>
    <div class="ligne-contact__client">
      <p class="t-titre-3"><?= e($d['client']) ?></p>
      <p class="ligne-contact__panne"><?php if ($d['code_panne'] !== null): ?><span class="ligne-contact__voyant"><?= composant('voyant-de-bord', ['code' => $d['code_panne'], 'allume' => true]) ?></span><?php endif ?><span class="t-petit"><?= e($d['resume']) ?></span></p>
      <?php if ($d['masque'] !== null): ?><p class="ligne-contact__masque t-donnee t-secondaire"><?= icone('cadenas', 16) ?><span><?= e($d['masque']) ?></span></p><?php endif ?>
    </div>
    <?php if ($d['rappel'] !== null): ?><?= composant('bouton', ['libelle' => 'Rappeler', 'type' => $d['principal'] ? 'principal' : 'secondaire', 'hauteur' => 48, 'href' => 'tel:' . $d['rappel'], 'attrs' => ['aria-label' => 'Rappeler ' . $d['client'] . ', ' . $d['heure']]]) ?><?php endif ?>
  </div>
  <div class="ligne-contact__bas t-etiquette">
    <span class="ligne-contact__canal"><?= e($d['canal']) ?></span>
    <span class="ligne-contact__lieu"><?= e($lieu) ?></span>
    <span class="ligne-contact__retour ligne-contact__retour--<?= e(str_replace('_', '-', $d['retour'])) ?>"><?= e($retours[$d['retour']] ?? '') ?></span>
  </div>
</li>
