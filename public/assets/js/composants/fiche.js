// Fiche mécanicien (C5, panneau de D1) : « moment 5 » du prototype, copie du message, interrupteur de position, avis en plus.
// Une même fonction sert la page et le panneau : elle reçoit la racine qui contient la fiche.
import { api, toast } from '../core/app.js';

const reduit = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Photo : couleur dominante → photo en fondu, 240 ms. */
function photo(racine) {
  const img = racine.querySelector('[data-portrait]');
  if (!img) return;
  const montrer = () => requestAnimationFrame(() => img.classList.add('charge'));
  if (img.complete && img.naturalWidth > 0) montrer();
  else { img.addEventListener('load', montrer, { once: true }); img.addEventListener('error', montrer, { once: true }); }
}

/** Le poinçon s'imprime : scale 1,3 → 1 et opacité, 200 ms ease-tampon, 150 ms après la photo, une fois par session et par mécano. */
function poincon(racine, slug) {
  const p = racine.querySelector('[data-poincon]');
  if (!p) return;
  const cle = 'poincon-' + slug;
  let dejaVu = false;
  try { dejaVu = sessionStorage.getItem(cle) === '1'; sessionStorage.setItem(cle, '1'); } catch { /* stockage fermé : on tamponne chaque fois */ }
  if (dejaVu || reduit()) { p.classList.add('pose'); return; }
  p.animate([{ opacity: 0, transform: 'scale(1.3)' }, { opacity: 1, transform: 'scale(1)' }],
    { delay: 240 + 150, duration: 200, easing: 'cubic-bezier(.3, 1.4, .5, 1)', fill: 'both' }).finished.then(() => p.classList.add('pose'), () => p.classList.add('pose'));
}

/** Bornes : les chiffres se comptent de 0 à la valeur, 520 ms après 300 ms, sortie cubique. */
function bornes(racine) {
  if (reduit()) return;
  racine.querySelectorAll('[data-compte]').forEach((el) => {
    const vers = Number(el.dataset.compte);
    const final = el.textContent;
    const decimales = String(el.dataset.compte).includes('.') ? 1 : 0;
    const format = (v) => v.toLocaleString('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
    el.textContent = format(0);
    const debut = performance.now() + 300;
    (function pas(now) {
      const t = Math.max(0, Math.min(1, (now - debut) / 520));
      el.textContent = t === 1 ? final : format(vers * (1 - (1 - t) ** 3));
      if (t < 1 && el.isConnected) requestAnimationFrame(pas);
      else el.textContent = final;
    })(performance.now());
  });
}

/** « Copier » et l'interrupteur « Lien de ma position » : la fin du message disparaît en fondu (160 ms). */
export function initialiserMessage(racine) {
  const bloc = racine.querySelector('[data-bloc-message]');
  if (!bloc) return;
  const debut = bloc.querySelector('[data-message-debut]');
  const position = bloc.querySelector('[data-message-position]');
  const interrupteur = bloc.querySelector('[data-interrupteur-position]');
  const avecPosition = () => position !== null && (interrupteur ? interrupteur.checked : true);

  const texte = () => debut.textContent + (avecPosition() ? ' ' + position.textContent : '');
  bloc.querySelector('[data-copier]')?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(texte());
      toast('Copié', { icone: 'coche' });
    } catch {
      // Presse-papiers refusé (HTTP, vieux navigateur) : on sélectionne le texte pour que le client le copie à la main
      const sel = getSelection();
      const plage = document.createRange();
      plage.selectNodeContents(bloc.querySelector('[data-message]'));
      sel?.removeAllRanges();
      sel?.addRange(plage);
      toast('Sélectionné : copiez-le avec votre téléphone', { icone: 'info' });
    }
  });

  interrupteur?.addEventListener('change', () => {
    const actif = interrupteur.checked;
    racine.querySelectorAll('[data-champ-position]').forEach((c) => { c.value = actif ? '1' : '0'; });
    position.hidden = false;
    if (reduit()) { position.hidden = !actif; position.classList.toggle('efface', !actif); return; }
    position.classList.toggle('efface', !actif);
    if (!actif) setTimeout(() => { if (!interrupteur.checked) position.hidden = true; }, 160);
  });
}

/** « Lire les 37 avis » : les avis suivants arrivent par paquets, sans quitter la fiche. */
function avis(racine, slug) {
  const section = racine.querySelector('[data-avis]');
  const lien = section?.querySelector('[data-plus-avis]');
  if (!lien) return;
  lien.addEventListener('click', async (e) => {
    e.preventDefault();
    lien.setAttribute('aria-busy', 'true');
    try {
      const r = await api('/api/mecano/' + encodeURIComponent(slug) + '/avis?decalage=' + section.dataset.decalage);
      section.querySelector('[data-liste-avis]').insertAdjacentHTML('beforeend', r.html); // composants/avis : tout le texte client y passe par e()
      section.dataset.decalage = r.decalage;
      if (r.reste > 0) lien.textContent = 'Voir les ' + r.reste + ' autres avis';
      else lien.remove();
    } catch {
      toast('Les avis n’ont pas pu se charger', { icone: 'triangle' });
    } finally {
      lien.removeAttribute('aria-busy');
    }
  });
}

export function initialiserFiche(racine, slug) {
  photo(racine);
  poincon(racine, slug);
  bornes(racine);
  initialiserMessage(racine);
  avis(racine, slug);
}
