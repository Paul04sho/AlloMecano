const CLE_NAVIGATION = 'allomecano:transition-page';
let ecouteurFinArrivee = null;

function resoudreUrlInterne(url, contexte = {}) {
  try {
    const destination = new URL(url, contexte.base ?? window.location.href);
    const origine = new URL(contexte.base ?? window.location.href);
    return destination.origin === origine.origin ? { destination, origine } : null;
  } catch {
    return null;
  }
}

/** Vrai si un lien quitterait le document courant au sein de la même origine. */
export function estNavigationInterne(url, contexte = {}) {
  const urls = resoudreUrlInterne(url, contexte);
  if (!urls) return false;
  // Un changement de fragment ou un lien vers la page elle-même ne quitte pas le document.
  return urls.destination.pathname !== urls.origine.pathname || urls.destination.search !== urls.origine.search;
}

/** Les formulaires standards rechargent aussi quand leur action est leur URL actuelle. */
export function estActionFormulaireInterne(url, contexte = {}) {
  return resoudreUrlInterne(url, contexte) !== null;
}

function mouvementReduit() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function enregistrerNavigation() {
  if (mouvementReduit()) return;
  if (ecouteurFinArrivee) {
    document.documentElement.removeEventListener('animationend', ecouteurFinArrivee);
    ecouteurFinArrivee = null;
  }
  try {
    sessionStorage.setItem(CLE_NAVIGATION, String(Date.now()));
    document.documentElement.dataset.transitionPage = 'depart';
  } catch {
    // En mode privé strict ou si le stockage est indisponible, la navigation reste native.
  }
}

function lienEligible(evenement) {
  if (evenement.defaultPrevented || evenement.button !== 0 || evenement.metaKey || evenement.ctrlKey
    || evenement.shiftKey || evenement.altKey) return null;

  const lien = evenement.target?.closest?.('a[href]');
  if (!lien || lien.hasAttribute('download')) return null;
  if (lien.target && lien.target.toLowerCase() !== '_self') return null;
  if (!estNavigationInterne(lien.href)) return null;
  return lien;
}

function formulaireEligible(evenement) {
  if (evenement.defaultPrevented) return false;
  const formulaire = evenement.target;
  if (!(formulaire instanceof HTMLFormElement)) return false;

  const bouton = evenement.submitter;
  const methode = bouton?.getAttribute('formmethod') ?? formulaire.method;
  if (methode.toLowerCase() === 'dialog') return false;
  const cible = bouton?.getAttribute('formtarget') ?? formulaire.target;
  const action = bouton?.getAttribute('formaction') ?? formulaire.action;
  if (cible && cible.toLowerCase() !== '_self') return false;
  return estActionFormulaireInterne(action);
}

function terminerMarquage() {
  const racine = document.documentElement;
  if (ecouteurFinArrivee) racine.removeEventListener('animationend', ecouteurFinArrivee);
  racine.dataset.transitionPage = 'arrivee';
  ecouteurFinArrivee = (evenement) => {
    if (evenement.target !== racine || evenement.pseudoElement !== '::before'
      || evenement.animationName !== 'marquage-disparition') return;
    racine.removeEventListener('animationend', ecouteurFinArrivee);
    ecouteurFinArrivee = null;
    if (racine.dataset.transitionPage === 'arrivee') delete racine.dataset.transitionPage;
  };
  racine.addEventListener('animationend', ecouteurFinArrivee);
}

function restaurerApresCache(evenement) {
  if (evenement.persisted && !mouvementReduit()) terminerMarquage();
}

/** Apparitions au scroll, uniquement en amélioration progressive et une seule fois. */
export function demarrerRevelations() {
  if (mouvementReduit() || !('IntersectionObserver' in window)) return;

  const racine = document.documentElement;
  const cibles = document.querySelectorAll('[data-apparition-defilement]');
  if (!cibles.length) return;

  let observateur;
  try {
    observateur = new IntersectionObserver((entrees) => {
      entrees.forEach((entree) => {
        if (!entree.isIntersecting) return;
        entree.target.dataset.apparitionVisible = '';
        observateur.unobserve(entree.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    racine.classList.add('revelations-actives');
    cibles.forEach((cible) => observateur.observe(cible));
  } catch {
    observateur?.disconnect();
    racine.classList.remove('revelations-actives');
    cibles.forEach((cible) => { delete cible.dataset.apparitionVisible; });
    return;
  }

  document.addEventListener('focusin', (evenement) => {
    const cible = evenement.target?.closest?.('[data-apparition-defilement]');
    if (!cible || !racine.classList.contains('revelations-actives')) return;
    cible.dataset.apparitionVisible = '';
    observateur.unobserve(cible);
  });
}

/**
 * Barre client (mobile) : se replie quand on descend, revient dès qu'on remonte, près du haut de la page
 * ou quand le clavier y entre. Seuil de 8 px pour ignorer les petits tremblements du doigt.
 */
export function demarrerBarre() {
  const barre = document.querySelector('header.barre:not(.barre--resultats)');
  if (!barre || mouvementReduit()) return;
  const mobile = window.matchMedia('(max-width: 1023.98px)');
  let dernierY = window.scrollY;
  let attente = false;
  const regler = () => {
    attente = false;
    const y = window.scrollY;
    const delta = y - dernierY;
    if (!mobile.matches || y < barre.offsetHeight || barre.contains(document.activeElement)) barre.classList.remove('barre--repliee');
    else if (delta > 8) barre.classList.add('barre--repliee');
    else if (delta < -8) barre.classList.remove('barre--repliee');
    if (Math.abs(delta) > 8) dernierY = y;
  };
  window.addEventListener('scroll', () => {
    if (attente) return;
    attente = true;
    requestAnimationFrame(regler);
  }, { passive: true });
  barre.addEventListener('focusin', () => barre.classList.remove('barre--repliee'));
}

/** Active T1 sans intercepter ni retarder les navigations natives. */
export function demarrerMarquage() {
  if (mouvementReduit()) {
    try { sessionStorage.removeItem(CLE_NAVIGATION); } catch {}
    return;
  }

  try {
    const horodatage = Number(sessionStorage.getItem(CLE_NAVIGATION));
    sessionStorage.removeItem(CLE_NAVIGATION);
    if (Number.isFinite(horodatage) && Date.now() - horodatage < 5 * 60 * 1000) terminerMarquage();
  } catch {
    // Le stockage n’est qu’un indice visuel ; il n’est jamais requis au fonctionnement du site.
  }

  window.addEventListener('click', (evenement) => {
    if (lienEligible(evenement)) enregistrerNavigation();
  });
  window.addEventListener('submit', (evenement) => {
    if (formulaireEligible(evenement)) enregistrerNavigation();
  });
  window.addEventListener('pageshow', restaurerApresCache);
}
