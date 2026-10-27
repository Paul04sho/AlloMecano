// allomécano · comportements communs à toutes les pages (amélioration progressive : tout marche sans JS).

import { demarrerBarre, demarrerMarquage, demarrerRevelations } from './mouvement.js';

demarrerMarquage();
demarrerRevelations();
demarrerBarre();

/** Jeton CSRF pour fetch() : en-tête X-CSRF. */
export const csrf = () => document.querySelector('meta[name="csrf"]')?.content ?? '';

/** fetch JSON avec CSRF et erreurs lisibles. */
export async function api(url, { methode = 'GET', donnees } = {}) {
  const reponse = await fetch(url, {
    method: methode,
    headers: { Accept: 'application/json', ...(donnees ? { 'Content-Type': 'application/json' } : {}), 'X-CSRF': csrf() },
    body: donnees ? JSON.stringify(donnees) : undefined,
    credentials: 'same-origin',
  });
  const json = await reponse.json().catch(() => ({}));
  if (!reponse.ok) throw Object.assign(new Error(json.message || 'La demande n’a pas abouti.'), { statut: reponse.status, json });
  return json;
}

/** Toast (spec 7.18) : bitume, 4 s (6 s avec une action), un seul à la fois, aria-live. */
export function toast(message, { icone = null, action = null, surAction = null, duree } = {}) {
  const zone = document.querySelector('[data-toasts]');
  if (!zone) return;
  zone.querySelectorAll('.toast').forEach((t) => t.remove());
  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = `${icone ? `<svg class="icone" width="20" height="20" aria-hidden="true"><use href="/assets/icones/sprite.svg#i-${icone}"></use></svg>` : ''}<span></span>`;
  t.querySelector('span').textContent = message;
  if (action) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'toast__action';
    b.textContent = action;
    b.addEventListener('click', () => { surAction?.(); fermer(); });
    t.append(b);
  }
  zone.append(t);
  const fermer = () => { t.classList.add('sortie'); t.addEventListener('animationend', () => t.remove(), { once: true }); };
  setTimeout(fermer, duree ?? (action ? 6000 : 4000));
}

/**
 * Bannière réseau (prototype · Toasts · bannière réseau) : « Hors ligne » descend du haut (240 ms) et reste tant que
 * le réseau manque ; au retour, « Vous êtes de nouveau en ligne » passe au vert en fondu (200 ms), puis les deux
 * remontent après 2 s (240 ms). La page reste affichée et utilisable pendant la coupure.
 */
let barreHorsLigne = null;
export function etatReseau(enLigne) {
  let zone = document.querySelector('[data-reseau]');
  if (!zone) {
    zone = document.createElement('div');
    zone.className = 'reseau';
    zone.dataset.reseau = '';
    zone.setAttribute('role', 'status');
    zone.setAttribute('aria-live', 'polite');
    document.body.append(zone);
  }
  const d = (ms) => (matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : ms);
  const barre = (etat, texte) => {
    const b = document.createElement('p');
    b.className = `reseau__barre reseau__barre--${etat} t-etiquette`;
    b.innerHTML = '<i aria-hidden="true"></i><span></span>';
    b.querySelector('span').textContent = texte;
    zone.append(b);
    return b;
  };
  if (!enLigne) {
    if (barreHorsLigne) return;
    barreHorsLigne = barre('hors', 'Hors ligne · on garde votre recherche');
    barreHorsLigne.animate([{ transform: 'translateY(-100%)' }, { transform: 'none' }], { duration: d(240), easing: 'cubic-bezier(.2, .8, .2, 1)' });
    return;
  }
  if (!barreHorsLigne) return;
  const hors = barreHorsLigne;
  barreHorsLigne = null;
  const retour = barre('retour', 'Vous êtes de nouveau en ligne');
  retour.animate([{ opacity: 0 }, { opacity: 1 }], { duration: d(200), fill: 'forwards' });
  setTimeout(() => {
    [hors, retour].forEach((b) => b.animate([{ transform: 'none' }, { transform: 'translateY(-100%)' }], { duration: d(240), easing: 'cubic-bezier(.7, 0, .84, 0)', fill: 'forwards' })
      .finished.then(() => b.remove(), () => b.remove()));
  }, 2000);
}
// Écrans client seulement : « on garde votre recherche » n'a pas de sens dans l'espace pro ni l'administration
if (!/^\/(pro|admin)(\/|$)/.test(location.pathname)) {
  addEventListener('offline', () => etatReseau(false));
  addEventListener('online', () => etatReseau(true));
  if (navigator.onLine === false) etatReseau(false);
}

