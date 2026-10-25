// M1f · Activer les appels : installer l'app, autoriser les notifications (abonnement push, RG-A2), faire sonner le téléphone. « C'est prêt » s'ouvre aux trois.
// Chaque réglage se vérifie sur le téléphone lui-même : l'état « fait » vient de ce que le navigateur dit, pas d'une case cochée.
import { api, chargement, toast } from '../core/app.js';

const racine = document.querySelector('[data-activer]');
const cle = racine.dataset.cle;
const reglage = (nom) => racine.querySelector(`[data-reglage="${nom}"]`);
const bouton = (nom) => reglage(nom).querySelector('[data-action]');
const sprite = document.querySelector('.icone use')?.getAttribute('href')?.replace(/#.*$/, '') ?? '';

const stocke = (k) => { try { return localStorage.getItem(k) === '1'; } catch { return false; } };
const retient = (k) => { try { localStorage.setItem(k, '1'); } catch { /* navigation privée : on redemandera */ } };
const installe = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true || stocke('am_pro_installe');

let invite = null; // beforeinstallprompt, quand le navigateur sait installer
let abonne = false;

// ---------- Les trois états d'un réglage : done · now · todo (272:1814, 272:1821, 272:1836) ----------
const NOEUDS = {
  done: '<path class="noeud-reglage__plein" d="M14 1.5L24.8 7.75V20.25L14 26.5L3.2 20.25V7.75L14 1.5Z"/><path class="noeud-reglage__coche" d="M9 14.3L12.3 17.5L18.9 10.7"/>',
  now: '<path class="noeud-reglage__plein" d="M14 1.5L24.8 7.75V20.25L14 26.5L3.2 20.25V7.75L14 1.5Z"/><circle class="noeud-reglage__voyant" cx="14" cy="14" r="4.4"/>',
  todo: '<path class="noeud-reglage__contour" d="M14 2.5L24 8.25V19.75L14 25.5L4 19.75V8.25L14 2.5Z"/>',
};

function etat(nom, valeur, phrase = null) {
  const li = reglage(nom);
  li.dataset.etat = valeur;
  li.className = `reglage-appel reglage-appel--${valeur}`;
  const svg = li.querySelector('.noeud-reglage');
  svg.setAttribute('class', `noeud-reglage noeud-reglage--${valeur}`);
  svg.innerHTML = NOEUDS[valeur];
  li.querySelector('[data-phrase]').textContent = phrase ?? (valeur === 'done' ? li.dataset.fait : li.dataset.texte);
  const b = bouton(nom);
  b.hidden = valeur === 'done';
  b.disabled = valeur === 'todo';
}

/** Le premier réglage non fait est « en cours », les suivants « à faire » ; « C'est prêt » s'ouvre quand tout est fait. */
function peindre(faits) {
  let courant = true;
  for (const nom of ['installer', 'notifications', 'essai']) {
    if (faits[nom]) etat(nom, 'done');
    else { etat(nom, courant ? 'now' : 'todo'); courant = false; }
  }
  const pret = racine.querySelector('[data-pret]');
  const tout = faits.installer && faits.notifications && faits.essai;
  pret.disabled = !tout;
  pret.className = `bouton bouton--${tout ? 'principal' : 'secondaire'} bouton--56 bouton--plein`;
  racine.querySelector('[data-reste]').hidden = tout;
}

const faits = { installer: false, notifications: false, essai: false };

// ---------- Notifications : abonnement push (RG-A2) ----------
const base64 = (texte) => {
  const brut = atob((texte + '='.repeat((4 - (texte.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from([...brut].map((c) => c.charCodeAt(0)));
};
const possible = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

async function souscription() {
  const sw = await navigator.serviceWorker.ready;
  return sw.pushManager.getSubscription();
}

async function verifierAbonnement() {
  if (!possible || Notification.permission !== 'granted') return false;
  const s = await souscription().catch(() => null);
  if (!s) return false;
  try { return (await api('/pro/api/push/etat', { methode: 'POST', donnees: { endpoint: s.endpoint } })).abonne === true; } catch { return false; }
}

async function autoriser() {
  if (!possible) return toast('Ce navigateur ne peut pas recevoir les notifications. Essayez avec Chrome.', { icone: 'triangle' });
  if (!cle) return toast('Les notifications ne sont pas encore configurées sur ce serveur.', { icone: 'triangle' });
  const b = bouton('notifications');
  chargement(b, true);
  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      toast('Les notifications sont refusées. Autorisez-les dans les réglages du navigateur, puis réessayez.', { icone: 'triangle' });
      return;
    }
    const sw = await navigator.serviceWorker.ready;
    const s = (await sw.pushManager.getSubscription()) ?? (await sw.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64(cle) }));
    await api('/pro/api/push', { methode: 'POST', donnees: s.toJSON() });
    faits.notifications = true;
    peindre(faits);
  } catch {
    toast('Les notifications n’ont pas pu être activées. Réessayez.', { icone: 'triangle' });
  } finally {
    chargement(b, false);
  }
}

// ---------- Installer ----------
addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); invite = e; });
addEventListener('appinstalled', () => { retient('am_pro_installe'); faits.installer = true; peindre(faits); });

