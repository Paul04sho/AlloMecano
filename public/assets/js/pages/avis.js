// C6b · Votre avis : la note s'affiche à côté des étoiles, la mention suit le prénom saisi, le bouton passe en « Publication… ».
import { chargement } from '../core/app.js';

const form = document.querySelector('[data-avis-formulaire]');
if (form) {
  const valeur = form.querySelector('[data-valeur-note]');
  form.querySelectorAll('input[name="note"]').forEach((r) => r.addEventListener('change', () => {
    valeur.hidden = false;
    valeur.querySelector('.t-borne-l').textContent = r.value;
  }));

  const mention = form.querySelector('[data-mention]');
  const prenom = form.querySelector('input[name="prenom"]');
  const majMention = () => { mention.textContent = mention.dataset.modele.replace('{prenom}', prenom.value.trim() || '…'); };
  prenom?.addEventListener('input', majMention);

  form.addEventListener('submit', () => {
    const bouton = form.querySelector('button[type="submit"]');
    if (bouton) chargement(bouton);
  });
}
