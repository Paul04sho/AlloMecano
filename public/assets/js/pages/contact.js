// C6 · « Avez-vous pu le joindre ? » en pleine page : les réponses changent d'étape sans recharger.
import { initialiserRetour, surveillerRetour } from '../composants/contact.js';

const racine = document.querySelector('[data-retour-appel]');
if (racine) initialiserRetour(racine);
surveillerRetour();
