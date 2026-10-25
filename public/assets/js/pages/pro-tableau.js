// Espace pro · M2 : l'interrupteur de disponibilité (RG-D1), la mise à jour de la position (RG-D2), l'envoi d'une photo (M2a, M2b).
// Amélioration progressive : sans JavaScript, les pièces s'envoient par le formulaire ; l'interrupteur demande le navigateur (position).
import { api, chargement, csrf, toast } from '../core/app.js';

const racine = document.querySelector('[data-tableau]');

/** La position du navigateur, ou une phrase qui dit quoi faire. */
function lirePosition() {
  return new Promise((resolu, refuse) => {
    if (!('geolocation' in navigator)) return refuse(new Error('Votre téléphone ne donne pas sa position. Essayez depuis un autre navigateur.'));
    navigator.geolocation.getCurrentPosition(
      (p) => resolu({ lat: p.coords.latitude, lon: p.coords.longitude, precision: Math.round(p.coords.accuracy) }),
      (err) => refuse(new Error(err.code === 1 ? 'La localisation est refusée. Autorisez-la dans votre navigateur, puis réessayez.' : 'Votre position n’a pas pu être lue. Sortez à l’air libre, puis réessayez.')),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  });
}

const message = (e) => e?.json?.erreur ?? e?.message ?? 'La demande n’a pas abouti.';

/** Applique la réponse du serveur : l'interrupteur en fondu croisé de 200 ms, la ligne de position, les bornes. */
function appliquer(r) {
  const conteneur = document.querySelector('[data-interrupteur-conteneur]');
  if (conteneur) {
    conteneur.innerHTML = r.html;
    conteneur.firstElementChild?.classList.add('interrupteur-dispo--fondu');
  }
  const position = document.querySelector('[data-position]');
  if (position) position.textContent = r.position;
  const action = document.querySelector('[data-position-action]');
  if (action) action.hidden = r.etat === 'indisponible';
  const bornes = document.querySelector('[data-bornes]');
  if (bornes && r.bornes_html) bornes.innerHTML = r.bornes_html;
  navigator.vibrate?.(30);
}

if (racine) {
  document.addEventListener('click', async (e) => {
    const bouton = e.target.closest('[data-dispo], [data-maj-position], [data-libre]');
    if (!bouton || bouton.getAttribute('aria-busy') === 'true') return;
    const interrupteur = bouton.matches('[data-dispo]');
    const dispo = bouton.getAttribute('aria-checked') === 'true';
    chargement(bouton, true);
    try {
      if (bouton.matches('[data-libre]')) { // RG-D4 : « Je suis libre »
        appliquer(await api('/pro/api/disponibilite', { methode: 'POST', donnees: { etat: 'libre' } }));
      } else if (interrupteur && dispo) {
        appliquer(await api('/pro/api/disponibilite', { methode: 'POST', donnees: { etat: 'indisponible' } }));
      } else {
        const position = await lirePosition();
        const r = await api(interrupteur ? '/pro/api/disponibilite' : '/pro/api/position', { methode: 'POST', donnees: interrupteur ? { etat: 'disponible', ...position } : position });
        appliquer(r);
        if (!interrupteur) toast('Position mise à jour.', { icone: 'coche' });
      }
    } catch (erreur) {
      toast(message(erreur));
      chargement(bouton, false);
    }
  });
}

// ---------- Envoi d'une photo : part tout seul au choix du fichier ----------
document.addEventListener('change', async (e) => {
  const champ = e.target.closest('[data-reprise-champ]');
  if (!champ?.files?.length) return;
  const form = champ.closest('form');
  const etiquette = form.querySelector('.bouton');
  form.classList.add('reprise-piece--envoi');
  etiquette.dataset.chargement = 'Envoi…';
  chargement(etiquette, true);
  try {
    const reponse = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-CSRF': csrf() }, credentials: 'same-origin' });
    const json = await reponse.json().catch(() => ({}));
    if (!reponse.ok || !json.ok) throw new Error(json.erreur || 'La photo n’est pas arrivée. Réessayez.');
    toast(json.renvoye ? 'Votre dossier repart chez l’équipe.' : 'Photo envoyée.', { icone: 'coche' });
    setTimeout(() => location.reload(), 900);
  } catch (erreur) {
    toast(erreur.message);
    chargement(etiquette, false);
    form.classList.remove('reprise-piece--envoi');
    champ.value = '';
  }
});
