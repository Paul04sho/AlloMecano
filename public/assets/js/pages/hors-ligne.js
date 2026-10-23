// X7 · Hors ligne : les 3 derniers mécanos appelés (gardés sur l'appareil), un appel direct sans internet, et une nouvelle tentative toutes les 5 s.
const racine = document.querySelector('[data-hors-ligne]');
if (racine) {
  const liste = racine.querySelector('[data-derniers]');
  const lignes = racine.querySelector('[data-derniers-lignes]');
  const vide = racine.querySelector('[data-derniers-vide]');
  const modele = document.querySelector('[data-modele-ligne]');

  const lire = () => {
    try { return JSON.parse(localStorage.getItem('derniers_mecanos') || '[]').filter((m) => m && m.nom && /^\+225\d{10}$/.test(m.telephone)); } catch { return []; }
  };
  const national = (tel) => `+225 ${tel.slice(4).match(/\d{2}/g).join(' ')}`;
  const heure = (iso) => new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Abidjan' });

  const afficher = () => {
    const mecanos = lire();
    lignes.replaceChildren();
    liste.hidden = mecanos.length === 0;
    vide.hidden = mecanos.length > 0;
    mecanos.forEach((m) => {
      const l = modele.content.firstElementChild.cloneNode(true);
      l.querySelector('[data-nom]').textContent = m.nom;
      l.querySelector('[data-telephone]').textContent = national(m.telephone);
      l.querySelector('[data-heure]').textContent = heure(m.vu);
      l.querySelector('[data-appel]').href = `tel:${m.telephone}`;
      l.querySelector('[data-appel-nom]').textContent = `Appeler ${m.nom.split(' ')[0]}`;
      const img = l.querySelector('img');
      if (m.portrait && m.portrait.startsWith('/')) { img.src = m.portrait; img.hidden = false; }
      lignes.append(l);
    });
    if (mecanos.length) {
      const premier = racine.querySelector('[data-appeler-premier]');
      premier.href = `tel:${mecanos[0].telephone}`;
      racine.querySelector('[data-appeler-libelle]').textContent = `Appeler ${mecanos[0].nom.split(' ')[0]}`;
      racine.querySelector('[data-derniers-titre]').textContent = `Vos ${mecanos.length} dernier${mecanos.length > 1 ? 's' : ''} mécano${mecanos.length > 1 ? 's' : ''}`;
    }
  };
  afficher();

  racine.querySelector('[data-effacer]').addEventListener('click', () => {
    try { localStorage.removeItem('derniers_mecanos'); } catch { /* rien à effacer */ }
    afficher();
  });

  // Nouvelle tentative automatique : dès que le serveur répond, on revient là où l'on était
  const essayer = async () => {
    try {
      const r = await fetch('/api/ping', { cache: 'no-store' });
      if (r.ok) location.pathname === '/hors-ligne' ? location.assign('/') : location.reload();
    } catch { /* toujours pas de réseau */ }
  };
  setInterval(essayer, 5000);
  addEventListener('online', essayer);
}
