// C2a · Demande de position native avec états de chargement et erreurs accessibles.
import { chargement } from '../core/app.js';

const boutonGps = document.querySelector('[data-position-gps]');
const statut = document.querySelector('[data-position-statut]');

function afficherStatut(message, etat = 'info') {
  if (!statut) return;
  statut.textContent = message;
  statut.dataset.etat = etat;
  statut.hidden = false;
}

function messageErreurPosition(erreur) {
  if (erreur.code === 1) {
    return 'L’accès à votre position a été refusé. Vous pouvez choisir votre commune à la place.';
  }

  if (erreur.code === 2) {
    return 'Votre position est indisponible pour le moment. Vérifiez le GPS ou choisissez votre commune.';
  }

  if (erreur.code === 3) {
    return 'La recherche de position a pris trop de temps. Réessayez ou choisissez votre commune.';
  }

  return 'La position ne peut pas être récupérée sur cet appareil. Choisissez votre commune.';
}

if (boutonGps instanceof HTMLButtonElement && statut instanceof HTMLElement) {
  boutonGps.addEventListener('click', () => {
    if (boutonGps.disabled) return;

    if (!('geolocation' in navigator)) {
      afficherStatut('La géolocalisation n’est pas disponible dans ce navigateur. Choisissez votre commune.', 'erreur');
      return;
    }

    statut.hidden = true;
    boutonGps.disabled = true;
    chargement(boutonGps, true);

    navigator.geolocation.getCurrentPosition(
      (position) => {
        chargement(boutonGps, false);
        boutonGps.disabled = false;

        const precision = Math.max(1, Math.round(position.coords.accuracy));
        afficherStatut(
          `Position détectée avec une précision d’environ ${precision} m. Vous pourrez vérifier le repère à l’étape de confirmation.`,
          'succes',
        );

        // Ne pas persister les coordonnées : elles restent uniquement dans cette réponse JS.
      },
      (erreur) => {
        chargement(boutonGps, false);
        boutonGps.disabled = false;
        afficherStatut(messageErreurPosition(erreur), 'erreur');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000,
      },
    );
  });
}
