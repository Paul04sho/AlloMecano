// Contact direct et WhatsApp (RG-A1, RG-A12), retour d'appel « Avez-vous pu le joindre ? » (RG-I4, C6) et numéro révélé (D1).
// Sans JS, le formulaire crée le contact puis part vers « tel: » ou WhatsApp : tout ceci n'est que de l'amélioration.
import { api, toast, vibrer } from '../core/app.js';
import { initialiserMessage } from './fiche.js';

const reduit = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const CLE = 'appel';
const bureau = () => matchMedia('(min-width: 1024px)').matches;
/** RG-A2 : l'appel intégré demande un micro et du WebRTC ; sinon « Appeler » ouvre l'appel direct. */
const capable = () => !!(navigator.mediaDevices?.getUserMedia && window.RTCPeerConnection);

function memoriser(id, min) {
  try { sessionStorage.setItem(CLE, JSON.stringify({ id, t: Date.now(), min })); } catch { /* stockage fermé : pas de retour d'appel */ }
}
function lireMemoire() {
  try { return JSON.parse(sessionStorage.getItem(CLE) || 'null'); } catch { return null; }
}
/** X7 : les 3 derniers mécanos appelés, gardés sur l'appareil seulement, pour les retrouver quand le réseau saute. */
function retenirMecano(memo) {
  if (!memo) return;
  try {
    const liste = JSON.parse(localStorage.getItem('derniers_mecanos') || '[]').filter((m) => m.telephone !== memo.telephone);
    localStorage.setItem('derniers_mecanos', JSON.stringify([{ ...memo, vu: new Date().toISOString() }, ...liste].slice(0, 3)));
  } catch { /* stockage fermé : pas d'historique hors ligne */ }
}
function oublier() {
  try { sessionStorage.removeItem(CLE); } catch { /* rien à oublier */ }
}

/** Chaque appui crée le contact AVANT d'ouvrir l'appel (RG-A1) ; le numéro n'arrive qu'avec la réponse. */
export function initialiserContacts(racine) {
  racine.querySelectorAll('[data-formulaire-contact]').forEach((f) => {
    if (f.dataset.lie) return;
    f.dataset.lie = '1';
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const bouton = f.querySelector('button[type="submit"]');
      if (bouton?.getAttribute('aria-busy') === 'true') return;
      bouton?.setAttribute('aria-busy', 'true');
      const donnees = Object.fromEntries([...new FormData(f)].filter(([k]) => k !== '_csrf'));
      if (f.hasAttribute('data-reveler')) donnees.reveler = '1';
      donnees.webrtc = capable() ? '1' : '0';
      try {
        const r = await api('/api/contacts', { methode: 'POST', donnees });
        if (r.html) { reveler(f, r.html); return; }
        if (r.canal === 'app') { location.href = r.cible; return; } // l'appel intégré : la page d'appel (C5c) prend le relais
        memoriser(r.id, Number(f.dataset.absenceMin) || 3);
        retenirMecano(r.memo);
        if (r.canal === 'whatsapp' && bureau()) {
          // Desktop : WhatsApp Web dans un autre onglet, la fiche reste là
          if (!window.open(r.cible, '_blank', 'noopener')) location.href = r.cible;
        } else {
          location.href = r.cible;
        }
      } catch (err) {
        toast(err.message || 'L’appel n’a pas pu être lancé.', { icone: 'triangle' });
      } finally {
        bouton?.removeAttribute('aria-busy');
      }
    });
  });
}

/** D1 · 159:858 : le numéro, le QR code et « Ouvrir WhatsApp Web » prennent la place des boutons. */
function reveler(formulaire, html) {
  const zone = formulaire.closest('[data-contact]');
  if (!zone) return;
  zone.innerHTML = html; // composants/contact-revele : rendu et échappé par le serveur, après la création du contact
  if (!reduit()) zone.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 200, easing: 'cubic-bezier(.2, .8, .2, 1)' });
  zone.querySelector('[data-copier-numero]')?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(zone.querySelector('[data-numero]').textContent.trim());
      vibrer(10);
      toast('Copié', { icone: 'coche' });
    } catch {
      toast('Copiez le numéro à la main', { icone: 'info' });
    }
  });
}

// ---------- Retour d'appel (RG-A12, RG-I4 ; prototype : moment 6) ----------

