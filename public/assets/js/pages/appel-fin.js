// C5c · fin de l'appel intégré : « Koffi ne répond pas » (le numéro et le suivant) et « Koffi vous dit s'il vient » (la réponse arrive en direct, RG-I2).
import { api } from '../core/app.js';
import { initialiserContacts, surveillerRetour } from '../composants/contact.js';

const racine = document.querySelector('[data-appel-fin]');
if (racine) {
  initialiserContacts(racine); // « Appeler Adama », WhatsApp, « Appeler Koffi sur son numéro » : le contact d'abord (RG-A1)
  surveillerRetour();

  if (racine.dataset.appelFin === 'termine') {
    // La réponse du mécano (« J'y vais » / « Pas pour moi ») s'affiche dès qu'il raccroche : on suit l'état toutes les 3 s (RG-A8)
    const id = racine.dataset.contact;
    (async () => {
      while (true) {
        try {
          const etat = await api(`/api/appels/${encodeURIComponent(id)}/signaux?apres=2147483647`);
          if (etat.issue_mecano) { location.replace(`/contact/${encodeURIComponent(id)}`); return; } // C6 montre la suite
        } catch (e) {
          if (e.statut === 404) return;
        }
        await new Promise((ok) => setTimeout(ok, 3000));
      }
    })();
  }
}
