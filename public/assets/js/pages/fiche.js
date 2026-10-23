// C5 · Fiche mécanicien : animations d'arrivée, copie du message, avis en plus ; le retour ramène à la liste d'où l'on vient.
import { initialiserFiche } from '../composants/fiche.js';
import { initialiserContacts, surveillerRetour } from '../composants/contact.js';

const fiche = document.querySelector('[data-fiche]');
if (fiche) {
  initialiserFiche(fiche, fiche.dataset.slug);
  initialiserContacts(fiche);
  surveillerRetour();

  // Retour : comme le bouton « précédent » quand on vient de la liste (elle garde sa position et sa feuille), sinon le lien
  const retour = fiche.querySelector('[data-retour]');
  retour?.addEventListener('click', (e) => {
    const vientDeLaListe = document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1;
    if (vientDeLaListe) { e.preventDefault(); history.back(); }
  });
}
