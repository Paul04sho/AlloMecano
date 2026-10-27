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

// Tableau des communes : à son arrivée à l'écran, les mécanos disponibles se comptent de 0 à leur nombre réel
// (comme les bornes de la fiche : 520 ms, délai 300 ms, sortie cubique). Le vrai nombre est dans la page dès le départ.
const nombres = communes ? [...communes.querySelectorAll('.ligne-commune__nombre .t-borne-l')] : [];
if (nombres.length && 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const compter = (el, delai) => {
    const fin = Number(el.textContent);
    const debut = performance.now() + delai;
    el.textContent = '0';
    const pas = (t) => {
      const p = Math.min(1, Math.max(0, (t - debut) / 520));
      el.textContent = String(Math.round(fin * (1 - (1 - p) ** 3)));
      if (p < 1) requestAnimationFrame(pas);
    };
    requestAnimationFrame(pas);
  };
  const observateur = new IntersectionObserver((entrees) => {
    if (!entrees.some((e) => e.isIntersecting)) return;
    observateur.disconnect();
    nombres.forEach((el, i) => compter(el, 300 + i * 40));
  }, { threshold: 0.25 });
  observateur.observe(communes);
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
