// C4 · Résultats (60:196), C4c (75:1070), D1 (156:274) : carte, repères, ligne à vol d'oiseau, feuille basse,
// choix d'un mécano sur la carte (une seule ligne étendue à la fois), filtres légers et résumé modifiable (desktop).
import { creerCarte } from '../composants/carte.js';
import { feuilleBasse } from '../composants/feuille-basse.js';
import { initialiserFiche } from '../composants/fiche.js';

const racine = document.querySelector('[data-resultats]');
const L = window.L;
const moi = [Number(racine.dataset.lat), Number(racine.dataset.lon)];
const precision = Number(racine.dataset.precision) || 0;
const rayonKm = Number(racine.dataset.rayon) || 10;
const mecanos = JSON.parse(racine.dataset.mecanos || '[]');
const reduit = matchMedia('(prefers-reduced-motion: reduce)').matches;
const mobile = () => matchMedia('(max-width: 1023.98px)').matches;
const encre = getComputedStyle(document.documentElement).getPropertyValue('--encre').trim();
const gabarit = (sel) => racine.querySelector(sel)?.innerHTML ?? '';

// ---------- Feuille basse (mobile) ----------
const feuille = racine.querySelector('[data-feuille]');
const vue = racine.querySelectorAll('[data-vue]');
const panneau = feuilleBasse(feuille, {
  poignee: racine.querySelector('[data-poignee]'), depart: 1,
  auChangement: (cran) => vue.forEach((b) => b.setAttribute('aria-pressed', String((b.dataset.vue === 'liste') === (cran === 2)))),
});
vue.forEach((b) => b.addEventListener('click', () => panneau.aller(b.dataset.vue === 'liste' ? 2 : 1))); // « Liste » → cran 92 %

// « Appeler Seydou » doit tenir sur une ligne : sinon WhatsApp passe en icône seule
function ajusterActions() {
  racine.querySelectorAll('.resultats__mecano.choisi .ligne-classement__actions').forEach((a) => {
    a.classList.remove('ligne-classement__actions--compactes');
    const l = [...a.querySelectorAll('form:first-child .bouton__libelle')].find((x) => x.offsetWidth);
    if (l && l.scrollWidth > l.clientWidth) a.classList.add('ligne-classement__actions--compactes');
  });
}
ajusterActions();
addEventListener('resize', ajusterActions);

// ---------- Carte ----------
const carte = creerCarte(racine.querySelector('[data-carte]'));
L.marker(moi, { icon: L.divIcon({ html: gabarit('template[data-vous]'), className: 'repere-icone', iconSize: [24, 24], iconAnchor: [12, 12] }), keyboard: false, interactive: false, zIndexOffset: 500 }).addTo(carte);
if (precision > 0) L.circle(moi, { radius: precision, color: encre, weight: 1.5, opacity: .4, dashArray: '4 4', fillColor: encre, fillOpacity: .06, interactive: false }).addTo(carte);

// Marge à laisser autour des repères : la feuille couvre le bas de la carte sur mobile, la barre flottante le haut
// (haut : barre 100 + hauteur d'un repère, inutile sans repère)
const marges = (haut = 180) => (mobile() ? { paddingTopLeft: [16, haut], paddingBottomRight: [16, panneau.hauteur() + 16] } : { padding: [64, 64] });

// Rendus par le bloc « mécanos » ci-dessous, utilisés par le panneau de fiche (D1)
let choisirMecano = () => {};
let recadrer = () => {};

