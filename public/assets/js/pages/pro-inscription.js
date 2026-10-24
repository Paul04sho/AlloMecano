// M1a · Compte mécano — autosauvegarde hors mot de passe, validation et aides clavier.
const form = document.querySelector('[data-m1-formulaire]');
const statut = document.querySelector('[data-m1-brouillon-statut]');
const heure = document.querySelector('[data-m1-heure]');
const champsBrouillon = ['prenom', 'nom', 'telephone', 'email'];
let minuteur = 0;
let chaineSauvegarde = Promise.resolve();
let revision = 0;

function afficherStatut(message) {
  if (statut) statut.textContent = message;
}

function donneesBrouillon() {
  const donnees = {};
  for (const nom of champsBrouillon) {
    const champ = form?.elements.namedItem(nom);
    donnees[nom] = typeof champ?.value === 'string' ? champ.value : '';
  }
  return donnees;
}

function sauvegarderBrouillon() {
  if (!form) return Promise.resolve(true);
  const version = ++revision;
  const donnees = donneesBrouillon();
  const csrf = document.querySelector('meta[name="csrf"]')?.content ?? '';

  // Sérialiser les écritures : une réponse lente ne doit jamais écraser un brouillon plus récent.
  const tache = chaineSauvegarde.catch(() => false).then(async () => {
    const reponse = await fetch('/pro/inscription/brouillon', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify(donnees),
    });
    const resultat = await reponse.json().catch(() => ({}));
    if (!reponse.ok || resultat.ok !== true) {
      throw new Error(resultat.message || 'Le brouillon n’a pas pu être enregistré.');
    }
    if (version === revision) {
      if (heure) heure.textContent = resultat.heure;
      afficherStatut(`Brouillon enregistré à ${resultat.heure}`);
    }
    return true;
  }).catch(() => {
    if (version === revision) {
      afficherStatut('Sauvegarde impossible. Vérifiez votre connexion puis réessayez.');
    }
    return false;
  });

  chaineSauvegarde = tache;
  return tache;
}

function programmerSauvegarde() {
  window.clearTimeout(minuteur);
  afficherStatut('Enregistrement du brouillon…');
  minuteur = window.setTimeout(() => { sauvegarderBrouillon(); }, 500);
}

function reglerMotDePasse() {
  const champ = form?.elements.namedItem('mot_de_passe');
  if (!(champ instanceof HTMLInputElement)) return;
  const longueur = form.querySelector('[data-regle-longueur]');
  const chiffre = form.querySelector('[data-regle-chiffre]');
  const assezLong = champ.value.length >= 8;
  const contientChiffre = /\d/.test(champ.value);
  longueur?.classList.toggle('is-ok', assezLong);
  chiffre?.classList.toggle('is-ok', contientChiffre);
  champ.setCustomValidity('');
}

function verifierAvantEnvoi() {
  if (!form) return true;
  const telephone = form.elements.namedItem('telephone');
  const motDePasse = form.elements.namedItem('mot_de_passe');
  const googleLie = form.dataset.googleVerifie === '1';

  if (telephone instanceof HTMLInputElement) {
    const chiffres = telephone.value.replace(/\D/g, '').replace(/^225(?=\d{10}$)/, '');
    telephone.setCustomValidity(chiffres.length === 10 ? '' : 'Entrez les 10 chiffres de votre numéro après +225.');
  }

  if (motDePasse instanceof HTMLInputElement) {
    if (!googleLie || motDePasse.value !== '') {
      const valide = motDePasse.value.length >= 8 && /\d/.test(motDePasse.value);
      motDePasse.setCustomValidity(valide ? '' : 'Choisissez un mot de passe de 8 caractères minimum, contenant au moins un chiffre.');
    } else {
      motDePasse.setCustomValidity('');
    }
  }

  if (form.checkValidity()) return true;
  const invalide = form.querySelector(':invalid');
  invalide?.focus();
  form.reportValidity();
  return false;
}

form?.querySelectorAll('input[name]').forEach((champ) => {
  if (champ.name === 'mot_de_passe' || champ.name === '_csrf') return;
  champ.addEventListener('input', programmerSauvegarde);
  champ.addEventListener('change', sauvegarderBrouillon);
});

const motDePasse = form?.elements.namedItem('mot_de_passe');
motDePasse?.addEventListener('input', reglerMotDePasse);
motDePasse?.addEventListener('change', reglerMotDePasse);
reglerMotDePasse();

form?.addEventListener('submit', (evenement) => {
  if (!verifierAvantEnvoi()) {
    evenement.preventDefault();
    evenement.stopPropagation();
  }
});

// Quitter la page ou passer à Google seulement après avoir envoyé les champs non sensibles.
document.querySelectorAll('[data-m1-navigation], [data-m1-google]').forEach((lien) => {
  lien.addEventListener('click', async (evenement) => {
    if (evenement.button !== 0 || evenement.metaKey || evenement.ctrlKey || evenement.shiftKey || evenement.altKey) return;
    evenement.preventDefault();
    lien.setAttribute('aria-busy', 'true');
    const destination = lien.href;
    window.clearTimeout(minuteur);
    const ok = await sauvegarderBrouillon();
    lien.removeAttribute('aria-busy');
    if (ok) window.location.assign(destination);
    else {
      statut?.setAttribute('tabindex', '-1');
      statut?.focus?.();
    }
  });
});
