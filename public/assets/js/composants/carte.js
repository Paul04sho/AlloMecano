// Carte (Fond de carte 56:736) : Leaflet + tuiles Protomaps servies par nous, couleurs carte/* du thème.
// Leaflet (window.L) et protomaps-leaflet (window.protomapsL) sont chargés avant, en scripts classiques.

const ABIDJAN = [[5.10, -4.50], [5.70, -3.65]];

const jeton = (nom) => getComputedStyle(document.documentElement).getPropertyValue(nom).trim();

// Clés de couleur d'une palette Protomaps (protomaps-leaflet 5.1, « Flavor »)
const CLES = 'background earth park_a park_b hospital industrial school wood_a wood_b pedestrian scrub_a scrub_b glacier sand beach aerodrome runway water zoo military tunnel_other_casing tunnel_minor_casing tunnel_link_casing tunnel_major_casing tunnel_highway_casing tunnel_other tunnel_minor tunnel_link tunnel_major tunnel_highway pier buildings minor_service_casing minor_casing link_casing major_casing_late highway_casing_late other minor_service minor_a minor_b link major_casing_early major highway_casing_early highway railway boundaries bridges_other_casing bridges_minor_casing bridges_link_casing bridges_major_casing bridges_highway_casing bridges_other bridges_minor bridges_link bridges_major bridges_highway roads_label_minor roads_label_minor_halo roads_label_major roads_label_major_halo ocean_label subplace_label subplace_label_halo city_label city_label_halo state_label state_label_halo country_label address_label address_label_halo'.split(' ');

/** Palette recolorée avec nos jetons carte/* (désaturée, comme le Fond de carte du Figma) : sol, îlots, routes, eau. */
function palette() {
  const sol = jeton('--carte-sol'), ilot = jeton('--carte-ilot'), route = jeton('--carte-route'), eau = jeton('--carte-eau');
  const texte = jeton('--encre-2'), filet = jeton('--filet');
  const f = {};
  for (const k of CLES) {
    if (/_label_halo$/.test(k)) f[k] = sol;
    else if (/_label$/.test(k)) f[k] = texte;
    else if (/casing/.test(k)) f[k] = sol;
    else if (/^(tunnel_|bridges_)?(other|minor_service|minor_a|minor_b|minor|link|major|highway)$/.test(k)) f[k] = route;
    else if (k === 'water') f[k] = eau;
    else if (k === 'railway' || k === 'boundaries') f[k] = filet;
    else if (k === 'background' || k === 'earth') f[k] = sol;
    else f[k] = ilot; // bâtiments, parcs, écoles… : îlots
  }
  const neutre = Object.fromEntries('blue green lapis pink red slategray tangerine turquoise'.split(' ').map((k) => [k, texte]));
  f.pois = neutre;
  f.landcover = Object.fromEntries('grassland barren urban_area farmland glacier scrub forest'.split(' ').map((k) => [k, sol]));
  return f;
}

function couche(source) {
  const P = window.protomapsL, f = palette();
  return P.leafletLayer({ url: source, paintRules: P.paintRules(f), labelRules: P.labelRules(f, 'fr'), backgroundColor: f.background, maxDataZoom: 15 }); // l'archive Protomaps s'arrête au zoom 15, au-delà on agrandit
}

/** Crée la carte dans un élément [data-carte] ; renvoie l'objet Leaflet. */
export function creerCarte(el, options = {}) {
  const L = window.L;
  const carte = L.map(el, {
    center: [Number(el.dataset.lat), Number(el.dataset.lon)], zoom: Number(el.dataset.zoom || 16),
    minZoom: 11, maxZoom: 18, maxBounds: ABIDJAN, maxBoundsViscosity: 1,
    zoomControl: false, attributionControl: false, ...options,
  });
  let fond = couche(el.dataset.source).addTo(carte);
  // Le thème change (préférence du système ou choix dans le pied de page) : on repeint le fond
  const repeindre = () => { carte.removeLayer(fond); fond = couche(el.dataset.source).addTo(carte); };
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', repeindre);
  new MutationObserver(repeindre).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  return carte;
}