if (mecanos.length === 0) {
  // C4c : la zone cherchée (rayon réellement utilisé), carte dézoomée
  const zone = L.circle(moi, { radius: rayonKm * 1000, color: encre, weight: 1.5, opacity: .5, dashArray: '6 6', fill: false, interactive: false }).addTo(carte);
  carte.fitBounds(zone.getBounds(), { ...marges(16), animate: false }); // 75:1070 : la barre flottante chevauche le haut du cercle
} else {
  const ligne = L.polyline([], { color: encre, weight: 3, dashArray: '9 7', interactive: false }).addTo(carte);
  const marqueurs = new Map();
  let choisi = mecanos[0].rang;

  const icone = (m, actif, i) => L.divIcon({
    html: `<button type="button" class="repere-pose" style="--i:${i}" aria-label="${m.prenom}, ${m.distance} km" aria-pressed="${actif}">${gabarit(actif ? `template[data-repere-choisi="${m.rang}"]` : `template[data-repere="${m.rang}"]`)}</button>`,
    className: 'repere-icone', iconSize: actif ? [56, 82] : [36, 53], iconAnchor: actif ? [28, 82] : [18, 53],
  });

  mecanos.forEach((m, i) => {
    if (m.lat === null) return;
    const mk = L.marker([m.lat, m.lon], { icon: icone(m, m.rang === choisi, i), keyboard: false, zIndexOffset: m.rang === choisi ? 400 : 0 }).addTo(carte);
    mk.on('click', () => choisir(m.rang, true));
    marqueurs.set(m.rang, mk);
  });

  // Ligne à vol d'oiseau : 120 ms après le dernier repère, en fondu
  const tracer = () => {
    const m = mecanos.find((x) => x.rang === choisi);
    ligne.setLatLngs(m?.lat !== null ? [moi, [m.lat, m.lon]] : []);
  };
  setTimeout(() => {
    tracer();
    ligne.getElement()?.animate([{ opacity: 0 }, { opacity: 1 }], { duration: reduit ? 1 : 240 });
  }, reduit ? 0 : 320 + 70 * mecanos.length + 120);

  const bornes = L.latLngBounds([moi, ...mecanos.filter((m) => m.lat !== null).map((m) => [m.lat, m.lon])]);
  // Fiche ouverte (D1) : la carte utile s'arrête au bord du panneau de 420 px
  recadrer = (avecPanneau, animer = true) => carte.fitBounds(bornes, {
    ...(avecPanneau ? { paddingTopLeft: [64, 64], paddingBottomRight: [64 + 420, 64] } : marges()), maxZoom: 16, animate: animer && !reduit,
  });
  recadrer(racine.classList.contains('resultats--fiche'), false);

  // Choix d'un mécano sur la carte : sa ligne devient l'étendue (une seule à la fois)
  function choisir(rang, depuisCarte) {
    if (rang === choisi) return;
    const avant = choisi;
    choisi = rang;
    [avant, rang].forEach((r) => {
      const m = mecanos.find((x) => x.rang === r);
      marqueurs.get(r)?.setIcon(icone(m, r === rang, 0)).setZIndexOffset(r === rang ? 400 : 0);
    });
    const bouton = marqueurs.get(rang)?.getElement()?.querySelector('button');
    bouton?.classList.remove('repere-pose');
    if (depuisCarte && document.activeElement === document.body) bouton?.focus({ preventScroll: true }); // le clavier garde sa place (l'icône a été remplacée)
    tracer();
    const ancien = racine.querySelector('.resultats__mecano.choisi');
    const nouveau = racine.querySelector(`.resultats__mecano[data-rang="${rang}"]`);
    const montrer = () => {
      ancien?.classList.remove('choisi');
      nouveau.classList.add('choisi');
      ajusterActions();
      const etendue = nouveau.querySelector('.resultats__etendue');
      etendue.querySelector('.ligne-classement__rubrique').textContent = depuisCarte && rang !== mecanos[0].rang ? 'Choisi sur la carte' : 'Le plus proche';
      if (!reduit) { etendue.classList.remove('entre'); void etendue.offsetWidth; etendue.classList.add('entre'); }
      if (depuisCarte) nouveau.scrollIntoView({ block: 'nearest', behavior: reduit ? 'auto' : 'smooth' });
    };
    const sortant = ancien?.querySelector('.resultats__etendue');
    if (sortant && !reduit) sortant.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateX(-12px)' }], { duration: 120, easing: 'cubic-bezier(.2,.8,.2,1)' }).finished.then(montrer);
    else montrer();
  }

  choisirMecano = choisir;

  // ---------- Filtres légers (desktop) : masquent des lignes de la liste figée, sans relancer ----------
  const filtres = racine.querySelectorAll('[data-filtre]');
  const vide = racine.querySelector('[data-filtre-vide]');
  filtres.forEach((f) => f.addEventListener('change', () => {
    const specialiste = racine.querySelector('[data-filtre="specialiste"]').checked;
    const note = racine.querySelector('[data-filtre="note"]').checked;
    let visibles = 0;
    racine.querySelectorAll('.resultats__mecano').forEach((li) => {
      const ok = (!specialiste || li.dataset.specialiste === '1') && (!note || Number(li.dataset.note) >= 4);
      li.hidden = !ok;
      marqueurs.get(Number(li.dataset.rang))?.setOpacity(ok ? 1 : 0);
      if (ok) visibles++;
    });
    vide.hidden = visibles > 0;
    const premier = racine.querySelector('.resultats__mecano:not([hidden])');
    if (premier && racine.querySelector('.resultats__mecano.choisi')?.hidden) choisir(Number(premier.dataset.rang), false);
  }));
}

// Rayon (desktop) : relance la recherche avec ce rayon
racine.querySelector('[data-rayon]')?.addEventListener('change', (e) => e.target.form.submit());

// ---------- Contrôles de carte (desktop) ----------
racine.querySelectorAll('button[data-zoom]').forEach((b) => b.addEventListener('click', () => carte.setZoom(carte.getZoom() + Number(b.dataset.zoom))));
racine.querySelector('[data-recentrer]')?.addEventListener('click', () => carte.setView(moi, Math.max(carte.getZoom(), 15)));

