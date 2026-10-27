// C4b · Chargement (73:942) : « Moment 1 · Le voyant cherche ». Le radar tourne pendant la recherche,
// tout s'arrête à l'arrivée des résultats ou au bout de 20 s.
import { api } from '../core/app.js';
import { creerCarte } from '../composants/carte.js';

const racine = document.querySelector('[data-recherche]');
const radar = racine.querySelector('[data-radar]');
const position = [Number(racine.dataset.lat), Number(racine.dataset.lon)];
const precision = Number(racine.dataset.precision) || 0;
const L = window.L;

const carte = creerCarte(racine.querySelector('[data-carte]'), { dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, keyboard: false });
const encre = getComputedStyle(document.documentElement).getPropertyValue('--encre').trim();
if (precision > 0) L.circle(position, { radius: precision, color: encre, weight: 1.5, opacity: .4, dashArray: '4 4', fillColor: encre, fillOpacity: .06, interactive: false }).addTo(carte);
const vous = racine.querySelector('template[data-vous]').innerHTML;
L.marker(position, { icon: L.divIcon({ html: vous, className: 'repere-icone', iconSize: [24, 24], iconAnchor: [12, 12] }), interactive: false, keyboard: false }).addTo(carte);

// Le radar suit le point « Vous » ; sur mobile, la carte visible s'arrête au-dessus de la feuille
const decalage = () => (matchMedia('(max-width: 1023.98px)').matches ? innerHeight * 0.56 / 2 : 0);
carte.panBy([0, decalage()], { animate: false });
const placerRadar = () => {
  const p = carte.latLngToContainerPoint(position);
  radar.style.left = `${p.x}px`;
  radar.style.top = `${p.y}px`;
};
placerRadar();
carte.on('move zoom resize', placerRadar);

const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;

function arreter() {
  radar.classList.add('arrete');
  racine.classList.add('recherche--arretee');
  racine.querySelector('.vous-etes-ici')?.classList.remove('vous-etes-ici--recherche');
}
const limite = setTimeout(arreter, 20000);

try {
  const r = await api('/api/recherches', { methode: 'POST', donnees: {} });
  clearTimeout(limite);
  arreter();
  location.replace(r.url);
} catch (e) {
  clearTimeout(limite);
  arreter();
  const feuille = racine.querySelector('.feuille__interieur');
  const note = document.createElement('div');
  note.className = 'note note--erreur';
  note.setAttribute('role', 'alert');
  note.innerHTML = '<svg class="icone" width="24" height="24" aria-hidden="true"><use href="/assets/icones/sprite.svg#i-triangle"></use></svg><div></div>';
  note.querySelector('div').textContent = 'La recherche n’a pas abouti. Vérifiez le réseau.';
  const reessayer = document.createElement('button');
  reessayer.type = 'button';
  reessayer.className = 'bouton bouton--principal bouton--plein';
  reessayer.innerHTML = '<span class="bouton__libelle">Réessayer</span>';
  reessayer.addEventListener('click', () => location.reload());
  // Message : les squelettes s'effacent en 120 ms, puis le message monte de 8 px (240 ms, prototype C4b)
  const squelettes = racine.querySelector('.recherche__squelettes');
  if (!reduit) await squelettes.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 120, fill: 'forwards' }).finished.catch(() => {});
  squelettes.replaceWith(note, reessayer);
  if (!reduit) [note, reessayer].forEach((el) => el.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }], { duration: 240, easing: 'cubic-bezier(.2, .8, .2, 1)' }));
  racine.querySelector('.feuille')?.removeAttribute('aria-busy');
  feuille.querySelector('h1').textContent = 'Recherche interrompue';
}
