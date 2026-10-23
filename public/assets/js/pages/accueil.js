// C1 · D0 · Accueil
import { menuPanne } from '../composants/menu-panne.js';

// Tableau des communes (mobile) : les 6 premières, puis « Voir les 13 communes » déplie sur place
const communes = document.querySelector('[data-communes]');
const voir = communes?.querySelector('[data-voir-communes]');
if (voir && communes.querySelector('.communes__suite')) {
  voir.hidden = false;
  voir.addEventListener('click', () => {
    communes.classList.add('ouvert');
    voir.setAttribute('aria-expanded', 'true');
    voir.hidden = true;
    communes.querySelector('.communes__suite a')?.focus();
  });
}

// Formulaire du héros (D0)
document.querySelectorAll('[data-menu-panne]').forEach(menuPanne);

// « Utiliser ma position » garde le véhicule et la panne déjà choisis
const formulaire = document.querySelector('.heros__formulaire');
formulaire?.querySelector('[data-ma-position]')?.addEventListener('click', (e) => {
  const donnees = new FormData(formulaire);
  const params = new URLSearchParams({ vehicule: donnees.get('vehicule') ?? '', panne: donnees.get('panne') ?? '' });
  e.currentTarget.href = `/position?${params}`;
});

// Trombinoscope (mobile) : la bande qui défile se prend au clavier, seulement si elle déborde
const bande = document.querySelector('[data-defilement]');
const reglerBande = () => {
  if (bande.scrollWidth > bande.clientWidth) bande.tabIndex = 0; else bande.removeAttribute('tabindex');
};
if (bande) { reglerBande(); addEventListener('resize', reglerBande); }
