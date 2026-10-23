import { chargement, toast } from '../core/app.js';
import { menuPanne } from '../composants/menu-panne.js';

document.querySelectorAll('[data-menu-panne]').forEach(menuPanne);

document.querySelector('[data-demo-toast]')?.addEventListener('click', () => {
  toast('Copié. Collez-le dans WhatsApp ou par SMS.', { icone: 'coche' });
});

document.querySelectorAll('[data-demo-chargement]').forEach((b) => {
  b.addEventListener('click', () => {
    chargement(b, true);
    setTimeout(() => chargement(b, false), 2500);
  });
});
