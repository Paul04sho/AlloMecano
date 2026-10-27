// C2a · Confirmation de la position (67:462) : l'écrou reste fixe, la carte glisse dessous.
// Le nom du quartier et les coordonnées suivent en direct, sans réseau (liste des quartiers dans la page).
import { creerCarte } from '../composants/carte.js';

const formulaire = document.querySelector('[data-confirmer]');
const el = formulaire.querySelector('[data-carte]');
const ecrou = formulaire.querySelector('.confirmer__ecrou');
const bulle = formulaire.querySelector('[data-bulle]');
const ombre = formulaire.querySelector('[data-ombre]');
const nom = formulaire.querySelector('[data-nom]');
const coordonnees = formulaire.querySelector('[data-coordonnees]');
const autre = formulaire.querySelector('[data-autre-quartier]');
const quartiers = JSON.parse(formulaire.dataset.quartiers || '[]'); // [nom, lat, lon, commune]
const precisionM = Number(formulaire.dataset.precision) || 0;

const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const POSE = 'cubic-bezier(.2,.9,.25,1.15)', SORTIE = 'cubic-bezier(.2,.8,.2,1)';
const duree = (ms) => (reduit ? 1 : ms);
const NB = ' ';
const depart = { lat: Number(formulaire.lat.value), lon: Number(formulaire.lon.value) };

const carte = creerCarte(el);
// La pointe de l'écrou est 13 px au-dessus du centre de la carte : on décale la vue d'autant
const decalage = () => -parseFloat(getComputedStyle(formulaire).getPropertyValue('--decalage-repere') || '0');
const recentrer = () => {
  const z = carte.getZoom();
  carte.setView(carte.unproject(carte.project([depart.lat, depart.lon], z).add([0, decalage()]), z), z, { animate: false });
};
carte.whenReady(recentrer);
const sousLaPointe = () => {
  const t = carte.getSize();
  return carte.containerPointToLatLng([t.x / 2, t.y / 2 - decalage()]);
};

// Halo de précision : rayon = précision GPS réelle
const encre = getComputedStyle(document.documentElement).getPropertyValue('--encre').trim();
if (precisionM > 0) {
  window.L.circle([depart.lat, depart.lon], { radius: precisionM, color: encre, weight: 1.5, opacity: .45, dashArray: '4 4', fillColor: encre, fillOpacity: .07, interactive: false }).addTo(carte);
}

// Arrivée : l'écrou se pose, la bulle apparaît
ecrou.animate([{ transform: 'translateY(-16px) scale(.9)', opacity: 0 }, { transform: 'none', opacity: 1 }], { delay: duree(120), duration: duree(320), easing: POSE, fill: 'backwards' });
bulle.animate([{ opacity: 0 }, { opacity: 1 }], { duration: duree(240), easing: SORTIE, fill: 'backwards' });

const distanceM = (a, b, c, d) => {
  const r = Math.PI / 180, x = Math.sin((c - a) * r / 2) ** 2 + Math.cos(a * r) * Math.cos(c * r) * Math.sin((d - b) * r / 2) ** 2;
  return 6371000 * 2 * Math.asin(Math.min(1, Math.sqrt(x)));
};
const plusProche = (lat, lon) => quartiers.reduce((m, q) => { const d = distanceM(lat, lon, q[1], q[2]); return !m || d < m.d ? { q, d } : m; }, null)?.q;
const deg = (v) => Math.abs(v).toFixed(4).replace('.', ',') + '°' + NB;
const formater = (lat, lon, p) => `${deg(lat)}${lat >= 0 ? 'N' : 'S'} · ${deg(lon)}${lon >= 0 ? 'E' : 'O'}${p ? ` · ±${NB}${p}${NB}m` : ''}`.toUpperCase();

let premierGeste = true, souleve = false;
function soulever() {
  if (souleve) return;
  souleve = true;
  ecrou.animate([{ transform: 'none' }, { transform: 'translateY(-10px)' }], { duration: duree(160), easing: SORTIE, fill: 'forwards' });
  ombre?.animate([{ opacity: 0, transform: 'scale(.6)' }, { opacity: 1, transform: 'scale(1)' }], { duration: duree(160), easing: SORTIE, fill: 'forwards' });
  if (premierGeste) { premierGeste = false; bulle.animate([{ opacity: 1 }, { opacity: 0 }], { duration: duree(160), fill: 'forwards' }); }
}
function reposer() {
  if (!souleve) return;
  souleve = false;
  ecrou.animate([{ transform: 'translateY(-10px)' }, { transform: 'translateY(2px)', offset: .7 }, { transform: 'none' }], { duration: duree(240), easing: POSE, fill: 'forwards' });
  ombre?.animate([{ opacity: 1, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(.6)' }], { duration: duree(200), fill: 'forwards' });
}

function suivre() {
  const p = sousLaPointe();
  const bouge = distanceM(depart.lat, depart.lon, p.lat, p.lng) > 3;
  formulaire.lat.value = p.lat.toFixed(6);
  formulaire.lon.value = p.lng.toFixed(6);
  formulaire.deplace.value = bouge ? '1' : '0'; // RG-R1 : point déplacé sur la carte
  coordonnees.textContent = formater(p.lat, p.lng, bouge ? null : precisionM || null);
  const q = plusProche(p.lat, p.lng);
  if (q && q[0] !== nom.textContent) {
    // Nouveau quartier : fondu croisé du nom (80 + 160 ms)
    nom.animate([{ opacity: 1 }, { opacity: 0 }], { duration: duree(80) }).finished.then(() => {
      nom.textContent = q[0];
      nom.animate([{ opacity: 0 }, { opacity: 1 }], { duration: duree(160) });
    });
    autre.href = `/position/quartier?commune=${encodeURIComponent(q[3])}`;
  }
}

carte.on('dragstart zoomstart', soulever);
carte.on('move', suivre);
carte.on('moveend', () => { reposer(); suivre(); });
