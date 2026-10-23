// X2 · Mot de passe oublié : les règles se cochent pendant la saisie (130:178), le bouton « Renvoyer » se réveille après 60 s (129:175).

// Étape 3 : règles vivantes
const formulaire = document.querySelector('[data-formulaire-mdp]');
if (formulaire) {
  const mdp = formulaire.querySelector('input[name="mot_de_passe"]');
  const confirmation = formulaire.querySelector('input[name="confirmation"]');
  const regle = (cle, faite) => {
    const li = formulaire.querySelector(`[data-regle="${cle}"]`);
    if (!li || li.classList.contains('regle-vivante--faite') === faite) return;
    li.classList.toggle('regle-vivante--faite', faite);
    li.querySelector('[data-regle-etat]').textContent = faite ? ' : fait' : ' : à faire';
  };
  const maj = () => {
    regle('longueur', mdp.value.length >= 8);
    regle('chiffre', /\d/.test(mdp.value));
    regle('identiques', confirmation.value !== '' && confirmation.value === mdp.value);
  };
  mdp.addEventListener('input', maj);
  confirmation.addEventListener('input', maj);
  maj();
}

// Étape 2 : compte à rebours avant le nouvel envoi
const renvoi = document.querySelector('[data-renvoi]');
if (renvoi) {
  let reste = Number(renvoi.dataset.renvoi) || 0;
  const texte = renvoi.querySelector('[data-renvoi-texte]');
  const minuteur = renvoi.querySelector('[data-renvoi-minuteur]');
  const bouton = renvoi.querySelector('[data-renvoi-bouton]');
  if (reste > 0) {
    const tic = setInterval(() => {
      reste -= 1;
      if (reste <= 0) {
        clearInterval(tic);
        texte.hidden = true;
        bouton.disabled = false;
        bouton.removeAttribute('aria-disabled');
        return;
      }
      minuteur.textContent = `${Math.floor(reste / 60)}:${String(reste % 60).padStart(2, '0')}`;
    }, 1000);
  }
}
