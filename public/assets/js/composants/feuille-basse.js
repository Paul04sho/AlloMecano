// Feuille basse (C4 60:352) : 3 crans (132 px, 56 %, 92 %), suit le doigt 1:1 ; au lâcher, cran le plus proche,
// ou le suivant si le geste est rapide (au-delà de 0,5 px/ms). Uniquement sur mobile (la feuille est une colonne sur desktop).

export function feuilleBasse(feuille, { poignee, depart = 1, auChangement } = {}) {
  const crans = () => [132, innerHeight * 0.56, innerHeight * 0.92];
  const mobile = () => matchMedia('(max-width: 1023.98px)').matches;
  let cran = depart, hauteurDepart = 0, yDepart = 0, dernier = { y: 0, t: 0 }, vitesse = 0, glisse = false;

  const poser = (h) => feuille.style.setProperty('--feuille-h', `${Math.round(h)}px`);
  function aller(i) {
    cran = Math.max(0, Math.min(2, i));
    feuille.classList.remove('glisse');
    poser(crans()[cran]);
    auChangement?.(cran);
  }
  function lacher() {
    if (!glisse) return;
    glisse = false;
    const h = feuille.getBoundingClientRect().height, c = crans();
    let cible;
    if (Math.abs(vitesse) > 0.5) cible = vitesse < 0 ? c.findIndex((x) => x > h + 1) : c.findLastIndex((x) => x < h - 1);
    if (cible === undefined || cible < 0) cible = c.reduce((m, x, i) => (Math.abs(x - h) < Math.abs(c[m] - h) ? i : m), 0);
    aller(cible);
  }
  poignee?.addEventListener('pointerdown', (e) => {
    if (!mobile()) return;
    glisse = true;
    feuille.classList.add('glisse');
    hauteurDepart = feuille.getBoundingClientRect().height;
    yDepart = e.clientY; dernier = { y: e.clientY, t: e.timeStamp }; vitesse = 0;
    poignee.setPointerCapture(e.pointerId);
  });
  poignee?.addEventListener('pointermove', (e) => {
    if (!glisse) return;
    const c = crans();
    poser(Math.min(c[2], Math.max(c[0], hauteurDepart - (e.clientY - yDepart))));
    if (e.timeStamp > dernier.t) vitesse = (e.clientY - dernier.y) / (e.timeStamp - dernier.t);
    dernier = { y: e.clientY, t: e.timeStamp };
  });
  poignee?.addEventListener('pointerup', lacher);
  poignee?.addEventListener('pointercancel', lacher);
  // Clavier : la poignée fait passer au cran suivant (puis revient au premier)
  poignee?.addEventListener('click', (e) => { if (e.detail === 0) aller((cran + 1) % 3); });
  addEventListener('resize', () => { if (mobile()) poser(crans()[cran]); });
  if (mobile()) poser(crans()[cran]);
  return { aller, hauteur: () => (mobile() ? crans()[cran] : 0) };
}
