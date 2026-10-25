<?php
/**
 * Écrou dossier légendé (Figma 92:154) : l'écrou de 160 au centre, ses six facettes numérotées autour, reliées par des filets.
 * Sens horaire depuis la facette haute à droite : 1 CNI recto, 2 CNI verso, 3 permis, 4 diplôme ou attestation, 5 photo de vous, 6 atelier (facultatif).
 * @var array<string, string> $pieces  type → état, comme ecrou-dossier ; toutes « envoyee » si absent
 */
$pieces ??= ['cni_recto' => 'envoyee', 'cni_verso' => 'envoyee', 'permis' => 'envoyee', 'diplome' => 'envoyee', 'portrait' => 'envoyee', 'atelier' => 'facultative'];
?>
<div class="ecrou-legende">
  <span class="ecrou-legende__ecrou"><?= composant('ecrou-dossier', ['pieces' => $pieces, 'taille' => 160]) ?></span>
  <span class="ecrou-legende__filets" aria-hidden="true"></span>
  <ol class="ecrou-legende__legende t-etiquette">
    <li class="ecrou-legende__1" value="1">1 · CNI recto</li>
    <li class="ecrou-legende__2" value="2">2 · CNI<br>verso</li>
    <li class="ecrou-legende__3" value="3">3 · Permis</li>
    <li class="ecrou-legende__4" value="4">4 · Diplôme ou<br>attestation</li>
    <li class="ecrou-legende__5" value="5">5 · Photo<br>de vous</li>
    <li class="ecrou-legende__6 t-secondaire" value="6">6 · Atelier<br>facultatif</li>
  </ol>
</div>
