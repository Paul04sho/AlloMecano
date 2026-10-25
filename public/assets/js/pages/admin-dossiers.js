// A1 · File des dossiers : parcourir les lignes aux flèches ↑ ↓ et ouvrir un dossier avec Entrée (légende du pied de table, Figma 167:536).
// La recherche se lance seule quand on arrête de taper. Sans JS : Entrée dans le champ envoie le formulaire, chaque ligne a son bouton « Examiner ».
const lignes = [...document.querySelectorAll('[data-ligne]')];
if (lignes.length) {
  let courante = -1;
  const aller = (i) => {
    courante = Math.max(0, Math.min(lignes.length - 1, i));
    lignes.forEach((l, k) => l.classList.toggle('est-active', k === courante));
    lignes[courante].scrollIntoView({ block: 'nearest' });
  };
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select') || e.altKey || e.ctrlKey || e.metaKey) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); aller(courante + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); aller(courante < 0 ? 0 : courante - 1); }
    else if (e.key === 'Enter' && courante >= 0 && !e.target.closest('a, button')) { lignes[courante].querySelector('[data-examiner]')?.click(); }
  });
  lignes.forEach((l, k) => l.addEventListener('click', (e) => { if (!e.target.closest('a, button')) aller(k); }));
}

const champ = document.querySelector('.dossiers__outils input[name="q"]');
if (champ) {
  let minuteur;
  champ.addEventListener('input', () => {
    clearTimeout(minuteur);
    minuteur = setTimeout(() => champ.form.requestSubmit(), 450);
  });
  if (champ.value) { champ.focus({ preventScroll: true }); champ.setSelectionRange(champ.value.length, champ.value.length); }
}
