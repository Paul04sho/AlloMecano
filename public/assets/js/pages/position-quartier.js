// C2b · Commune, quartier, repère (68:604) : toute la liste est dans la page, la recherche marche sans connexion.
const formulaire = document.querySelector('[data-quartier]');
const zone = formulaire.querySelector('[data-zone-choix]');
const champ = formulaire.querySelector('#champ-q');
const repere = formulaire.querySelector('#champ-repere');
const recap = formulaire.querySelector('[data-recap]');
const recapTexte = recap.querySelector('.recap__texte');
const continuer = formulaire.querySelector('[data-continuer]');
const libelle = continuer.querySelector('.bouton__libelle');
const pourquoi = formulaire.querySelector('[data-socle-pourquoi]');
const quartiers = JSON.parse(formulaire.dataset.quartiers); // [slug, nom, alias, commune, nom de la commune]
const communes = JSON.parse(formulaire.dataset.communes);   // [slug, nom]
const FINE = ' ';

const normaliser = (t) => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[-’]/g, ' ').replace(/\s+/g, ' ').trim();

/** Même classement que PositionService::chercher : nom ou alias avant la commune, début de mot avant le reste. */
function chercher(texte) {
  const cle = normaliser(texte);
  if (!cle) return [];
  return quartiers.map((q) => {
    const champs = [q[1], q[2], q[4]].map(normaliser);
    const i = champs.findIndex((c) => c.includes(cle));
    return i < 0 ? null : { q, rang: [i === 2 ? 1 : 0, champs[i].indexOf(cle) === 0 ? 0 : 1, q[1].length] };
  }).filter(Boolean).sort((a, b) => a.rang[0] - b.rang[0] || a.rang[1] - b.rang[1] || a.rang[2] - b.rang[2]).slice(0, 12).map((r) => r.q);
}

// ---------- Rendu (mêmes balises que les composants PHP ligne-choix et barre de fil) ----------
const el = (balise, attrs = {}, enfants = []) => {
  const n = document.createElement(balise);
  for (const [k, v] of Object.entries(attrs)) if (k === 'texte') n.textContent = v; else n.setAttribute(k, v);
  n.append(...enfants);
  return n;
};
const ligne = (nom, valeur, texte, coche) => {
  const input = el('input', { type: 'radio', name: nom, value: valeur });
  input.checked = !!coche;
  return el('label', { class: 'ligne-choix' }, [input, el('span', { texte })]);
};
const titre = (texte) => el('h2', { class: 't-titre-2', texte });
const liste = (etiquette, lignes) => el('div', { class: 'lignes-choix', role: 'radiogroup', 'aria-label': etiquette, 'data-liste': '' }, lignes);

let choix = formulaire.querySelector('input[name="quartier"]:checked')?.value ?? null;

const chevron = () => {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'icone'); svg.setAttribute('width', '20'); svg.setAttribute('height', '20'); svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', document.querySelector('use[href*="sprite.svg"]')?.getAttribute('href').replace(/#.*/, '#i-chevron') ?? '/assets/icones/sprite.svg#i-chevron');
  svg.append(use);
  return svg;
};
function afficherCommunes() {
  zone.replaceChildren(titre(`Quelle commune${FINE}?`), el('ul', { class: 'lignes-choix', role: 'list', 'data-liste': '' },
    communes.map(([slug, nom]) => el('li', {}, [el('a', { class: 'ligne-choix ligne-choix--lien', href: `/position/quartier?commune=${encodeURIComponent(slug)}`, 'data-commune': slug }, [el('span', { texte: nom }), chevron()])]))));
}
function afficherCommune(slug) {
  const commune = communes.find((c) => c[0] === slug);
  if (!commune) return afficherCommunes();
  const changer = el('a', { class: 'lien', href: '/position/quartier', texte: 'Changer de commune' });
  changer.addEventListener('click', (e) => { e.preventDefault(); champ.value = ''; choisir(null); afficherCommunes(); });
  zone.replaceChildren(
    el('div', { class: 'position-quartier__fil' }, [el('span', { class: 't-etiquette t-secondaire', texte: `${commune[1]} ›` }), changer]),
    titre(`Quel quartier${FINE}?`),
    liste(`Quartiers de ${commune[1]}`, [
      ...quartiers.filter((q) => q[3] === slug).map((q) => ligne('quartier', q[0], q[1], q[0] === choix)),
      ligne('quartier', `commune:${slug}`, `Je ne sais pas${FINE}: centre de la commune`, choix === `commune:${slug}`),
    ]),
  );
  zone.querySelector('input:checked, input')?.focus({ preventScroll: true });
}
function afficherResultats(texte) {
  const trouves = chercher(texte);
  zone.replaceChildren(titre(`Quel quartier${FINE}?`), trouves.length
    ? liste('Quartiers trouvés', trouves.map((q) => ligne('quartier', q[0], `${q[1]}, ${q[4]}`, q[0] === choix)))
    : el('p', { class: 't-petit t-secondaire position-quartier__vide', texte: 'Aucun quartier ne correspond. Essayez un autre nom, ou choisissez votre commune.' }));
}

// ---------- Choix, récapitulatif, socle ----------
function nomDuChoix() {
  if (!choix) return null;
  if (choix.startsWith('commune:')) return communes.find((c) => c[0] === choix.slice(8))?.[1] ?? null;
  return quartiers.find((q) => q[0] === choix)?.[1] ?? null;
}
function choisir(valeur) {
  choix = valeur;
  const nom = nomDuChoix();
  const r = repere.value.trim();
  recap.hidden = !nom;
  recapTexte.textContent = nom ? nom + (r ? ` · ${r}` : '') : '';
  libelle.textContent = nom ? `Continuer${FINE}: ${nom}` : 'Continuer';
  continuer.disabled = !nom; // Désactivé : creux + la phrase qui dit ce qui manque
  if (pourquoi) pourquoi.hidden = !!nom;
}

zone.addEventListener('change', (e) => { if (e.target.name === 'quartier') choisir(e.target.value); });
// Une commune : ses quartiers s'affichent sur place (sans JS, le lien recharge la page)
zone.addEventListener('click', (e) => {
  const lien = e.target.closest('[data-commune]');
  if (!lien) return;
  e.preventDefault();
  afficherCommune(lien.dataset.commune);
});
champ.addEventListener('input', () => {
  const v = champ.value.trim();
  if (v) afficherResultats(v);
  else {
    const commune = choix ? (choix.startsWith('commune:') ? choix.slice(8) : quartiers.find((q) => q[0] === choix)?.[3]) : null;
    commune ? afficherCommune(commune) : afficherCommunes();
  }
});
repere.addEventListener('input', () => choisir(choix));
// « Changer de commune » rendu par le serveur
formulaire.querySelector('[data-changer-commune]')?.addEventListener('click', (e) => { e.preventDefault(); champ.value = ''; choisir(null); afficherCommunes(); });
// Entrée dans le champ de recherche : on ne soumet pas le formulaire
champ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); zone.querySelector('input')?.focus(); } });

choisir(choix);
