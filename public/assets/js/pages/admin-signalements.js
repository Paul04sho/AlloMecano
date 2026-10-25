// A4 · Signalements : la fenêtre de classement s'ouvre en vraie modale (dialog natif) ; sans JS, ?classer=1 l'ouvre déjà.
function fermer(d) {
  d.close();
  const url = new URL(location.href);
  url.searchParams.delete('classer');
  history.replaceState(null, '', url);
}
document.querySelectorAll('dialog[data-modale]').forEach((d) => {
  if (d.hasAttribute('open')) { d.removeAttribute('open'); d.showModal(); d.querySelector('textarea')?.focus(); }
  d.addEventListener('cancel', (e) => { e.preventDefault(); fermer(d); });
  d.addEventListener('click', (e) => { if (e.target === d) fermer(d); });
  d.querySelectorAll('[data-fermer]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); fermer(d); }));
});
document.querySelector('[data-classer]')?.addEventListener('click', (e) => {
  const d = document.querySelector('dialog[data-modale="classer"]');
  if (!d) return;
  e.preventDefault();
  d.showModal();
  d.querySelector('textarea')?.focus();
});
