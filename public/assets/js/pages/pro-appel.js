// M5 · Appel intégré, côté mécano : il sonne (RG-A5, 30 s au plus), on décroche (réponse WebRTC, RG-A6), on raccroche, puis « J'y vais » ou « Pas pour moi » (RG-I2, RG-D4).
// Même protocole que le client (js/pages/appel.js) : signalisation par interrogation, 1 s pendant la connexion, 3 s pendant l'appel (RG-A8).
import { api, chargement, csrf, toast } from '../core/app.js';

const racine = document.querySelector('[data-appel-pro]');
const id = racine.dataset.contact;
const echecS = Number(racine.dataset.echec) || 15; // RG-A7 : audio impossible après 15 s
const url = (chemin) => `/pro/api/appels/${encodeURIComponent(id)}/${chemin}`;
const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const sprite = (nom) => document.querySelector('.icone use')?.getAttribute('href')?.replace(/#.*$/, '') + '#i-' + nom;
const tous = (selecteur) => [...racine.querySelectorAll(selecteur)];

let pc = null;
let flux = null;
let verrou = null;
let dernier = 0;
let enLigne = false;
let fini = racine.dataset.phase === 'suite'; // page rouverte après l'appel : il ne reste que la réponse à donner
let remoteOk = false;
let enCours = false; // « Décrocher » a été pressé
let candidatsEnAttente = [];
let minuteurEchec = null;
let depart = Date.now() - (Number(racine.dataset.depuis) || 0) * 1000;
let departLigne = null;

// ---------- Affichage ----------
const deux = (n) => String(n).padStart(2, '0');
const formatSonne = (s) => `${Math.floor(s / 60)}:${deux(s % 60)}`;
const formatLigne = (s) => `${deux(Math.floor(s / 60))}:${deux(s % 60)}`;
const duree = (s) => (s < 60 ? `${s} s` : `${Math.floor(s / 60)} min${s % 60 ? ' ' + (s % 60) : ''}`);

setInterval(() => {
  if (fini) return;
  if (enLigne && departLigne !== null) tous('[data-phase-ligne] [data-chrono]').forEach((c) => { c.textContent = formatLigne(Math.floor((Date.now() - departLigne) / 1000)); });
  else tous('[data-phase-sonne] [data-chrono]').forEach((c) => { c.textContent = formatSonne(Math.floor((Date.now() - depart) / 1000)); });
}, 250);

function montrer(phase) {
  racine.dataset.phase = phase;
  tous('[data-phase-sonne]').forEach((el) => { el.hidden = phase !== 'entrant'; });
  tous('[data-phase-ligne]').forEach((el) => { el.hidden = phase !== 'ligne'; });
  racine.classList.toggle('appel-pro--clair', phase === 'suite');
  racine.classList.toggle('appel-pro--sombre', phase !== 'suite');
  racine.classList.toggle('sur-sombre', phase !== 'suite');
  racine.querySelector('[data-vue="appel"]').hidden = phase === 'suite';
  racine.querySelector('[data-vue="suite"]').hidden = phase !== 'suite';
  if (phase === 'suite') scrollTo(0, 0);
  window.__carteClient?.invalidateSize();
}

// ---------- Sonnerie : vibreur et petit signal sonore, tant que ça sonne et que la page est ouverte ----------
let ctxAudio = null;
function sonner() {
  if (enCours || fini || racine.dataset.phase !== 'entrant') return;
  navigator.vibrate?.([400, 200, 400]);
  try {
    ctxAudio ??= new AudioContext();
    const o = ctxAudio.createOscillator();
    const g = ctxAudio.createGain();
    o.frequency.value = 880;
    g.gain.value = 0.12;
    o.connect(g).connect(ctxAudio.destination);
    o.start();
    o.stop(ctxAudio.currentTime + 0.35);
  } catch { /* son bloqué tant que la page n'a pas reçu de geste : le vibreur et la notification suffisent */ }
}
let sonnerie = null;
if (racine.dataset.phase === 'entrant') { sonner(); sonnerie = setInterval(sonner, 2000); }
const taire = () => { clearInterval(sonnerie); navigator.vibrate?.(0); };

// ---------- Fin ----------
function nettoyer() {
  taire();
  clearTimeout(minuteurEchec);
  flux?.getTracks().forEach((t) => t.stop());
  pc?.close();
  pc = null;
  remoteOk = false;
  verrou?.release?.().catch(() => {});
}

/** Raccroché (par l'un ou l'autre) : « Vous y allez ? ». Le temps d'appel est celui de la conversation. */
function termine() {
  if (fini) return;
  const etaitEnLigne = enLigne;
  fini = true;
  enLigne = false;
  nettoyer();
  if (!etaitEnLigne) { // jamais décroché : client parti, appel manqué
    toast('L’appel s’est terminé.', { icone: 'info' });
    setTimeout(() => location.replace('/pro'), 900);
    return;
  }
  const s = Math.max(1, Math.round((Date.now() - departLigne) / 1000));
  const heure = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  racine.querySelector('[data-resume-fin]').textContent = `Appel terminé · ${duree(s)} · ${heure}`;
  montrer('suite');
}

// ---------- Signalisation ----------
const envoyer = (type, charge = null) => api(url('signal'), { methode: 'POST', donnees: { type, charge } });

async function boucle() {
  while (!fini) {
    try {
      const r = await api(`${url('signaux')}?apres=${dernier}`);
      dernier = r.dernier;
      depart = Date.now() - r.depuis_s * 1000;
      for (const s of r.signaux) {
        if (s.type === 'candidat') { if (remoteOk) await pc.addIceCandidate(s.charge).catch(() => {}); else candidatsEnAttente.push(s.charge); }
        if (s.type === 'raccroche') return termine();
      }
      if (r.fin) return termine();
    } catch (e) {
      if (e.statut === 404) return termine();
    }
    await new Promise((ok) => setTimeout(ok, enLigne ? 3000 : 1000));
  }
}

// ---------- Décrocher ----------
/** Un vrai micro ; sur un poste de développement, ?silence=1 donne une piste silencieuse (pas d'invite). */
async function micro() {
  if (racine.hasAttribute('data-dev') && new URLSearchParams(location.search).get('silence') === '1') {
    const ctx = new AudioContext();
    const dest = ctx.createMediaStreamDestination();
    ctx.createOscillator().connect(dest);
    return dest.stream;
  }
  return navigator.mediaDevices.getUserMedia({ audio: true });
}

async function decrocher(bouton) {
  if (enCours || fini) return;
  enCours = true;
  taire();
  chargement(bouton, true);
  try { verrou = await navigator.wakeLock?.request('screen'); } catch { /* écran non verrouillable : sans importance */ }
  try {
    flux = await micro();
  } catch {
    enCours = false;
    chargement(bouton, false);
    if (!fini) sonnerie = setInterval(sonner, 2000);
    return toast('Autorisez le micro pour décrocher.', { icone: 'triangle' });
  }
  try {
    // L'offre du client est dans la signalisation
    let offre = null;
    for (let i = 0; i < 20 && !offre; i++) {
      const r = await api(`${url('signaux')}?apres=0`);
      offre = r.signaux.find((s) => s.type === 'offre');
      for (const s of r.signaux) dernier = Math.max(dernier, s.id);
      if (r.fin) return termine();
      if (!offre) await new Promise((ok) => setTimeout(ok, 500));
    }
    if (!offre) throw new Error('offre');
    let serveurs;
    try { serveurs = (await api('/pro/api/appels/ice')).iceServers; } catch { serveurs = [{ urls: ['stun:stun.cloudflare.com:3478'] }]; }
    pc = new RTCPeerConnection({ iceServers: serveurs });
    flux.getTracks().forEach((t) => pc.addTrack(t, flux));
    pc.ontrack = (e) => { const audio = racine.querySelector('[data-audio]'); audio.srcObject = e.streams[0]; audio.play().catch(() => {}); };
    pc.onicecandidate = (e) => { if (e.candidate) envoyer('candidat', e.candidate.toJSON()).catch(() => {}); };
    pc.oniceconnectionstatechange = () => {
      if (['connected', 'completed'].includes(pc.iceConnectionState)) clearTimeout(minuteurEchec);
      if (pc.iceConnectionState === 'failed' && enLigne) raccrocher(); // réseau perdu en pleine conversation
    };
    await pc.setRemoteDescription(offre.charge);
    remoteOk = true;
    for (const c of candidatsEnAttente) await pc.addIceCandidate(c).catch(() => {});
    candidatsEnAttente = [];
    const reponse = await pc.createAnswer();
    await pc.setLocalDescription(reponse);
    await envoyer('reponse', { type: reponse.type, sdp: reponse.sdp }); // RG-A6 : décroché
  } catch {
    nettoyer();
    enCours = false;
    chargement(bouton, false);
    return toast('Impossible de décrocher : l’appel n’est plus en cours.', { icone: 'triangle' });
  }
  enLigne = true;
  departLigne = Date.now();
  if (!reduit) racine.animate([{ opacity: .6 }, { opacity: 1 }], { duration: 200, easing: 'cubic-bezier(.2, .8, .2, 1)' });
  montrer('ligne');
  // RG-A7 : si l'audio ne passe pas dans les 15 s, on raccroche
  minuteurEchec = setTimeout(() => { if (!['connected', 'completed'].includes(pc?.iceConnectionState)) { toast('La connexion audio a échoué.', { icone: 'triangle' }); raccrocher(); } }, echecS * 1000);
}

async function raccrocher() {
  if (fini) return;
  try { await envoyer('raccroche'); } catch { /* le serveur clora l'appel de lui-même */ }
  termine();
}

async function refuser() {
  if (fini) return;
  fini = true;
  nettoyer();
  try { await envoyer('refus'); } catch { /* idem */ }
  location.replace('/pro');
}

racine.querySelector('[data-decrocher]')?.addEventListener('click', (e) => decrocher(e.currentTarget));
racine.querySelector('[data-refuser]')?.addEventListener('click', refuser);
racine.querySelector('[data-raccrocher]')?.addEventListener('click', raccrocher);

// Fermer l'onglet en pleine conversation raccroche (sendBeacon : le navigateur l'envoie même pendant le déchargement)
addEventListener('pagehide', () => {
  if (fini || !enLigne) return;
  const f = new FormData();
  f.append('_csrf', csrf());
  f.append('type', 'raccroche');
  navigator.sendBeacon(url('signal'), f);
});

// ---------- Commandes ----------
const microBtn = racine.querySelector('[data-commande="micro"]');
microBtn?.addEventListener('click', () => {
  const piste = flux?.getAudioTracks()[0];
  if (!piste) return;
  piste.enabled = !piste.enabled;
  const coupe = !piste.enabled;
  microBtn.classList.toggle('commande-appel--active', coupe);
  microBtn.setAttribute('aria-pressed', String(coupe));
  microBtn.querySelector('[data-libelle]').textContent = coupe ? 'Micro coupé' : 'Micro';
  microBtn.querySelector('use').setAttribute('href', sprite(coupe ? 'micro-barre' : 'micro'));
});

// Haut-parleur : les navigateurs de téléphone ne laissent pas choisir entre l'écouteur et le haut-parleur ;
// là où setSinkId existe (ordinateur, certains Android), on passe d'une sortie audio à l'autre.
const hautParleur = racine.querySelector('[data-commande="haut-parleur"]');
if (hautParleur) {
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
}

// ---------- Vous y allez ? ----------
tous('[data-issue]').forEach((b) => b.addEventListener('click', async () => {
  if (b.getAttribute('aria-busy') === 'true') return;
  chargement(b, true);
  try {
    const r = await api(url('issue'), { methode: 'POST', donnees: { reponse: b.dataset.issue } }); // RG-I2 : le client voit la réponse aussitôt
    location.replace(r.suite || '/pro');
  } catch (e) {
    chargement(b, false);
    toast(e.json?.message || 'Votre réponse n’est pas partie. Réessayez.', { icone: 'triangle' });
  }
}));

// ---------- Mini-carte du client : repère, cercle de précision ----------
const bloc = racine.querySelector('[data-carte-client]');
if (bloc) {
  const el = bloc.querySelector('[data-carte]');
  const lat = Number(el.dataset.lat);
  const lon = Number(el.dataset.lon);
  const rayon = Math.max(15, Number(bloc.dataset.precision) || 25);
  // Le zoom qui donne un cercle d'environ 64 px de large, comme sur la maquette
  el.dataset.zoom = String(Math.max(14, Math.min(18, Math.round(Math.log2((64 * 156543.03 * Math.cos(lat * Math.PI / 180)) / (2 * rayon))))));
  import('../composants/carte.js').then(({ creerCarte }) => {
    const L = window.L;
    const carte = creerCarte(el, { dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, boxZoom: false, keyboard: false });
    const gabarit = bloc.querySelector('template[data-repere]');
    L.marker([lat, lon], { interactive: false, keyboard: false, icon: L.divIcon({ html: gabarit.content.firstElementChild.cloneNode(true), className: 'repere-icone', iconSize: [36, 49], iconAnchor: [18, 49] }) }).addTo(carte);
    const encre = getComputedStyle(document.documentElement).getPropertyValue('--encre').trim();
    L.circle([lat, lon], { radius: rayon, color: encre, weight: 1.5, opacity: .5, dashArray: '4 4', fillColor: encre, fillOpacity: .08, interactive: false }).addTo(carte);
    window.__carteClient = carte;
  }).catch(() => { /* sans carte, l'appel marche quand même */ });
}

if (!fini) boucle();
