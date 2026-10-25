// Inscription mécano (M1a → M1d) : brouillon qui s'enregistre à chaque champ (RG-DO1), règles du mot de passe qui se cochent, pièces envoyées une à une, accords qui réveillent l'envoi.
// Amélioration progressive : chaque écran reste complet sans JavaScript (formulaires classiques, serveur qui valide).
import { api, chargement, csrf, toast } from '../core/app.js';

const heure = () => new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

/** « Brouillon enregistré à 10:14 » : la ligne apparaît au premier enregistrement. */
function noterBrouillon(a) {
  const ligne = document.querySelector('[data-brouillon]');
  if (!ligne) return;
  ligne.hidden = false;
  ligne.querySelector('[data-brouillon-texte]').textContent = `Brouillon enregistré à ${a || heure()}`;
}

/** Les facettes de l'écrou suivent l'état des pièces : cni_recto, cni_verso, permis, diplome, portrait, atelier (sens horaire). */
const TYPES = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait', 'atelier'];
function peindreEcrou(svg, etats) {
  svg?.querySelectorAll('.ecrou-dossier__facette').forEach((f, i) => {
    f.setAttribute('class', `ecrou-dossier__facette ecrou-dossier__facette--${(etats[TYPES[i]] || 'manquante').replace('_', '-')}`);
  });
}

// ---------- M1a · compte ----------
const compte = document.querySelector('[data-inscription-compte]');
if (compte) {
  const mdp = compte.querySelector('input[name="mot_de_passe"]');
  const regle = (cle, faite) => {
    const li = compte.querySelector(`[data-regle="${cle}"]`);
    if (!li || li.classList.contains('regle-vivante--faite') === faite) return;
    li.classList.toggle('regle-vivante--faite', faite);
    li.querySelector('[data-regle-etat]').textContent = faite ? ' : fait' : ' : à faire';
  };
  mdp?.addEventListener('input', () => { regle('longueur', mdp.value.length >= 8); regle('chiffre', /\d/.test(mdp.value)); });

  // Avant la création du compte, ce qui est saisi reste dans ce navigateur (jamais le mot de passe)
  const cle = compte.dataset.brouillonCle;
  if (cle) {
    const champs = ['prenom', 'nom', 'telephone', 'email'].map((n) => compte.elements[n]).filter(Boolean);
    try {
      const garde = JSON.parse(localStorage.getItem(cle) || 'null');
      if (garde) {
        champs.forEach((c) => { if (c.value === '' && garde.v?.[c.name]) c.value = garde.v[c.name]; });
        noterBrouillon(garde.a);
      }
    } catch { /* stockage indisponible : le formulaire marche quand même */ }
    compte.addEventListener('input', (e) => {
      if (!champs.includes(e.target)) return;
      const a = heure();
      try { localStorage.setItem(cle, JSON.stringify({ a, v: Object.fromEntries(champs.map((c) => [c.name, c.value])) })); } catch { /* idem */ }
      noterBrouillon(a);
    });
    compte.addEventListener('submit', () => { try { localStorage.removeItem(cle); } catch { /* idem */ } });
  }
  compte.addEventListener('submit', () => { const b = compte.querySelector('[data-chargement]'); if (b) chargement(b); });
}

