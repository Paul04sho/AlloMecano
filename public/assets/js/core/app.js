// allomécano · comportements communs à toutes les pages (amélioration progressive : tout marche sans JS).

/** Jeton CSRF pour fetch() : en-tête X-CSRF. */
export const csrf = () => document.querySelector('meta[name="csrf"]')?.content ?? '';

/** fetch JSON avec CSRF et erreurs lisibles. */
export async function api(url, { methode = 'GET', donnees } = {}) {
  const reponse = await fetch(url, {
    method: methode,
    headers: { Accept: 'application/json', ...(donnees ? { 'Content-Type': 'application/json' } : {}), 'X-CSRF': csrf() },
    body: donnees ? JSON.stringify(donnees) : undefined,
    credentials: 'same-origin',
  });
  const json = await reponse.json().catch(() => ({}));
  if (!reponse.ok) throw Object.assign(new Error(json.message || 'La demande n’a pas abouti.'), { statut: reponse.status, json });
  return json;
}

/** Toast (spec 7.18) : bitume, 4 s (6 s avec une action), un seul à la fois, aria-live. */
export function toast(message, { icone = null, action = null, surAction = null, duree } = {}) {
  const zone = document.querySelector('[data-toasts]');
  if (!zone) return;
  zone.querySelectorAll('.toast').forEach((t) => t.remove());
  const t = document.createElement('div');
  t.className = 'toast';
  t.innerHTML = `${icone ? `<svg class="icone" width="20" height="20" aria-hidden="true"><use href="/assets/icones/sprite.svg#i-${icone}"></use></svg>` : ''}<span></span>`;
  t.querySelector('span').textContent = message;
  if (action) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'toast__action';
    b.textContent = action;
    b.addEventListener('click', () => { surAction?.(); fermer(); });
    t.append(b);
  }
  zone.append(t);
  const fermer = () => { t.classList.add('sortie'); t.addEventListener('animationend', () => t.remove(), { once: true }); };
  setTimeout(fermer, duree ?? (action ? 6000 : 4000));
}

/** Bouton en chargement : libellé au présent + voyant clignotant, largeur figée (spec 7.2). */
export function chargement(bouton, actif = true) {
  const libelle = bouton.querySelector('.bouton__libelle');
  if (actif) {
    bouton.style.minWidth = `${bouton.offsetWidth}px`;
    bouton.dataset.libelleInitial = libelle.textContent;
    if (bouton.dataset.chargement) libelle.textContent = bouton.dataset.chargement;
    bouton.setAttribute('aria-busy', 'true');
  } else {
    libelle.textContent = bouton.dataset.libelleInitial ?? libelle.textContent;
    bouton.removeAttribute('aria-busy');
    bouton.style.minWidth = '';
  }
}

/** Thème : cookie lu par PHP (pas de flash au chargement). */
export function definirTheme(theme) {
  if (theme === 'systeme') {
    document.documentElement.removeAttribute('data-theme');
    document.cookie = 'theme=; Max-Age=0; Path=/; SameSite=Lax';
  } else {
    document.documentElement.dataset.theme = theme;
    document.cookie = `theme=${theme}; Max-Age=31536000; Path=/; SameSite=Lax`;
  }
}

// ---------- Comportements déclaratifs ----------

// Téléphone : « 0707123456 » → « 07 07 12 34 56 »
document.addEventListener('input', (e) => {
  const champ = e.target;
  if (champ.matches?.('[data-format="telephone"]')) {
    const chiffres = champ.value.replace(/\D/g, '').replace(/^225(?=\d{10})/, '').slice(0, 10);
    champ.value = chiffres.replace(/(\d{2})(?=\d)/g, '$1 ');
  }
  if (champ.matches?.('textarea[maxlength]')) {
    const compteur = document.querySelector(`[data-compteur="${champ.id}"]`);
    if (compteur) compteur.textContent = `${champ.value.length} / ${champ.maxLength}`;
  }
});

// Afficher / masquer le mot de passe
document.addEventListener('click', (e) => {
  const b = e.target.closest?.('[data-afficher-mdp]');
  if (!b) return;
  const champ = document.getElementById(b.getAttribute('aria-controls'));
  const visible = champ.type === 'text';
  champ.type = visible ? 'password' : 'text';
  b.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
  b.querySelector('use')?.setAttribute('href', `/assets/icones/sprite.svg#i-${visible ? 'oeil' : 'oeil-barre'}`);
});

// Choix du thème (pied de page, page Système) : Clair · Sombre · Auto
document.addEventListener('click', (e) => {
  const b = e.target.closest?.('[data-theme-choix]');
  if (!b) return;
  definirTheme(b.dataset.themeChoix);
  b.parentElement.querySelectorAll('[data-theme-choix]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
});

// Formulaire envoyé : le bouton passe en chargement (évite le double envoi)
document.addEventListener('submit', (e) => {
  const bouton = e.submitter?.closest?.('.bouton');
  if (bouton && !e.defaultPrevented) chargement(bouton, true);
});

// Message flash posé par PHP après une redirection
const flash = document.querySelector('[data-flash]');
if (flash) toast(flash.dataset.flash, { icone: flash.dataset.flashIcone || null });

// Le socle laisse la place aux toasts
const socle = document.querySelector('[data-socle]');
if (socle) document.documentElement.style.setProperty('--decalage-socle', `${socle.offsetHeight}px`);