/** Les étapes de C6 : réponses, mécano suivant, conseils. `racine` contient [data-retour-appel]. */
export function initialiserRetour(racine, { surFermeture } = {}) {
  const contact = racine.dataset.contact;
  const feuille = racine.querySelector('[data-retour-feuille]');
  const contenu = racine.querySelector('[data-retour-contenu]');

  // « Appeler Seydou » doit tenir sur une ligne : sinon WhatsApp passe en icône seule (comme dans la liste)
  const ajuster = () => racine.querySelectorAll('.retour__suivant .ligne-classement__actions').forEach((a) => {
    a.classList.remove('ligne-classement__actions--compactes');
    const l = a.querySelector('form:first-child .bouton__libelle');
    if (l && l.scrollWidth > l.clientWidth) a.classList.add('ligne-classement__actions--compactes');
  });
  addEventListener('resize', ajuster);

  const etape = () => {
    initialiserContacts(racine);
    ajuster();
    initialiserMessage(racine);
    racine.querySelector('#retour-titre')?.focus({ preventScroll: true });
    // Le repère du mécano suivant se plante (320 ms, 200 ms après) ; les conseils se posent l'un après l'autre
    const repere = racine.querySelector('.retour__suivant .repere');
    if (repere && !reduit()) repere.animate([{ opacity: 0, transform: 'translateY(-16px) scale(.9)' }, { opacity: 1, transform: 'none' }], { delay: 200, duration: 320, easing: 'cubic-bezier(.2, .9, .25, 1.15)', fill: 'backwards' });
    racine.querySelectorAll('.conseils__ligne').forEach((li, i) => {
      if (reduit()) { li.classList.add('pose'); return; }
      li.animate([{ opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }], { delay: 160 + i * 240, duration: 240, easing: 'cubic-bezier(.2, .8, .2, 1)', fill: 'backwards' })
        .finished.then(() => li.classList.add('pose'), () => li.classList.add('pose'));
    });
  };
  etape();

  racine.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-reponse-retour]');
    if (!form) return;
    e.preventDefault();
    const bouton = e.submitter ?? form.querySelector('button[type="submit"]');
    if (bouton?.getAttribute('aria-busy') === 'true') return;
    bouton?.setAttribute('aria-busy', 'true');
    try {
      const r = await api(`/api/contacts/${encodeURIComponent(contact)}/issue`, { methode: 'POST', donnees: { issue: bouton.value } });
      const actuelle = contenu.querySelector('.retour__etape');
      if (actuelle && !reduit()) { actuelle.classList.add('sort'); await new Promise((ok) => setTimeout(ok, 200)); } // la suite entre de la droite, 200 ms
      contenu.innerHTML = r.html; // client/contact-etape : rendu et échappé par le serveur
      const nouvelle = contenu.querySelector('.retour__etape');
      if (!reduit()) nouvelle?.classList.add('entre');
      feuille.querySelector('.retour__interieur')?.scrollTo({ top: 0 });
      etape();
    } catch (err) {
      toast(err.message || 'Votre réponse n’a pas été enregistrée.', { icone: 'triangle' });
      bouton?.removeAttribute('aria-busy');
    }
  });

  // « Plus tard » : la feuille redescend (200 ms) ; en pleine page le lien suffit
  racine.addEventListener('click', (e) => {
    if (!e.target.closest('[data-plus-tard]') || !racine.classList.contains('retour--feuille')) return;
    e.preventDefault();
    fermer(racine, surFermeture);
  });
}

function fermer(racine, apres) {
  const fin = () => { racine.remove(); document.removeEventListener('keydown', surEchap); apres?.(); };
  if (reduit()) { fin(); return; }
  racine.querySelector('[data-retour-voile]')?.animate([{ opacity: .92 }, { opacity: 0 }], { duration: 200, fill: 'forwards' });
  racine.querySelector('[data-retour-feuille]')?.animate([{ transform: 'translateY(0)' }, { transform: 'translateY(100%)' }], { duration: 200, easing: 'cubic-bezier(.7, 0, .84, 0)', fill: 'forwards' }).finished.then(fin, fin);
}
function surEchap(e) {
  if (e.key === 'Escape') document.querySelector('.retour--feuille') && fermer(document.querySelector('.retour--feuille'));
}

/** Moment 6 : la feuille monte 1,5 s après le retour, voile bitume à 92 % et feuille qui glisse (280 ms). */
async function ouvrirFeuille(id) {
  if (document.querySelector('.retour--feuille')) return;
  let html;
  try {
    const r = await fetch(`/api/contacts/${encodeURIComponent(id)}/retour`, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (!r.ok) return;
    html = await r.text();
  } catch { return; }
  document.body.insertAdjacentHTML('beforeend', html); // composants/retour-appel : rendu et échappé par le serveur
  const racine = document.querySelector('.retour--feuille');
  if (!reduit()) {
    racine.querySelector('[data-retour-voile]').animate([{ opacity: 0 }, { opacity: .92 }], { duration: 280, easing: 'cubic-bezier(.2, .8, .2, 1)' });
    racine.querySelector('[data-retour-feuille]').animate([{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }], { duration: 280, easing: 'cubic-bezier(.2, .8, .2, 1)' });
  }
  initialiserRetour(racine);
  document.addEventListener('keydown', surEchap);
}

async function verifier() {
  if (document.visibilityState !== 'visible') return;
  const a = lireMemoire();
  if (!a) return;
  const absence = (Date.now() - a.t) / 1000;
  if (absence > 24 * 3600) { oublier(); return; } // RG-I4 : au-delà, c'est le bandeau qui prend le relais
  if (absence < (a.min || 3)) return; // moins de 3 s : pas un vrai appel
  oublier();
  api(`/api/contacts/${encodeURIComponent(a.id)}/absence`, { methode: 'POST', donnees: { secondes: Math.round(absence) } }).catch(() => {}); // RG-A12 : un indice, jamais une preuve
  setTimeout(() => ouvrirFeuille(a.id), 1500);
}

/** À appeler une fois par page : surveille le retour dans l'onglet. */
export function surveillerRetour() {
  if (surveillerRetour.actif) return;
  surveillerRetour.actif = true;
  document.addEventListener('visibilitychange', verifier);
  addEventListener('pageshow', verifier);
  verifier();
}
