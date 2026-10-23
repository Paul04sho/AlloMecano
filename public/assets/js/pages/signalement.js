// C7 · Signaler : la photo est allégée (300 Ko au plus) et ré-encodée avant l'envoi, ce qui retire l'EXIF et la position (RG-S3).
// Sans JS, le fichier part tel quel et le serveur fait le même travail.
import { chargement, toast } from '../core/app.js';

const MAX_OCTETS = 300 * 1024;

/** Réduit l'image (côté le plus long 1600 px, qualité qui baisse) jusqu'à tenir dans 300 Ko ; nul si le navigateur ne sait pas la lire. */
async function alleger(fichier) {
  let bitmap;
  try { bitmap = await createImageBitmap(fichier, { imageOrientation: 'from-image' }); } catch { return null; }
  let cote = Math.min(1600, Math.max(bitmap.width, bitmap.height));
  for (let essai = 0; essai < 6; essai++) {
    const echelle = cote / Math.max(bitmap.width, bitmap.height);
    const toile = document.createElement('canvas');
    toile.width = Math.max(1, Math.round(bitmap.width * echelle));
    toile.height = Math.max(1, Math.round(bitmap.height * echelle));
    const ctx = toile.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, toile.width, toile.height);
    ctx.drawImage(bitmap, 0, 0, toile.width, toile.height);
    const blob = await new Promise((ok) => toile.toBlob(ok, 'image/jpeg', Math.max(0.4, 0.8 - 0.08 * essai)));
    if (blob && blob.size <= MAX_OCTETS) return new File([blob], 'photo.jpg', { type: 'image/jpeg' });
    cote = Math.round(cote * 0.8);
  }
  return null;
}

const form = document.querySelector('[data-signalement-formulaire]');
if (form) {
  const zone = form.querySelector('[data-champ-photo]');
  const entree = zone.querySelector('input[type="file"]');
  const choisie = zone.querySelector('[data-photo-choisie]');
  const nom = zone.querySelector('[data-photo-nom]');

  const afficher = (fichier) => {
    zone.classList.toggle('champ-photo--choisie', Boolean(fichier));
    choisie.hidden = !fichier;
    nom.textContent = fichier ? `${fichier.name} · ${Math.max(1, Math.round(fichier.size / 1024))} Ko` : '';
  };

  entree.addEventListener('change', async () => {
    const f = entree.files[0];
    if (!f) return afficher(null);
    const legere = await alleger(f);
    if (legere) {
      const lot = new DataTransfer();
      lot.items.add(legere);
      entree.files = lot.files;
      afficher(legere);
    } else {
      entree.value = '';
      afficher(null);
      toast('Cette photo ne peut pas être utilisée. Essayez-en une autre.');
    }
  });
  zone.querySelector('[data-photo-retirer]').addEventListener('click', () => { entree.value = ''; afficher(null); entree.focus(); });

  form.addEventListener('submit', () => {
    const bouton = form.querySelector('button[type="submit"]');
    if (bouton) chargement(bouton);
  });
}
