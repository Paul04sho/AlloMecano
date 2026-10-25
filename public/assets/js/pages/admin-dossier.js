// A2 · Examen d'un dossier : visionneuses (zoom, pivoter, luminosité, déplacement), pièce par pièce, décision et aperçu du message, raccourcis clavier.
// Sans JS, le formulaire est complet : le serveur vérifie tout (ExamenDossierService::decider), ici on ne fait que guider l'admin.
import { toast } from '../core/app.js';

const form = document.querySelector('[data-examen]');
if (form) {
  const base = form.getAttribute('action').replace(/\/decision$/, '');
  const OBLIGATOIRES = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait'];
  const heure = () => new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Africa/Abidjan' });

  // ---------- Visionneuses ----------
  const viseurs = new Map();
  document.querySelectorAll('[data-visionneuse]').forEach((v) => {
    const etat = { zoom: 100, rot: 0, lum: 0, x: 0, y: 0 };
    const img = v.querySelector('[data-image]');
    const panneau = v.querySelector('[data-panneau]');
    const sortie = v.querySelector('[data-zoom-valeur]');
    const appliquer = () => {
      img.style.transform = `translate(${etat.x}px, ${etat.y}px) rotate(${etat.rot}deg) scale(${etat.zoom / 100})`;
      img.style.filter = `brightness(${[1, 1.35, 1.7][etat.lum]})`;
      sortie.textContent = `${etat.zoom} %`;
    };
    const zoomer = (pas) => { etat.zoom = Math.max(50, Math.min(400, etat.zoom + pas * 25)); if (etat.zoom === 100) etat.x = etat.y = 0; appliquer(); };
    v.querySelectorAll('[data-zoom]').forEach((b) => b.addEventListener('click', () => zoomer(Number(b.dataset.zoom))));
    v.querySelector('[data-pivoter]').addEventListener('click', () => { etat.rot = (etat.rot + 90) % 360; appliquer(); });
    v.querySelector('[data-luminosite]').addEventListener('click', () => { etat.lum = (etat.lum + 1) % 3; appliquer(); });
    panneau.addEventListener('contextmenu', (e) => e.preventDefault()); // aucun téléchargement (RG-P6)
    panneau.addEventListener('wheel', (e) => { if (e.ctrlKey || e.metaKey) { e.preventDefault(); zoomer(e.deltaY < 0 ? 1 : -1); } }, { passive: false });
    let depart = null;
    panneau.addEventListener('pointerdown', (e) => { depart = { x: e.clientX - etat.x, y: e.clientY - etat.y }; panneau.classList.add('est-saisi'); panneau.setPointerCapture(e.pointerId); });
    panneau.addEventListener('pointermove', (e) => { if (depart && etat.zoom > 100) { etat.x = e.clientX - depart.x; etat.y = e.clientY - depart.y; appliquer(); } });
    const fin = () => { depart = null; panneau.classList.remove('est-saisi'); };
    panneau.addEventListener('pointerup', fin);
    panneau.addEventListener('pointercancel', fin);
    viseurs.set(v.dataset.visionneuse, { v, img, etat, zoomer, appliquer });
  });

  // ---------- Pièce affichée dans la visionneuse de droite ----------
  const droite = viseurs.get('piece');
  let affichee = 'cni_recto';
  const afficher = (type, titre) => {
    if (type === affichee) return;
    const ancienne = form.querySelector(`[data-piece="${affichee}"]`);
    ancienne?.classList.remove('ligne-piece--affichee');
    const e = ancienne?.querySelector('[data-etat]');
    if (e && !e.classList.contains('ligne-piece__etat--erreur')) e.textContent = `Consultée · ${heure()}`;
    affichee = type;
    droite.img.src = `${base}/pieces/${type}`; // chaque consultation est inscrite au journal par le serveur (RG-P6)
    droite.etat.zoom = 100; droite.etat.rot = 0; droite.etat.x = droite.etat.y = 0; droite.appliquer();
    droite.v.querySelector('[data-visionneuse-titre]').textContent = titre;
    const segment = droite.v.querySelector('.visionneuse__segment');
    if (segment) segment.hidden = !type.startsWith('cni_');
    const ligne = form.querySelector(`[data-piece="${type}"]`);
    ligne?.classList.add('ligne-piece--affichee');
    const n = ligne?.querySelector('[data-etat]');
    if (n && !n.classList.contains('ligne-piece__etat--erreur')) n.textContent = 'Affichée';
    form.querySelectorAll('[data-face]').forEach((r) => { r.checked = r.value === type; });
  };
  form.querySelectorAll('[data-afficher]').forEach((b) => b.addEventListener('click', () => afficher(b.dataset.afficher, b.dataset.titre)));
  form.querySelectorAll('[data-face]').forEach((r) => r.addEventListener('change', () => afficher(r.value, r.value === 'cni_recto' ? 'PIÈCE D’IDENTITÉ · RECTO' : 'PIÈCE D’IDENTITÉ · VERSO')));

  // ---------- Décision ----------
  const valider = form.querySelector('[data-valider]');
  const renvoyer = form.querySelector('[data-renvoyer]');
  const info = form.querySelector('[data-decision-info]');
  const titre = form.querySelector('[data-decision-titre]');
  const reprises = form.querySelector('[data-reprises]');
  const apercu = form.querySelector('[data-apercu-texte]');
  const NOMS = { cni_recto: 'la CNI recto', cni_verso: 'la CNI verso', permis: 'le permis', diplome: 'le diplôme', portrait: 'la photo de profil', atelier: 'la photo de l’atelier' };

  const decisions = () => Object.fromEntries([...form.querySelectorAll('input[name^="piece["]:checked')].map((r) => [r.name.slice(6, -1), r.value]));
  const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

  let minuteur;
  const majApercu = () => {
    const d = decisions();
    const params = new URLSearchParams();
    Object.entries(d).filter(([, v]) => v === 'a_reprendre').forEach(([t]) => {
      params.set(`piece[${t}]`, 'a_reprendre');
      params.set(`motif[${t}]`, form.querySelector(`input[name="motif[${t}]"]:checked`)?.value ?? 'photo_floue');
      params.set(`precision[${t}]`, form.querySelector(`[name="precision[${t}]"]`)?.value ?? '');
    });
    if (![...params.keys()].length) return;
    clearTimeout(minuteur);
    minuteur = setTimeout(async () => {
      try { apercu.textContent = (await (await fetch(`${form.dataset.apercu}?${params}`, { headers: { Accept: 'application/json' } })).json()).message; } catch { /* l'aperçu reste celui du serveur */ }
    }, 250);
  };

  const maj = () => {
    const d = decisions();
    const conformes = OBLIGATOIRES.filter((t) => d[t] === 'conforme').length;
    const aReprendre = Object.keys(d).filter((t) => d[t] === 'a_reprendre');
    const oblAReprendre = aReprendre.filter((t) => OBLIGATOIRES.includes(t));
    const tousDecides = OBLIGATOIRES.every((t) => !form.querySelector(`[data-piece="${t}"] input[type="radio"]`) || d[t]);
    const motifsOk = aReprendre.every((t) => {
      const m = form.querySelector(`input[name="motif[${t}]"]:checked`)?.value;
      return m && (m !== 'autre' || form.querySelector(`[name="precision[${t}]"]`)?.value.trim());
    });
    const concordances = [...form.querySelectorAll('[data-concordance]')].every((c) => c.checked) && form.querySelector('[data-visage]')?.checked;
    titre.textContent = `${pluriel(conformes, 'pièce')} conforme${conformes > 1 ? 's' : ''} sur 5`;
    form.querySelectorAll('[data-reprise]').forEach((p) => { p.hidden = d[p.dataset.reprise] !== 'a_reprendre'; });
    reprises.hidden = aReprendre.length === 0;
    form.querySelectorAll('[data-piece]').forEach((l) => {
      const t = l.dataset.piece;
      l.classList.toggle('ligne-piece--a-reprendre', d[t] === 'a_reprendre');
      const e = l.querySelector('[data-etat]');
      if (e) { e.classList.toggle('ligne-piece__etat--erreur', d[t] === 'a_reprendre'); e.classList.toggle('t-secondaire', d[t] !== 'a_reprendre'); if (d[t] === 'a_reprendre') e.textContent = 'À reprendre'; else if (e.textContent === 'À reprendre') e.textContent = t === affichee ? 'Affichée' : 'Consultée'; }
    });
    // Valider : cinq pièces conformes, trois concordances, le visage
    const peutValider = conformes === 5 && concordances;
    valider?.setAttribute('aria-disabled', peutValider ? 'false' : 'true');
    if (renvoyer) {
      renvoyer.hidden = oblAReprendre.length === 0;
      renvoyer.setAttribute('aria-disabled', tousDecides && motifsOk ? 'false' : 'true');
      form.querySelector('[data-renvoyer-libelle]').textContent = `Renvoyer pour correction (${pluriel(oblAReprendre.length, 'pièce')})`;
    }
    if (info) {
      info.hidden = peutValider;
      const raison = oblAReprendre.length === 1 ? `${NOMS[oblAReprendre[0]]} est à reprendre` : oblAReprendre.length > 1 ? `${oblAReprendre.length} pièces sont à reprendre` : conformes < 5 ? 'des pièces restent à examiner' : 'cochez les trois concordances et « Le visage correspond à la pièce »';
      info.querySelector('span').textContent = conformes === 5 ? `Validation impossible : ${raison}.` : `Validation impossible : ${raison}. Elle se débloque quand les 5 pièces sont conformes.`;
    }
    majApercu();
  };
  form.addEventListener('change', maj);
  form.addEventListener('input', (e) => { if (e.target.name?.startsWith('precision[')) maj(); });
  maj();

  form.addEventListener('submit', (e) => {
    const bouton = e.submitter;
    if (bouton?.getAttribute('aria-disabled') === 'true') {
      e.preventDefault();
      toast(info?.querySelector('span')?.textContent || 'Décision impossible pour le moment.', { icone: 'info' });
    }
  });

  // ---------- Refus définitif : une confirmation avec motif ----------
  const dialogue = form.querySelector('[data-refus]');
  form.querySelector('[data-refuser]')?.addEventListener('click', () => { dialogue.showModal?.(); dialogue.querySelector('textarea')?.focus(); });
  form.querySelector('[data-refus-annuler]')?.addEventListener('click', () => dialogue.close());

  // ---------- Raccourcis : J/K pièce · C conforme · R à reprendre · Z zoom · V valider ----------
  const types = [...form.querySelectorAll('[data-piece]')].filter((l) => l.querySelector('input[type="radio"]')).map((l) => l.dataset.piece);
  let courante = affichee;
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select, dialog') || e.altKey || e.ctrlKey || e.metaKey) return;
    const k = e.key.toLowerCase();
    if (k === 'j' || k === 'k') {
      const i = Math.max(0, Math.min(types.length - 1, types.indexOf(courante) + (k === 'j' ? 1 : -1)));
      courante = types[i];
      form.querySelector(`[data-piece="${courante}"] [data-afficher]`)?.click();
      form.querySelector(`[data-piece="${courante}"] [data-afficher]`)?.focus();
    } else if (k === 'c' || k === 'r') {
      const radio = form.querySelector(`[data-piece="${courante}"] input[value="${k === 'c' ? 'conforme' : 'a_reprendre'}"]`);
      if (radio && !radio.disabled) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
    } else if (k === 'z') {
      const v = droite; v.etat.zoom === 100 ? v.zoomer(4) : (v.etat.zoom = 100, v.etat.x = v.etat.y = 0, v.appliquer()); // 100 % → 200 %, puis retour
    } else if (k === 'v') {
      if (valider?.getAttribute('aria-disabled') === 'false') form.requestSubmit(valider); else toast(info?.querySelector('span')?.textContent || 'Validation impossible.', { icone: 'info' });
    } else return;
    e.preventDefault();
  });
}
