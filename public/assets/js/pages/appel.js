// C5c · Appel intégré, côté client : WebRTC audio, signalisation par interrogation (RG-A8 : 1 s pendant la connexion, 3 s pendant l'appel).
// Le serveur tient l'état de l'appel (30 s de sonnerie, RG-A5) ; ici on ne fait que négocier l'audio et montrer où on en est.
import { api, csrf, toast } from '../core/app.js';

const racine = document.querySelector('[data-appel]');
const id = racine.dataset.contact;
const sonnerieS = Number(racine.dataset.sonnerie) || 30;
const echecS = Number(racine.dataset.echec) || 15; // RG-A7 : audio impossible après 15 s
const url = (chemin) => `/api/appels/${encodeURIComponent(id)}/${chemin}`;
const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const sprite = (nom) => document.querySelector('.icone use')?.getAttribute('href')?.replace(/#.*$/, '') + '#i-' + nom;

let pc = null;
let flux = null;
let verrou = null;
let dernier = 0;
let enLigne = false;
let fini = false;
let aRepondu = false;
let candidatsEnAttente = [];
let minuteurEchec = null;
let depart = Date.now(); // début de la sonnerie, recalé sur l'horloge du serveur à chaque réponse
let departLigne = null;

// ---------- Affichage ----------
const chronoSonne = racine.querySelector('[data-phase-sonne] [data-chrono]');
const chronoLigne = racine.querySelector('[data-phase-ligne] [data-chrono]');
const deux = (n) => String(n).padStart(2, '0');
const formatSonne = (s) => `${Math.floor(s / 60)}:${deux(s % 60)}`;
const formatLigne = (s) => `${deux(Math.floor(s / 60))}:${deux(s % 60)}`;

setInterval(() => {
  if (fini) return;
  if (enLigne && departLigne !== null) chronoLigne.textContent = formatLigne(Math.floor((Date.now() - departLigne) / 1000));
  else chronoSonne.textContent = formatSonne(Math.floor((Date.now() - depart) / 1000));
}, 250);

function passerEnLigne(depuisS) {
  if (enLigne) return;
  enLigne = true;
  departLigne = Date.now() - (depuisS ?? 0) * 1000;
  racine.dataset.phase = 'ligne';
  racine.querySelector('[data-anneau]').dataset.phase = 'ligne';
  racine.querySelector('[data-phase-sonne]').hidden = true;
  racine.querySelector('[data-phase-ligne]').hidden = false;
  racine.dataset.statut = 'decroche';
  if (!reduit) racine.querySelector('[data-etat]').animate([{ opacity: 0 }, { opacity: 1 }], { duration: 200, easing: 'cubic-bezier(.2, .8, .2, 1)' });
}

// ---------- Fin : le serveur rend la page qui convient (ne répond pas, terminé…) ----------
function terminer() {
  if (fini) return;
  fini = true;
  nettoyer();
  location.replace(`/appel/${encodeURIComponent(id)}`);
}

function nettoyer() {
  clearTimeout(minuteurEchec);
  flux?.getTracks().forEach((t) => t.stop());
  pc?.close();
  verrou?.release?.().catch(() => {});
}

async function echec(raison) {
  if (fini) return;
  try { await api(url('echec'), { methode: 'POST', donnees: { raison } }); } catch { /* le serveur constatera de lui-même */ }
  terminer();
}

// ---------- Signalisation ----------
const envoyer = (type, charge = null) => api(url('signal'), { methode: 'POST', donnees: { type, charge } });

async function traiter(s) {
  if (s.type === 'reponse' && !aRepondu) {
    aRepondu = true;
    await pc.setRemoteDescription(s.charge);
    for (const c of candidatsEnAttente) await pc.addIceCandidate(c).catch(() => {});
    candidatsEnAttente = [];
    // RG-A7 : si l'audio ne passe pas dans les 15 s, l'appel est « échoué » et on propose le numéro
    minuteurEchec = setTimeout(() => { if (!['connected', 'completed'].includes(pc.iceConnectionState)) echec('reseau'); }, echecS * 1000);
  } else if (s.type === 'candidat') {
    if (aRepondu) await pc.addIceCandidate(s.charge).catch(() => {});
    else candidatsEnAttente.push(s.charge);
  }
}

async function boucle() {
  while (!fini) {
    try {
      const r = await api(`${url('signaux')}?apres=${dernier}`);
      dernier = r.dernier;
      depart = Date.now() - r.sonne_depuis_s * 1000;
      for (const s of r.signaux) await traiter(s);
      if (r.fin) return terminer();
      if (r.statut === 'decroche') passerEnLigne(r.en_ligne_depuis_s);
    } catch (e) {
      if (e.statut === 404) return terminer();
      // réseau coupé un instant : on réessaie au prochain tour
    }
    await new Promise((ok) => setTimeout(ok, enLigne ? 3000 : 1000));
  }
}

// ---------- Appel ----------
async function demarrer() {
  try { verrou = await navigator.wakeLock?.request('screen'); } catch { /* écran non verrouillable : sans importance */ }
  try {
    flux = await navigator.mediaDevices.getUserMedia({ audio: true });
  } catch {
    return echec('micro_refuse'); // RG-A7
  }
  let serveurs;
  try { serveurs = (await api('/api/appels/ice')).iceServers; } catch { serveurs = [{ urls: ['stun:stun.cloudflare.com:3478'] }]; }
  pc = new RTCPeerConnection({ iceServers: serveurs });
  flux.getTracks().forEach((t) => pc.addTrack(t, flux));
  const audio = racine.querySelector('[data-audio]');
  pc.ontrack = (e) => { audio.srcObject = e.streams[0]; audio.play().catch(() => {}); };
  pc.onicecandidate = (e) => { if (e.candidate) envoyer('candidat', e.candidate.toJSON()).catch(() => {}); };
  pc.oniceconnectionstatechange = () => {
    if (['connected', 'completed'].includes(pc.iceConnectionState)) clearTimeout(minuteurEchec);
    if (pc.iceConnectionState === 'failed' && enLigne) raccrocher(); // réseau perdu en pleine conversation
  };
  const offre = await pc.createOffer();
  await pc.setLocalDescription(offre);
  try {
    await envoyer('offre', { type: offre.type, sdp: offre.sdp });
  } catch {
    return terminer(); // l'appel n'est plus actif (occupé, expiré) : la page dit pourquoi
  }
  boucle();
}

async function raccrocher() {
  if (fini) return;
  fini = true;
  try { await api(url('raccrocher'), { methode: 'POST' }); } catch { /* le serveur clora l'appel de lui-même */ }
  nettoyer();
  location.replace(`/appel/${encodeURIComponent(id)}`);
}
racine.querySelector('[data-raccrocher]').addEventListener('click', raccrocher);

// Fermer l'onglet raccroche (sendBeacon : le navigateur l'envoie même pendant le déchargement)
addEventListener('pagehide', () => {
  if (fini) return;
  const f = new FormData();
  f.append('_csrf', csrf());
  navigator.sendBeacon(url('raccrocher'), f);
});

// ---------- Commandes ----------
const micro = racine.querySelector('[data-commande="micro"]');
micro.addEventListener('click', () => {
  const piste = flux?.getAudioTracks()[0];
  if (!piste) return;
  piste.enabled = !piste.enabled;
  const coupe = !piste.enabled;
  micro.classList.toggle('commande-appel--active', coupe);
  micro.setAttribute('aria-pressed', String(coupe));
  micro.querySelector('[data-libelle]').textContent = coupe ? 'Micro coupé' : 'Micro';
  micro.querySelector('use').setAttribute('href', sprite(coupe ? 'micro-barre' : 'micro'));
});

// Haut-parleur : les navigateurs de téléphone ne laissent pas choisir entre l'écouteur et le haut-parleur ;
// là où setSinkId existe (ordinateur, certains Android), on passe d'une sortie audio à l'autre.
const hautParleur = racine.querySelector('[data-commande="haut-parleur"]');
(async () => {
  const audio = racine.querySelector('[data-audio]');
  let sorties = [];
  try { sorties = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'audiooutput'); } catch { /* pas de liste */ }
  if (!audio.setSinkId || sorties.length < 2) {
    hautParleur.disabled = true;
    hautParleur.title = 'Pas disponible sur ce téléphone : réglez le volume avec les boutons.';
    return;
  }
  let courant = 0;
  hautParleur.addEventListener('click', async () => {
    courant = (courant + 1) % sorties.length;
    try { await audio.setSinkId(sorties[courant].deviceId); } catch { toast('Cette sortie audio n’a pas pu être choisie.', { icone: 'triangle' }); return; }
    hautParleur.classList.toggle('commande-appel--active', courant !== 0);
    hautParleur.setAttribute('aria-pressed', String(courant !== 0));
  });
})();

demarrer();