async function installer() {
  if (invite) {
    invite.prompt();
    const { outcome } = await invite.userChoice;
    invite = null;
    if (outcome === 'accepted') { retient('am_pro_installe'); faits.installer = true; peindre(faits); }
    return;
  }
  // Pas d'invite automatique (Safari, ou déjà refusée) : le geste se fait dans le menu du navigateur
  etat('installer', 'now', 'Dans le menu de votre navigateur, choisissez « Ajouter à l’écran d’accueil », puis revenez ici.');
  const b = bouton('installer');
  b.querySelector('.bouton__libelle').textContent = 'C’est fait';
  b.dataset.action = 'installer-fait';
}

// ---------- Essai de sonnerie ----------
async function essayer() {
  const b = bouton('essai');
  chargement(b, true);
  try {
    await api('/pro/api/push/essai', { methode: 'POST', donnees: {} });
    navigator.vibrate?.([400, 200, 400]);
    retient('am_pro_essai');
    faits.essai = true;
    peindre(faits);
  } catch (e) {
    toast(e.json?.ok === false ? 'Aucun téléphone n’a sonné. Vérifiez les notifications, puis réessayez.' : 'L’essai n’a pas pu partir. Réessayez.', { icone: 'triangle' });
  } finally {
    chargement(b, false);
  }
}

// ---------- C'est prêt ----------
racine.querySelector('[data-pret]').addEventListener('click', async (e) => {
  const b = e.currentTarget;
  if (b.disabled) return;
  chargement(b, true);
  try {
    const r = await api('/pro/api/appels/pret', { methode: 'POST', donnees: {} });
    location.replace(r.suite || '/pro');
  } catch {
    chargement(b, false);
    toast('Il faut un téléphone qui reçoit les notifications. Refaites l’autorisation, puis réessayez.', { icone: 'triangle' });
  }
});

racine.addEventListener('click', (e) => {
  const b = e.target.closest('[data-action]');
  if (!b || b.disabled || b.getAttribute('aria-busy') === 'true') return;
  const nom = b.dataset.action;
  if (nom === 'installer') installer();
  else if (nom === 'installer-fait') { retient('am_pro_installe'); faits.installer = true; peindre(faits); }
  else if (nom === 'notifications') autoriser();
  else if (nom === 'essai') essayer();
});

// ---------- Au chargement : ce que le téléphone sait déjà ----------
(async () => {
  faits.installer = installe();
  faits.essai = stocke('am_pro_essai');
  peindre(faits);
  faits.notifications = await verifierAbonnement();
  if (!faits.notifications) faits.essai = false; // un essai ne vaut que pour un téléphone abonné
  peindre(faits);
})();
