// A3 · Comptes : fenêtres de suspension et de suppression (dialog natif), aperçu exact du message, parcours de la table au clavier.
// Sans JS : « Suspendre le compte » et « Supprimer » ouvrent la fenêtre par l'adresse (?suspendre=1, ?supprimer=1) et la recherche part avec Entrée.
const champ = document.querySelector('.comptes__outils input[name="q"]');
if (champ) {
  let minuteur;
  champ.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(() => champ.form.requestSubmit(), 450); });
  if (champ.value) { champ.focus({ preventScroll: true }); champ.setSelectionRange(champ.value.length, champ.value.length); }
}

// Fenêtres : on garde l'adresse de la page (la fenêtre reste ouverte au rechargement) mais on passe en vraie modale
function fermer(d) {
  d.close();
  const url = new URL(location.href);
  url.searchParams.delete('suspendre');
  url.searchParams.delete('supprimer');
  history.replaceState(null, '', url);
}
document.querySelectorAll('dialog[data-modale]').forEach((d) => {
  if (d.hasAttribute('open')) { d.removeAttribute('open'); d.showModal(); }
  d.addEventListener('cancel', (e) => { e.preventDefault(); fermer(d); });
  d.addEventListener('click', (e) => { if (e.target === d) fermer(d); });
  d.querySelectorAll('[data-fermer]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); fermer(d); }));
});
for (const [attribut, nom] of [['data-suspendre', 'suspendre'], ['data-supprimer', 'supprimer']]) {
  document.querySelector(`[${attribut}]`)?.addEventListener('click', (e) => {
    const d = document.querySelector(`dialog[data-modale="${nom}"]`);
    if (!d) return;
    e.preventDefault();
    d.showModal();
    d.querySelector('input:not([type="hidden"]), textarea')?.focus();
  });
}

// Aperçu exact du message de suspension, mis à jour à chaque frappe et à chaque durée choisie
const form = document.querySelector('form[data-apercu]');
if (form) {
  const texte = form.querySelector('[data-apercu-texte]');
  let minuteur;
  const maj = () => {
    clearTimeout(minuteur);
    minuteur = setTimeout(async () => {
      const params = new URLSearchParams({ motif: form.motif.value, duree: form.querySelector('input[name="duree"]:checked')?.value ?? 'indefinie' });
      try { texte.textContent = (await (await fetch(`${form.dataset.apercu}?${params}`, { headers: { Accept: 'application/json' } })).json()).message; } catch { /* l'aperçu reste celui du serveur */ }
    }, 200);
  };
  form.addEventListener('input', maj);
  form.addEventListener('change', maj);
}

// Table : ↑ ↓ pour parcourir, Entrée pour ouvrir le compte
const lignes = [...document.querySelectorAll('[data-ligne]')];
if (lignes.length) {
  let courante = lignes.findIndex((l) => l.classList.contains('compte--ouvert'));
  const aller = (i) => {
    courante = Math.max(0, Math.min(lignes.length - 1, i));
    lignes.forEach((l, k) => l.classList.toggle('est-active', k === courante));
    lignes[courante].scrollIntoView({ block: 'nearest' });
  };
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select, dialog') || e.altKey || e.ctrlKey || e.metaKey) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); aller(courante + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); aller(courante < 0 ? 0 : courante - 1); }
    else if (e.key === 'Enter' && courante >= 0 && !e.target.closest('a, button')) { lignes[courante].querySelector('[data-ouvrir]')?.click(); }
  });
}