// ---------- Résumé modifiable sur place (desktop) ----------
document.querySelectorAll('[data-resume-item]').forEach((item) => {
  const bouton = item.querySelector('button[aria-controls]');
  const panneauEdition = item.querySelector('.resume__panneau');
  const fermer = () => { panneauEdition.hidden = true; bouton.setAttribute('aria-expanded', 'false'); };
  bouton.addEventListener('click', () => {
    const ouvrir = panneauEdition.hidden;
    document.querySelectorAll('.resume__panneau').forEach((p) => { p.hidden = true; });
    document.querySelectorAll('.resume__segment[aria-expanded]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    panneauEdition.hidden = !ouvrir;
    bouton.setAttribute('aria-expanded', String(ouvrir));
    if (ouvrir) panneauEdition.querySelector('input:checked, input')?.focus();
  });
  item.addEventListener('keydown', (e) => { if (e.key === 'Escape') { fermer(); bouton.focus(); } });
  document.addEventListener('click', (e) => { if (!item.contains(e.target)) fermer(); });
});

// ---------- Fiche ouverte sur la carte (D1 · 158:542) : panneau de 420 px, la liste reste à gauche ----------
const bureau = matchMedia('(min-width: 1024px)');
const zoneCarte = racine.querySelector('.resultats__carte');
const rechercheId = racine.dataset.recherche;

function lierPanneau(panneau, ligne) {
  const slug = panneau.dataset.slug;
  initialiserFiche(panneau, slug);
  panneau.querySelector('#panneau-nom')?.focus({ preventScroll: true });
  panneau.querySelectorAll('[data-fermer-fiche]').forEach((b) => b.addEventListener('click', () => fermerFiche()));
  if (ligne) choisirMecano(Number(ligne.dataset.rang), false);
}

async function ouvrirFiche(slug, ligne) {
  const dejaOuvert = zoneCarte.querySelector('[data-panneau]');
  if (dejaOuvert?.dataset.slug === slug) return;
  let html;
  try {
    const r = await fetch(`/api/mecano/${encodeURIComponent(slug)}/fiche?r=${encodeURIComponent(rechercheId)}`, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (!r.ok) throw new Error(String(r.status));
    html = await r.text();
  } catch {
    location.href = `/mecano/${encodeURIComponent(slug)}?r=${encodeURIComponent(rechercheId)}`; // la fiche en pleine page prend le relais
    return;
  }
  dejaOuvert?.remove();
  zoneCarte.insertAdjacentHTML('beforeend', html); // gabarit fiche-panneau.php : tout le texte y passe par e()
  const panneau = zoneCarte.querySelector('[data-panneau]');
  if (dejaOuvert) panneau.style.animation = 'none'; // changement de fiche : pas de nouvelle glissade
  racine.classList.add('resultats--fiche');
  document.querySelectorAll('.resultats__mecano.ouvert').forEach((l) => l.classList.remove('ouvert'));
  ligne?.classList.add('ouvert');
  history.replaceState(null, '', `${location.pathname}?fiche=${encodeURIComponent(slug)}`);
  lierPanneau(panneau, ligne);
  recadrer(true);
}

function fermerFiche() {
  const panneau = zoneCarte.querySelector('[data-panneau]');
  if (!panneau) return;
  const fin = () => {
    panneau.remove();
    racine.classList.remove('resultats--fiche');
    recadrer(false);
  };
  const ligne = document.querySelector('.resultats__mecano.ouvert');
  document.querySelectorAll('.resultats__mecano.ouvert').forEach((l) => l.classList.remove('ouvert'));
  history.replaceState(null, '', location.pathname);
  if (reduit) fin();
  else { panneau.classList.add('ferme'); panneau.addEventListener('animationend', fin, { once: true }); }
  ligne?.querySelector('a')?.focus({ preventScroll: true });
}

// Un appui sur une ligne ouvre la fiche à côté sur desktop ; sur mobile le lien mène à la fiche en pleine page
racine.addEventListener('click', (e) => {
  if (!bureau.matches || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
  const lien = e.target.closest('a[href^="/mecano/"]');
  const ligne = lien?.closest('[data-fiche]');
  if (!ligne) return;
  e.preventDefault();
  ouvrirFiche(ligne.dataset.fiche, ligne.closest('.resultats__mecano'));
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && zoneCarte.querySelector('[data-panneau]') && !document.querySelector('.resume__panneau:not([hidden])')) fermerFiche(); });

const present = zoneCarte.querySelector('[data-panneau]');
if (present) lierPanneau(present, document.querySelector('.resultats__mecano.ouvert'));
