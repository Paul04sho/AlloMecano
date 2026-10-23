// C2a · Où êtes-vous ? (66:437, 198:1963) : demande la position, affiche la précision qui s'affine,
// puis envoie le point en POST (jamais dans l'URL, RG-P1).
import { chargement } from '../core/app.js';

const racine = document.querySelector('[data-position-gps]');
const formulaire = racine.querySelector('[data-formulaire-gps]');
const bouton = racine.querySelector('[data-utiliser-gps]');
const commune = racine.querySelector('[data-choisir-commune]');
const aide = racine.querySelector('[data-aide]');
const precision = racine.querySelector('[data-precision]');
const note = racine.querySelector('[data-note-refus]');
const voyant = racine.querySelector('.ecrou-repere__voyant');
const halo = racine.querySelector('[data-halo]');

const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const POSE = 'cubic-bezier(.2,.9,.25,1.15)', RADAR = 'cubic-bezier(.2,.6,.3,1)', SORTIE = 'cubic-bezier(.2,.8,.2,1)';
const duree = (ms) => (reduit ? 1 : ms);
const NB = ' ';
const BONNE_PRECISION_M = 30;   // on n'attend pas mieux
const AFFINAGE_MS = 5000;       // au plus 5 s d'affinage après la première mesure
const DELAI_MS = 12000;         // sans aucune mesure : on abandonne

let suivi = null, meilleure = null, minuterieAffinage = null, minuterieDelai = null;

// « Choisir ma commune » devient le principal (un seul jaune par écran)
function inverserBoutons() {
  bouton.classList.replace('bouton--principal', 'bouton--contour');
  commune.classList.replace('bouton--contour', 'bouton--principal');
}

function echec(message) {
  arreter();
  chargement(bouton, false);
  precision.hidden = true;
  if (message) note.querySelector('.note > div').innerHTML = message;
  note.hidden = false;
  note.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: duree(240), easing: SORTIE });
  inverserBoutons();
  commune.focus();
}

function arreter() {
  if (suivi !== null) navigator.geolocation.clearWatch(suivi);
  clearTimeout(minuterieAffinage); clearTimeout(minuterieDelai);
  suivi = null;
}

// Moment 2 · On vous a trouvé : le voyant s'allume dans le trou, un halo unique, puis on passe à la carte
function trouve(p) {
  arreter();
  voyant.removeAttribute('hidden');
  voyant.animate([{ opacity: 0, transform: 'scale(0)' }, { opacity: 1, transform: 'scale(1)' }], { duration: duree(240), easing: POSE, fill: 'both' });
  halo.animate([{ opacity: .5, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(2.6)' }], { delay: duree(120), duration: duree(600), easing: RADAR });
  formulaire.lat.value = p.coords.latitude.toFixed(6);
  formulaire.lon.value = p.coords.longitude.toFixed(6);
  formulaire.precision.value = Math.round(p.coords.accuracy);
  setTimeout(() => formulaire.submit(), reduit ? 0 : 760);
}

function mesure(p) {
  if (!meilleure || p.coords.accuracy < meilleure.coords.accuracy) meilleure = p;
  precision.hidden = false;
  precision.textContent = `Précision ±${NB}${Math.round(meilleure.coords.accuracy)}${NB}m`;
  if (meilleure.coords.accuracy <= BONNE_PRECISION_M) return trouve(meilleure);
  if (!minuterieAffinage) minuterieAffinage = setTimeout(() => trouve(meilleure), AFFINAGE_MS);
}

bouton?.addEventListener('click', () => {
  if (!('geolocation' in navigator)) return echec('<strong>Localisation indisponible.</strong> Pas grave : choisissez votre commune, ça marche aussi.');
  chargement(bouton, true);
  aide.hidden = true; note.hidden = true; meilleure = null; minuterieAffinage = null;
  suivi = navigator.geolocation.watchPosition(mesure, (err) => {
    if (meilleure) return trouve(meilleure);
    echec(err.code === err.PERMISSION_DENIED ? null : '<strong>Position introuvable.</strong> Pas grave : choisissez votre commune, ça marche aussi.');
  }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
  minuterieDelai = setTimeout(() => (meilleure ? trouve(meilleure) : echec('<strong>Position introuvable.</strong> Pas grave : choisissez votre commune, ça marche aussi.')), DELAI_MS);
});