// ---------- M1b · métier ----------
const metier = document.querySelector('[data-inscription-metier]');
if (metier) {
  const form = metier.querySelector('[data-brouillon-form]');
  const quartiers = JSON.parse(metier.dataset.quartiers || '[]'); // [libellé, lat, lon]
  const apercu = metier.querySelector('[data-apercu-ligne]');

  // Stepper : les boutons apparaissent (sans JS, on tape le nombre)
  metier.querySelectorAll('[data-stepper]').forEach((s) => {
    const champ = s.querySelector('[data-stepper-champ]');
    const [min, max] = [Number(champ.min), Number(champ.max)];
    s.querySelectorAll('[data-pas]').forEach((b) => {
      b.hidden = false;
      b.addEventListener('click', () => {
        champ.value = Math.min(max, Math.max(min, (Number(champ.value) || 0) + Number(b.dataset.pas)));
        champ.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  // Déplacement inclus : le montant s'éteint
  const inclus = form.elements.deplacement_inclus;
  const deplacement = form.elements.tarif_deplacement;
  const eteindre = () => {
    deplacement.disabled = inclus.checked;
    deplacement.closest('.champ')?.classList.toggle('champ--desactive', inclus.checked);
    if (inclus.checked) deplacement.value = '';
  };
  inclus.addEventListener('change', eteindre);

  // Compteur de la phrase de présentation
  const phrase = form.elements.presentation;
  const compteur = document.querySelector(`[data-compteur="${phrase.id}"]`);
  const compter = () => { if (compteur) compteur.textContent = `${phrase.value.length} / ${phrase.maxLength}`; };
  compter();

  // Aperçu de la ligne : spécialités et commune suivent la saisie
  const majApercu = () => {
    const libelles = [...form.querySelectorAll('input[name="specialites[]"]:checked')].map((c) => c.dataset.libelle);
    const spec = apercu?.querySelector('.ligne-classement__specialites');
    if (spec) spec.textContent = libelles.length ? libelles.join(' · ') : 'Vos spécialités';
    const q = quartiers.find((x) => x[0].toLowerCase() === form.elements.quartier.value.trim().toLowerCase());
    const meta = apercu?.querySelector('.ligne-classement__meta');
    if (meta) meta.textContent = `Nouveau · ${q ? q[0].split(',')[0] : 'Votre commune'}`;
  };

  // Mini-carte : le quartier, le repère, le cercle du rayon
  const L = window.L;
  const el = metier.querySelector('[data-carte]');
  let carte = null, repere = null, cercle = null;
  if (L && window.protomapsL && el) {
    import('../composants/carte.js').then(({ creerCarte }) => {
      carte = creerCarte(el, { dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, boxZoom: false, keyboard: false });
      const gabarit = metier.querySelector('template[data-repere]');
      repere = L.marker([Number(el.dataset.lat), Number(el.dataset.lon)], { interactive: false, keyboard: false, icon: L.divIcon({ html: gabarit.content.firstElementChild.cloneNode(true), className: 'repere-icone', iconSize: [44, 61], iconAnchor: [22, 61] }) }).addTo(carte);
      const encre = getComputedStyle(document.documentElement).getPropertyValue('--encre').trim();
      cercle = L.circle(repere.getLatLng(), { radius: 1000, color: encre, weight: 1.5, opacity: .6, dashArray: '4 4', fillColor: encre, fillOpacity: .06, interactive: false }).addTo(carte);
      majCarte();
    });
  }
  function majCarte() {
    if (!carte) return;
    const q = quartiers.find((x) => x[0].toLowerCase() === form.elements.quartier.value.trim().toLowerCase());
    const [lat, lon] = q ? [q[1], q[2]] : [Number(el.dataset.lat), Number(el.dataset.lon)];
    const km = Number(form.querySelector('input[name="rayon"]:checked')?.value || 10);
    repere.setLatLng([lat, lon]);
    cercle.setLatLng([lat, lon]).setRadius(km * 1000);
    carte.fitBounds(cercle.getBounds(), { padding: [10, 10], animate: false });
  }

  // Enregistrement champ par champ (RG-DO1)
  let attente = null;
  const charge = {};
  const envoyer = async () => {
    const donnees = { ...charge };
    Object.keys(charge).forEach((k) => delete charge[k]);
    try {
      const r = await api('/pro/api/brouillon', { methode: 'POST', donnees });
      noterBrouillon(r.enregistre_a);
      const erreurs = Object.values(r.erreurs || {});
      if (erreurs.length) toast(String(erreurs[0]), { icone: 'triangle' });
    } catch {
      toast('Pas de réseau : votre saisie sera enregistrée dès que possible.', { icone: 'triangle' });
      Object.assign(charge, donnees);
    }
  };
  const planifier = (delai = 0) => { clearTimeout(attente); attente = setTimeout(envoyer, delai); };
  form.addEventListener('change', (e) => {
    const c = e.target;
    if (c.name === 'specialites[]') charge.specialites = [...form.querySelectorAll('input[name="specialites[]"]:checked')].map((x) => x.value);
    else if (c.name === 'deplacement_inclus') { charge.deplacement_inclus = c.checked; if (!c.checked) charge.tarif_deplacement = deplacement.value; }
    else if (c.name) charge[c.name] = c.value;
    majApercu();
    majCarte();
    planifier(0);
  });
  form.addEventListener('input', (e) => {
    const c = e.target;
    if (c.name === 'presentation') { compter(); charge.presentation = c.value; planifier(600); }
    else if (c.name === 'tarif_diagnostic' || c.name === 'tarif_deplacement') { charge[c.name] = c.value; planifier(600); }
    else if (c.name === 'quartier' && quartiers.some((x) => x[0].toLowerCase() === c.value.trim().toLowerCase())) { charge.quartier = c.value; majApercu(); majCarte(); planifier(0); }
  });
  form.addEventListener('submit', () => { const b = form.querySelector('[data-chargement]'); if (b) chargement(b); });
  form.querySelectorAll('input[name="rayon"]').forEach((r) => r.addEventListener('change', () => { metier.querySelector('[data-rayon-valeur]').textContent = r.value; }));
  window.addEventListener('online', () => { if (Object.keys(charge).length) planifier(0); });
}

// ---------- M1c · pièces ----------
const pieces = document.querySelector('[data-inscription-pieces]');
if (pieces) {
  const SEGMENTS = 10;
  const attenteReseau = [];

  const majResume = (r) => {
    const phrase = pieces.querySelector('[data-pieces-phrase]');
    pieces.querySelector('[data-pieces-compte]').textContent = `${r.envoyees} / 5 obligatoires`;
    phrase.textContent = r.manque ? `${r.manque}. L’atelier est facultatif.` : 'Toutes les pièces obligatoires sont là. L’atelier est facultatif.';
    peindreEcrou(pieces.querySelector('[data-pieces-ecrou] svg'), r.etats);
    const suite = pieces.querySelector('[data-pieces-continuer]');
    const info = pieces.querySelector('.socle__info');
    if (r.manque) {
      suite.setAttribute('aria-disabled', 'true');
      if (info) info.textContent = r.manque;
    } else {
      suite.removeAttribute('aria-disabled');
      info?.remove();
    }
  };

  const etatPochette = (p, etat, texte) => {
    p.dataset.etat = etat;
    p.className = `pochette pochette--${etat.replace('_', '-')}`;
    p.querySelector('[data-pochette-etat] span').textContent = texte;
    p.querySelector('.pochette__remplacer').hidden = etat !== 'envoyee';
    p.querySelector('.pochette__actions').hidden = !['manquante', 'a_reprendre'].includes(etat);
    p.querySelector('.pochette__jauge').hidden = etat !== 'envoi';
  };
  const jauge = (p, pct) => p.querySelectorAll('.pochette__jauge i').forEach((i, n) => i.classList.toggle('plein', n < Math.round(pct / 100 * SEGMENTS)));

  function envoyerPiece(p, fichier, precedent) {
    const form = p.querySelector('[data-pochette-form]');
    const donnees = new FormData();
    donnees.append('fichier', fichier);
    donnees.append('_csrf', csrf());
    etatPochette(p, 'envoi', 'Envoi 0 %');
    jauge(p, 0);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF', csrf());
    xhr.upload.onprogress = (e) => {
      if (!e.lengthComputable) return;
      const pct = Math.round(e.loaded / e.total * 100);
      p.querySelector('[data-pochette-etat] span').textContent = `Envoi ${pct} %`;
      jauge(p, pct);
    };
    xhr.onload = () => {
      let r = {};
      try { r = JSON.parse(xhr.responseText); } catch { /* réponse illisible */ }
      if (xhr.status === 200 && r.ok) {
        const ko = Math.max(1, Math.round(r.piece.taille_octets / 1024));
        etatPochette(p, 'envoyee', `Envoyée · ${ko.toLocaleString('fr-FR').replace(/\s/g, ' ')} Ko`);
        const specimen = pieces.querySelector('template[data-specimen]');
        if (specimen && p.dataset.pochette.startsWith('cni_')) p.querySelector('.pochette__vignette').replaceChildren(specimen.content.cloneNode(true));
        noterBrouillon(r.enregistre_a);
        majResume(r);
      } else {
        etatPochette(p, precedent.etat, precedent.texte);
        toast(r.erreur || 'Cette pièce n’a pas pu être envoyée. Réessayez.', { icone: 'triangle' });
      }
    };
    xhr.onerror = () => { // coupure : l'envoi reprend tout seul au retour du réseau (« Envoi repris »)
      p.querySelector('[data-pochette-etat] span').textContent = 'En attente du réseau';
      attenteReseau.push({ p, fichier, precedent });
    };
    xhr.send(donnees);
  }

  pieces.addEventListener('change', (e) => {
    const champ = e.target.closest('[data-pochette-champ]');
    const fichier = champ?.files?.[0];
    if (!fichier) return;
    const p = champ.closest('.pochette');
    const precedent = { etat: p.dataset.etat, texte: p.querySelector('[data-pochette-etat] span').textContent };
    champ.value = '';
    envoyerPiece(p, fichier, precedent);
  });
  window.addEventListener('online', () => {
    while (attenteReseau.length) {
      const { p, fichier, precedent } = attenteReseau.shift();
      toast('Envoi repris', { icone: 'coche' });
      envoyerPiece(p, fichier, precedent);
    }
  });
  pieces.addEventListener('click', (e) => { if (e.target.closest('[data-pieces-continuer][aria-disabled="true"]')) e.preventDefault(); });
}

// ---------- M1d · relire et envoyer ----------
const envoi = document.querySelector('[data-envoi]');
if (envoi) {
  const cases = [...envoi.querySelectorAll('[data-accord]')];
  const bouton = envoi.querySelector('[data-envoyer]');
  const info = envoi.querySelector('.socle__info');
  const maj = () => {
    const pret = cases.every((c) => c.checked);
    bouton.classList.toggle('bouton--eteint', !pret);
    bouton.setAttribute('aria-disabled', String(!pret));
    if (info) info.hidden = pret;
  };
  cases.forEach((c) => c.addEventListener('change', maj));
  maj();
  envoi.addEventListener('submit', (e) => {
    if (!cases.every((c) => c.checked)) {
      e.preventDefault();
      toast('Cochez les 3 cases pour envoyer votre dossier.', { icone: 'triangle' });
      return;
    }
    chargement(bouton);
  });
}
