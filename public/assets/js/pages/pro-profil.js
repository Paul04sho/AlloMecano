// M4 · Mon profil : la mini-carte de la zone, avec le cercle du rayon et le repère « Votre atelier » (le visage du mécano dans le trou).
// Sans JavaScript la carte reste son fond de couleur : la phrase de la zone, au-dessus, dit la même chose.
const bloc = document.querySelector('[data-carte-zone]');
if (bloc) {
  const el = bloc.querySelector('[data-carte]');
  const lat = Number(el.dataset.lat);
  const lon = Number(el.dataset.lon);
  const rayonM = Number(bloc.dataset.rayon) * 1000;
  import('../composants/carte.js').then(({ creerCarte }) => {
    const L = window.L;
    const carte = creerCarte(el, { dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, boxZoom: false, keyboard: false });
    const encre = getComputedStyle(document.documentElement).getPropertyValue('--encre').trim();
    const cercle = L.circle([lat, lon], { radius: rayonM, color: encre, weight: 1.5, opacity: .6, dashArray: '4 4', fillColor: encre, fillOpacity: .06, interactive: false }).addTo(carte);
    const gabarit = bloc.querySelector('template[data-repere]');
    L.marker([lat, lon], { interactive: false, keyboard: false, icon: L.divIcon({ html: gabarit.content.firstElementChild.cloneNode(true), className: 'repere-icone', iconSize: [56, 82], iconAnchor: [28, 82] }) }).addTo(carte);
    carte.fitBounds(cercle.getBounds(), { padding: [12, 12], animate: false }); // tout le rayon tient dans la carte
  }).catch(() => { /* sans carte, la fiche reste complète */ });
}
