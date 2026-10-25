// Service worker : quand le réseau manque, une navigation reçoit la page « Hors ligne » (X7) au lieu de l'erreur du navigateur.
// La page et ses feuilles de style, scripts et polices sont gardés à l'installation ; le reste passe toujours par le réseau.
const VERSION = 'allomecano-hors-ligne-v1';
const PAGE = '/hors-ligne';

self.addEventListener('install', (evenement) => {
  evenement.waitUntil((async () => {
    const cache = await caches.open(VERSION);
    const reponse = await fetch(PAGE, { cache: 'reload' });
    const html = await reponse.clone().text();
    await cache.put(PAGE, reponse);
    // Tout ce que la page charge : feuilles de style, modules, polices, icônes
    const liens = new Set([...html.matchAll(/(?:href|src)="(\/assets\/[^"]+)"/g)].map((m) => m[1]));
    await Promise.all([...liens].map((lien) => cache.add(lien).catch(() => {})));
    await self.skipWaiting();
  })());
});

self.addEventListener('activate', (evenement) => {
  evenement.waitUntil((async () => {
    for (const nom of await caches.keys()) if (nom !== VERSION) await caches.delete(nom);
    await self.clients.claim();
  })());
});

// Notifications push du mécano : « Appel allomécano » (RG-A5, renvoyée toutes les 5 s tant que ça sonne) et l'essai de sonnerie (M1f).
// Un appel reste affiché jusqu'à ce qu'on le touche ; toucher la notification ouvre l'écran d'appel (M5) dans l'onglet déjà ouvert, sinon dans un nouveau.
self.addEventListener('push', (evenement) => {
  let charge = {};
  try { charge = evenement.data.json(); } catch { /* notification sans contenu : on affiche le titre par défaut */ }
  const appel = Boolean(charge.contact);
  evenement.waitUntil(self.registration.showNotification(charge.titre || 'allomécano', {
    body: charge.texte || '',
    tag: charge.contact || 'allomecano',
    renotify: true,
    requireInteraction: appel,
    vibrate: appel ? [400, 200, 400, 200, 400] : [200],
    data: { url: charge.url || '/pro' },
  }));
});

self.addEventListener('notificationclick', (evenement) => {
  evenement.notification.close();
  const url = evenement.notification.data?.url || '/pro';
  evenement.waitUntil((async () => {
    for (const fenetre of await self.clients.matchAll({ type: 'window', includeUncontrolled: true })) {
      if (new URL(fenetre.url).origin === self.location.origin) {
        await fenetre.focus();
        return fenetre.navigate(url);
      }
    }
    return self.clients.openWindow(url);
  })());
});

self.addEventListener('fetch', (evenement) => {
  const requete = evenement.request;
  if (requete.method !== 'GET' || new URL(requete.url).origin !== self.location.origin) return;
  if (requete.mode === 'navigate') {
    evenement.respondWith(fetch(requete).catch(async () => (await caches.match(PAGE)) || Response.error()));
    return;
  }
  const chemin = new URL(requete.url).pathname;
  if (chemin.startsWith('/assets/') && !requete.headers.has('range')) {
    // Ressources de la page hors ligne : le réseau d'abord, le cache seulement s'il manque
    evenement.respondWith(fetch(requete).catch(async () => (await caches.match(requete)) || Response.error()));
  }
});
