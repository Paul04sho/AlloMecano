// C3 · Ma panne (69:653, 69:868) : le voyant s'allume, « Choc ou accident » fait monter la note Danger,
// le socle s'active au premier choix. Prénom et téléphone restent aussi sur ce téléphone (localStorage).
import { api } from '../core/app.js';

const formulaire = document.querySelector('[data-panne]');
const danger = formulaire.querySelector('[data-note-danger]');
const precisions = formulaire.querySelector('#champ-precisions');
const voir = formulaire.querySelector('[data-voir]');
const pourquoi = formulaire.querySelector('[data-socle-pourquoi]');
const prenom = formulaire.querySelector('#champ-prenom');
const telephone = formulaire.querySelector('#champ-telephone');
const exemples = JSON.parse(formulaire.dataset.exemples || '{}');

const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const SORTIE = 'cubic-bezier(.2,.8,.2,1)';
const duree = (ms) => (reduit ? 1 : ms);
const MEMOIRE = 'am_contact';

const panneChoisie = () => formulaire.querySelector('input[name="panne"]:checked');

// Socle : désactivé tant qu'il manque la panne, avec la phrase qui dit quoi faire
function socle(animer) {
  const ok = !!panneChoisie();
  const avant = voir.disabled;
  voir.disabled = !ok;
  if (pourquoi) pourquoi.hidden = ok;
  // Premier choix : désactivé → jaune, puis un petit coup pour dire qu'on peut y aller (400 ms)
  if (animer && avant && ok) voir.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.02)', offset: .5 }, { transform: 'scale(1)' }], { duration: duree(400), easing: SORTIE });
}

function choisirPanne(input, animer) {
  // Moment 3 · Le voyant de bord s'allume : deux éclairs, en opacité seulement (260 ms)
  const allume = input.parentElement.querySelector('.voyant-bord__allume');
  if (animer && allume) allume.animate([{ opacity: 0 }, { opacity: 1, offset: .3 }, { opacity: .4, offset: .6 }, { opacity: 1 }], { duration: duree(260), easing: 'linear' });
  precisions.placeholder = exemples[input.value] ?? precisions.placeholder;
  // Choc ou accident : la note Danger monte au-dessus de tout (8 px, 240 ms)
  const etaitCache = danger.hidden;
  danger.hidden = !input.hasAttribute('data-danger');
  if (animer && etaitCache && !danger.hidden) {
    danger.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: duree(240), easing: SORTIE });
    danger.scrollIntoView({ behavior: reduit ? 'auto' : 'smooth', block: 'nearest' });
  }
  socle(animer);
}

formulaire.addEventListener('change', (e) => {
  if (e.target.name === 'panne') choisirPanne(e.target, true);
});

// Prénom et téléphone : repris de ce téléphone s'ils ne viennent pas du serveur, gardés à l'envoi
try {
  const m = JSON.parse(localStorage.getItem(MEMOIRE) || 'null');
  if (m && !prenom.value && !telephone.value) { prenom.value = m.prenom ?? ''; telephone.value = m.telephone ?? ''; }
} catch {}
formulaire.addEventListener('submit', (e) => {
  if (e.submitter?.hasAttribute('data-effacer')) return;
  try {
    if (prenom.value.trim() || telephone.value.trim()) localStorage.setItem(MEMOIRE, JSON.stringify({ prenom: prenom.value.trim(), telephone: telephone.value.trim() }));
    else localStorage.removeItem(MEMOIRE);
  } catch {}
});

// « Effacer » : sur ce téléphone et en base, sans quitter la page
formulaire.querySelector('[data-effacer]').addEventListener('click', (e) => {
  e.preventDefault();
  prenom.value = ''; telephone.value = '';
  try { localStorage.removeItem(MEMOIRE); } catch {}
  api('/api/client/effacer', { methode: 'POST', donnees: {} }).catch(() => {});
  prenom.focus();
});

const initiale = panneChoisie();
if (initiale) choisirPanne(initiale, false); else socle(false);