/**
 * Retour tactile court aux moments clés (Android ; iOS l'ignore). Chrome le refuse tant que la personne n'a pas touché
 * la page en cours : on ne l'appelle qu'après un geste. Jamais en mouvement réduit.
 */
export function vibrer(motif) {
  if (!navigator.vibrate || navigator.userActivation?.hasBeenActive === false) return;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  try { navigator.vibrate(motif); } catch { /* refusé par le navigateur : sans importance */ }
}

/** Bouton en chargement : libellé au présent + voyant clignotant, largeur figée (spec 7.2). */
export function chargement(bouton, actif = true) {
  const libelle = bouton.querySelector('.bouton__libelle');
  if (actif) {
    bouton.style.minWidth = `${bouton.offsetWidth}px`;
    bouton.dataset.libelleInitial = libelle.textContent;
    if (bouton.dataset.chargement) libelle.textContent = bouton.dataset.chargement;
    bouton.setAttribute('aria-busy', 'true');
  } else {
    libelle.textContent = bouton.dataset.libelleInitial ?? libelle.textContent;
    bouton.removeAttribute('aria-busy');
    bouton.style.minWidth = '';
  }
}

/** Thème : cookie lu par PHP (pas de flash au chargement). */
export function definirTheme(theme) {
  if (theme === 'systeme') {
    document.documentElement.removeAttribute('data-theme');
    document.cookie = 'theme=; Max-Age=0; Path=/; SameSite=Lax';
  } else {
    document.documentElement.dataset.theme = theme;
    document.cookie = `theme=${theme}; Max-Age=31536000; Path=/; SameSite=Lax`;
  }
}

// ---------- Comportements déclaratifs ----------

// Téléphone : « 0707123456 » → « 07 07 12 34 56 »
document.addEventListener('input', (e) => {
  const champ = e.target;
  if (champ.matches?.('[data-format="telephone"]')) {
    const chiffres = champ.value.replace(/\D/g, '').replace(/^225(?=\d{10})/, '').slice(0, 10);
    champ.value = chiffres.replace(/(\d{2})(?=\d)/g, '$1 ');
  }
  if (champ.matches?.('textarea[maxlength]')) {
    const compteur = document.querySelector(`[data-compteur="${champ.id}"]`);
    if (compteur) compteur.textContent = `${champ.value.length} / ${champ.maxLength}`;
  }
});

// Afficher / masquer le mot de passe
document.addEventListener('click', (e) => {
  const b = e.target.closest?.('[data-afficher-mdp]');
  if (!b) return;
  const champ = document.getElementById(b.getAttribute('aria-controls'));
  const visible = champ.type === 'text';
  champ.type = visible ? 'password' : 'text';
  b.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
  b.querySelector('use')?.setAttribute('href', `/assets/icones/sprite.svg#i-${visible ? 'oeil' : 'oeil-barre'}`);
});

// Choix du thème (pied de page, page Système) : Clair · Sombre · Auto
document.addEventListener('click', (e) => {
  const b = e.target.closest?.('[data-theme-choix]');
  if (!b) return;
  definirTheme(b.dataset.themeChoix);
  b.parentElement.querySelectorAll('[data-theme-choix]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
});

// Formulaire envoyé : le bouton passe en chargement (évite le double envoi)
document.addEventListener('submit', (e) => {
  const bouton = e.submitter?.closest?.('.bouton');
  if (bouton && !e.defaultPrevented) chargement(bouton, true);
});

// Message flash posé par PHP après une redirection
const flash = document.querySelector('[data-flash]');
if (flash) toast(flash.dataset.flash, { icone: flash.dataset.flashIcone || null });

// Le socle laisse la place aux toasts
const socle = document.querySelector('[data-socle]');
if (socle) document.documentElement.style.setProperty('--decalage-socle', `${socle.offsetHeight}px`);

// RG-C7 · client anonyme : le serveur confie un jeton neuf une fois (meta). Si le navigateur en avait déjà un
// (cookie perdu), on reprend l'ancien pour garder l'historique ; sinon on garde une copie du neuf.
const jetonNeuf = document.querySelector('meta[name="client-jeton"]')?.content;
if (jetonNeuf) {
  let ancien = null;
  try { ancien = localStorage.getItem('am_client'); } catch {}
  const garder = () => { try { localStorage.setItem('am_client', jetonNeuf); } catch {} };
  if (ancien && ancien !== jetonNeuf) api('/api/client/restaurer', { methode: 'POST', donnees: { jeton: ancien } }).catch(garder);
  else garder();
}

// X7 · hors ligne : le service worker sert la page « Hors ligne » quand le réseau manque (HTTPS ou localhost seulement).
if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
  navigator.serviceWorker.register('/sw.js').catch(() => { /* pas de service worker : le navigateur affiche sa propre page */ });
}
