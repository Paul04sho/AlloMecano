// Menu de panne (composant menu-panne) : liste d'options avec leur voyant de bord.
// La liste déroulante native reste la source de vérité (name="panne") : ce menu ne fait que la piloter.
// Clavier : Entrée / Espace / flèches ouvrent ; flèches, Début, Fin déplacent ; Entrée choisit ; Échap ferme.

export function menuPanne(racine) {
  const natif = racine.querySelector('.menu-panne__natif');
  const zone = racine.querySelector('.menu-panne__js');
  const bouton = racine.querySelector('.menu-panne__bouton');
  const liste = racine.querySelector('.menu-panne__liste');
  const options = [...liste.querySelectorAll('[role="option"]')];
  const valeur = racine.querySelector('.menu-panne__valeur');
  const voyant = racine.querySelector('[data-menu-icone] use');
  zone.hidden = false;
  natif.tabIndex = -1;
  natif.setAttribute('aria-hidden', 'true');
  let actif = Math.max(0, options.findIndex((o) => o.getAttribute('aria-selected') === 'true'));

  const marquer = (i) => {
    actif = (i + options.length) % options.length;
    options.forEach((o, k) => o.classList.toggle('actif', k === actif));
    liste.setAttribute('aria-activedescendant', options[actif].id);
    options[actif].scrollIntoView({ block: 'nearest' });
  };
  const ouvrir = () => {
    liste.hidden = false;
    bouton.setAttribute('aria-expanded', 'true');
    marquer(actif);
    liste.focus();
  };
  const fermer = (rendreFocus = true) => {
    liste.hidden = true;
    bouton.setAttribute('aria-expanded', 'false');
    if (rendreFocus) bouton.focus();
  };
  const choisir = (i) => {
    const o = options[i];
    options.forEach((x) => x.setAttribute('aria-selected', String(x === o)));
    natif.value = o.dataset.valeur;
    natif.dispatchEvent(new Event('change', { bubbles: true }));
    valeur.textContent = o.textContent.trim();
    voyant?.setAttribute('href', o.querySelector('use').getAttribute('href'));
    fermer();
  };

  bouton.addEventListener('click', () => (liste.hidden ? ouvrir() : fermer()));
  bouton.addEventListener('keydown', (e) => {
    if (['ArrowDown', 'ArrowUp'].includes(e.key)) { e.preventDefault(); ouvrir(); }
  });
  liste.addEventListener('keydown', (e) => {
    const touches = { ArrowDown: actif + 1, ArrowUp: actif - 1, Home: 0, End: options.length - 1 };
    if (e.key in touches) { e.preventDefault(); marquer(touches[e.key]); }
    else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choisir(actif); }
    else if (e.key === 'Escape') { e.preventDefault(); fermer(); }
    else if (e.key === 'Tab') fermer(false);
  });
  options.forEach((o, i) => {
    o.addEventListener('click', () => choisir(i));
    o.addEventListener('mousemove', () => marquer(i));
  });
  document.addEventListener('click', (e) => {
    if (!liste.hidden && !racine.contains(e.target)) fermer(false);
  });
}
