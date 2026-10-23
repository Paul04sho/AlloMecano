// Outil de développement : joue le mécano qui reçoit l'appel intégré (absent en production).
// Même protocole que le futur écran M5 : on voit l'appel sonner, on décroche (réponse WebRTC), on raccroche, puis « J'y vais » / « Pas pour moi ».
import { api } from '../core/app.js';

const racine = document.querySelector('[data-dev-mecano]');
const choix = racine.querySelector('[data-choix]');
const statut = racine.querySelector('[data-statut]');
const blocs = { entrant: racine.querySelector('[data-appel-entrant]'), ligne: racine.querySelector('[data-en-ligne]'), reponse: racine.querySelector('[data-reponse]') };
const audio = racine.querySelector('[data-audio]');

let contact = null;
let pc = null;
let flux = null;
let dernier = 0;
let enCours = false;
let debut = 0;
let candidatsEnAttente = [];
let remoteOk = false;

const mecano = () => choix.value;
const dev = (chemin) => `/api/dev/appel/${encodeURIComponent(contact)}/${chemin}`;
const montrer = (nom) => Object.entries(blocs).forEach(([k, el]) => { el.hidden = k !== nom; });
const poster = (type, charge = null) => api(dev('signal'), { methode: 'POST', donnees: { mecano: mecano(), type, charge } });

/** Par défaut une piste silencieuse (poste de test sans micro, pas d'invite) ; ouvrir la page avec ?micro=1 pour utiliser le vrai micro. */
async function micro() {
  if (new URLSearchParams(location.search).get('micro') === '1') {
    try { return await navigator.mediaDevices.getUserMedia({ audio: true }); } catch { /* repli sur la piste silencieuse */ }
  }
  const ctx = new AudioContext();
  const dest = ctx.createMediaStreamDestination();
  ctx.createOscillator().connect(dest);
  return dest.stream;
}

async function surveiller() {
  while (true) {
    try {
      if (!enCours) {
        const { entrants } = await api(`/api/dev/mecano/${encodeURIComponent(mecano())}/entrants`);
        if (entrants.length > 0 && contact !== entrants[0].contact) {
          contact = entrants[0].contact;
          dernier = 0;
          const e = entrants[0];
          racine.querySelector('[data-resume]').textContent = `Appel entrant · ${e.resume}${e.lieu ? ' · ' + e.lieu : ''}${e.repere ? ', ' + e.repere : ''}`;
          statut.textContent = 'Ça sonne…';
          montrer('entrant');
        } else if (entrants.length === 0 && contact !== null && !blocs.entrant.hidden) {
          contact = null;
          statut.textContent = 'Appel manqué ou annulé. En attente d’un appel…';
          montrer(null);
        }
      } else {
        const r = await api(`/api/dev/appel/${encodeURIComponent(contact)}/signaux?mecano=${encodeURIComponent(mecano())}&apres=${dernier}`);
        dernier = r.dernier;
        for (const s of r.signaux) {
          if (s.type === 'candidat') { if (remoteOk) await pc.addIceCandidate(s.charge).catch(() => {}); else candidatsEnAttente.push(s.charge); }
          if (s.type === 'raccroche') await fin();
        }
        if (['termine', 'annule', 'manque', 'occupe', 'echoue'].includes(r.statut)) await fin();
      }
    } catch { /* on réessaie */ }
    await new Promise((ok) => setTimeout(ok, enCours ? 1000 : 1500));
  }
}

async function decrocher() {
  enCours = true;
  statut.textContent = 'Connexion…';
  // L'offre du client est dans la signalisation
  let offre = null;
  for (let i = 0; i < 20 && !offre; i++) {
    const r = await api(`/api/dev/appel/${encodeURIComponent(contact)}/signaux?mecano=${encodeURIComponent(mecano())}&apres=0`);
    offre = r.signaux.find((s) => s.type === 'offre');
    for (const s of r.signaux) dernier = Math.max(dernier, s.id);
    if (!offre) await new Promise((ok) => setTimeout(ok, 500));
  }
  if (!offre) { enCours = false; return; }
  flux = await micro();
  const { iceServers } = await api('/api/dev/ice');
  pc = new RTCPeerConnection({ iceServers });
  flux.getTracks().forEach((t) => pc.addTrack(t, flux));
  pc.ontrack = (e) => { audio.srcObject = e.streams[0]; audio.play().catch(() => {}); };
  pc.onicecandidate = (e) => { if (e.candidate) poster('candidat', e.candidate.toJSON()).catch(() => {}); };
  await pc.setRemoteDescription(offre.charge);
  remoteOk = true;
  for (const c of candidatsEnAttente) await pc.addIceCandidate(c).catch(() => {});
  candidatsEnAttente = [];
  const reponse = await pc.createAnswer();
  await pc.setLocalDescription(reponse);
  await poster('reponse', { type: reponse.type, sdp: reponse.sdp });
  debut = Date.now();
  statut.textContent = 'En ligne';
  montrer('ligne');
}

async function fin() {
  if (!enCours) return;
  enCours = false;
  flux?.getTracks().forEach((t) => t.stop());
  pc?.close();
  pc = null;
  remoteOk = false;
  statut.textContent = 'Appel terminé.';
  montrer('reponse');
}

racine.querySelector('[data-decrocher]').addEventListener('click', decrocher);
racine.querySelector('[data-refuser]').addEventListener('click', async () => {
  await poster('refus').catch(() => {});
  contact = null;
  montrer(null);
  statut.textContent = 'Refusé. En attente d’un appel…';
});
racine.querySelector('[data-raccrocher]').addEventListener('click', async () => {
  await poster('raccroche').catch(() => {});
  await fin();
});
racine.querySelectorAll('[data-issue]').forEach((b) => b.addEventListener('click', async () => {
  await api(dev('issue'), { methode: 'POST', donnees: { mecano: mecano(), reponse: b.dataset.issue } }).catch(() => {});
  contact = null;
  montrer(null);
  statut.textContent = `Réponse envoyée (${b.textContent.trim()}). En attente d’un appel…`;
}));
setInterval(() => {
  const c = racine.querySelector('[data-chrono]');
  if (enCours && debut) { const s = Math.floor((Date.now() - debut) / 1000); c.textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`; }
}, 500);
choix.addEventListener('change', () => { contact = null; enCours = false; montrer(null); });

surveiller();
