import { chargement, definirTheme, toast } from '../core/app.js';

document.querySelectorAll('[data-theme-choix]').forEach((b) => {
  b.addEventListener('click', () => definirTheme(b.dataset.themeChoix));
});

document.querySelector('[data-demo-toast]')?.addEventListener('click', () => {
  toast('Copié. Collez-le dans WhatsApp ou par SMS.', { icone: 'coche' });
});

document.querySelectorAll('[data-demo-chargement]').forEach((b) => {
  b.addEventListener('click', () => {
    chargement(b, true);
    setTimeout(() => chargement(b, false), 2500);
  });
});
