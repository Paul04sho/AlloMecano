// Espace pro, mécano validé : tant que l'app est ouverte, on regarde si un appel sonne (RG-A2, RG-A5). Chaque interrogation prouve aussi que l'app est ouverte (vu_le).
// L'appel qui sonne ouvre l'écran M5. Quand l'app est fermée, c'est la notification push qui prévient (sw.js).
import { api } from '../core/app.js';

async function boucle() {
  while (true) {
    if (!document.hidden) {
      try {
        const { entrants } = await api('/pro/api/appels/entrants');
        if (entrants.length > 0) return location.assign(`/pro/appel/${encodeURIComponent(entrants[0].contact)}`);
      } catch { /* réseau coupé un instant : on réessaie */ }
    }
    await new Promise((ok) => setTimeout(ok, 3000));
  }
}
boucle();
