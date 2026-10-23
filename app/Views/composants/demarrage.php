<?php
/**
 * Loader 1 · Démarrage, variante A « Serrage » (docs/mouvement-et-interactions.html).
 * Ne joue qu'à la première page de la session (sessionStorage) ; le cookie de session « demarrage » évite
 * ensuite d'envoyer ce bloc. Sans JS il reste masqué. SVG + CSS + JS en ligne : il n'attend rien.
 *   html.demarrage           le loader est affiché, le logo de la barre est masqué
 *   html.demarrage-attente   l'arrivée du contenu ([data-entree]) attend la fin du loader
 */
if (isset($_COOKIE['demarrage'])) {
    return;
}
// Tracés du logo : « all », écrou-repère, « mécano » (public/assets/img/logo-allomecano.svg)
preg_match_all('/\sd="([^"]+)"/', (string) file_get_contents(RACINE . '/public/assets/img/logo-allomecano.svg'), $m);
$traces = ['all' => $m[1][0] ?? '', 'mecano' => array_slice($m[1], 2)];
// Symbole (Logo/symbole, Figma 34:113) : 6 facettes entre l'hexagone R 52 et le trou r 20, centre (60, 58)
$pt = fn (float $rayon, float $deg) => [round(60 + $rayon * cos(deg2rad($deg)), 2), round(58 + $rayon * sin(deg2rad($deg)), 2)];
$facettes = $joints = [];
$hexagone = [];
for ($k = 0; $k < 6; $k++) {
    $a = -90 + 60 * $k;
    $b = $a + 60;
    [$ax, $ay] = $pt(52, $a); [$bx, $by] = $pt(52, $b); [$cx, $cy] = $pt(20, $b); [$dx, $dy] = $pt(20, $a);
    $facettes[] = "M$ax {$ay}L$bx {$by}L$cx {$cy}A20 20 0 0 0 $dx {$dy}Z";
    [$x1, $y1] = $pt(17, $a); [$x2, $y2] = $pt(57, $a);
    $joints[] = "<line x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\"/>";
    $hexagone[] = "$ax $ay";
}
?>
<style>
.ecran-demarrage{display:none;position:fixed;inset:0;z-index:100;pointer-events:none}
html.demarrage .ecran-demarrage{display:block}
html.demarrage .barre__logo svg{visibility:hidden}
.ecran-demarrage__fond{position:absolute;inset:0;background:var(--surface)}
.ecran-demarrage__mot{position:absolute;left:0;top:0;width:200px;height:40.6px;overflow:visible;transform-origin:55.27px 18.72px;color:var(--encre)}
.ecran-demarrage__mot .d-all,.ecran-demarrage__mot .d-meca{fill:currentColor;opacity:0}
.ecran-demarrage .d-sym{transform-box:view-box;transform-origin:60px 58px}
.ecran-demarrage .d-trace path,.ecran-demarrage .d-trace circle,.ecran-demarrage .d-queue-trace{fill:none;stroke:var(--encre-2);stroke-width:1.2;stroke-dasharray:3 2.6}
.ecran-demarrage .d-fa{fill:currentColor;transform-box:fill-box;transform-origin:center}
.ecran-demarrage .d-queue{fill:currentColor}
.ecran-demarrage .d-joints{stroke:var(--surface);stroke-width:3.2}
.ecran-demarrage .d-gris{fill:var(--gris-voyant);transform-box:fill-box;transform-origin:center}
.ecran-demarrage .d-jaune{fill:var(--balise);opacity:0}
.ecran-demarrage__lent{position:absolute;left:var(--marge);right:var(--marge);bottom:56px;margin:0;text-align:center;color:var(--encre-2)}
</style>
<div class="ecran-demarrage" id="demarrage" aria-hidden="true">
  <div class="ecran-demarrage__fond"></div>
  <svg class="ecran-demarrage__mot" viewBox="0 0 197 40">
    <defs><clipPath id="d-jauge"><rect id="d-niveau" x="40" y="100" width="40" height="0"/></clipPath></defs>
    <path class="d-all" d="<?= e($traces['all']) ?>"/>
    <g class="d-meca"><?php foreach ($traces['mecano'] as $d): ?><path d="<?= e($d) ?>"/><?php endforeach ?></g>
    <g transform="translate(38.895 3.496) scale(0.259)"><g class="d-sym">
      <g class="d-trace"><path d="M<?= implode('L', $hexagone) ?>Z"/><circle cx="60" cy="58" r="20"/></g>
      <path class="d-queue-trace" d="M46 101.9L60 138L74 101.9"/>
      <?php foreach ($facettes as $d): ?><path class="d-fa" d="<?= $d ?>"/><?php endforeach ?>
      <g clip-path="url(#d-jauge)"><path class="d-queue" d="M46 101.9L74 101.9L60 138Z"/></g>
      <g class="d-joints"><?= implode('', $joints) ?></g>
      <circle class="d-gris" cx="60" cy="58" r="9.5"/>
      <circle class="d-jaune" cx="60" cy="58" r="9.5"/>
    </g></g>
  </svg>
  <p class="ecran-demarrage__lent t-etiquette" hidden>Réseau lent · on charge l’essentiel</p>
</div>
<script>
(function () {
  var h = document.documentElement, L = document.getElementById('demarrage');
  try { if (sessionStorage.getItem('demarrage')) { L.remove(); return; } sessionStorage.setItem('demarrage', '1'); } catch (e) { L.remove(); return; }
  document.cookie = 'demarrage=1; Path=/; SameSite=Lax';
  if (!L.animate) { L.remove(); return; }
  h.classList.add('demarrage', 'demarrage-attente');
  var $ = function (s) { return L.querySelector(s); }, fa = L.querySelectorAll('.d-fa');
  var W = $('.ecran-demarrage__mot'), sym = $('.d-sym'), trace = $('.d-trace'), queueTrace = $('.d-queue-trace'), niveau = $('#d-niveau');
  var gris = $('.d-gris'), jaune = $('.d-jaune'), joints = $('.d-joints'), fond = $('.ecran-demarrage__fond'), lent = $('.ecran-demarrage__lent');
  var E = { out: 'cubic-bezier(.2,.8,.2,1)', back: 'cubic-bezier(.34,1.56,.64,1)', inout: 'cubic-bezier(.65,0,.35,1)', linear: 'linear' };
  var O = { x: 55.27, y: 18.72 }, ECHELLE = 4.2, anims = [];
  var reduit = matchMedia('(prefers-reduced-motion: reduce)').matches, retour = false;
  try { retour = !!localStorage.getItem('allomecano-vu'); localStorage.setItem('allomecano-vu', '1'); } catch (e) {}
  function a(el, kf, o, vitesse) {
    var an = el.animate(kf, { duration: o.d, delay: o.at || 0, easing: E[o.e || 'linear'], fill: o.f || 'both', iterations: o.n || 1 });
    an.playbackRate = vitesse || 1; anims.push(an); return an;
  }
  function fini(liste) { return Promise.all(liste.map(function (x) { return x.finished.catch(function () {}); })); }
  function attendre(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
  // Position de départ : l'écrou au centre (46 % de la hauteur), ×4,2
  var Lx = innerWidth / 2 - O.x, Ly = innerHeight * .46 - O.y;
  W.style.left = Lx + 'px'; W.style.top = Ly + 'px'; W.style.transform = 'scale(' + ECHELLE + ')';
  // « Contenu prêt » = DOMContentLoaded + polices (plafonné à 2,5 s). La carte n'est jamais attendue.
  var pret = false, t0 = performance.now(), jauge = 0;
  var dom = new Promise(function (r) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', r) : r(); });
  var polices = Promise.race([document.fonts ? document.fonts.ready : Promise.resolve(), attendre(2500)]);
  var prete = Promise.all([dom, polices]).then(function () { pret = true; });
  // Jauge : la pointe se remplit au rythme du vrai chargement, jamais à 100 % avant d'être prête
  (function boucle(now) {
    var p = pret ? 1 : .88 * (1 - Math.exp(-Math.max(0, now - t0) / 700));
    jauge += (p - jauge) * .14; niveau.setAttribute('height', (38 * jauge).toFixed(2));
    if (!(pret && jauge > .995)) requestAnimationFrame(boucle); else niveau.setAttribute('height', '38');
  })(t0);
  var minuterie = setTimeout(function () { if (!pret) lent.hidden = false; }, 3000);
  function terminer() {
    clearTimeout(minuterie); h.classList.remove('demarrage', 'demarrage-attente');
    anims.forEach(function (x) { x.cancel(); }); L.remove();
  }
  async function jouer() {
    // On démarre à la première image réellement peinte (les feuilles de style du <head> bloquent l'affichage)
    await new Promise(function (r) { requestAnimationFrame(function () { requestAnimationFrame(r); }); });
    if (reduit) { // logo complet immobile, seule la jauge avance, fondu de 200 ms
      trace.style.opacity = 0; queueTrace.style.opacity = 0; joints.style.strokeWidth = 0; jaune.style.opacity = 1;
      await prete; await attendre(220); h.classList.remove('demarrage-attente');
      await a(L, [{ opacity: 1 }, { opacity: 0 }], { d: 200 }).finished; return terminer();
    }
    var v = retour ? 2.5 : 1, vf = retour ? 1.6 : 1, construction = [];
    if (!(retour && pret)) {
      construction.push(a(sym, [{ transform: 'rotate(-24deg)' }, { transform: 'rotate(0deg)' }], { d: 640, e: 'out' }, v));
      construction.push(a(trace, [{ opacity: 0 }, { opacity: 1, offset: .15 }, { opacity: 1, offset: .7 }, { opacity: 0 }], { d: 760 }, v));
      fa.forEach(function (f, k) {
        var ang = (-60 + 60 * k) * Math.PI / 180, dx = Math.cos(ang) * 10, dy = Math.sin(ang) * 10;
        construction.push(a(f, [{ opacity: 0, transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.9)' }, { opacity: 1, transform: 'none' }], { at: k * 55, d: 220, e: 'back' }, v));
      });
      construction.push(a(gris, [{ transform: 'scale(0)' }, { transform: 'scale(1)' }], { at: 420, d: 280, e: 'back' }, v));
      construction.push(a(jaune, [{ opacity: 0 }, { opacity: 1 }], { at: 660, d: 100 }, v));
      construction.push(a(joints, [{ strokeWidth: 3.2 }, { strokeWidth: 0 }], { at: 740, d: 140, e: 'inout' }, v));
      // Contenu prêt pendant la construction : le reste passe à ×2, sans couper net
      prete.then(function () { construction.forEach(function (x) { try { x.updatePlaybackRate(x.playbackRate * 2); } catch (e) {} }); });
      await fini(construction);
    } else { trace.style.opacity = 0; joints.style.strokeWidth = 0; jaune.style.opacity = 1; }
    var souffle = pret ? null : a(jaune, [{ opacity: 1 }, { opacity: .35 }, { opacity: 1 }], { d: 1000, e: 'inout', n: Infinity });
    await prete; if (souffle) souffle.cancel();
    var tw = performance.now(); while (jauge < .98 && performance.now() - tw < 250) await attendre(16);
    lent.hidden = true;
    // Fin : l'écrou rétrécit et prend la place du « o », le nom se pose, puis monte dans la barre
    var tx = O.x - 100, ty = O.y - 20.3;
    a(W, [{ transform: 'scale(' + ECHELLE + ')' }, { transform: 'translate(' + tx + 'px,' + ty + 'px) scale(1)' }], { d: 380, e: 'out' }, vf);
    a(queueTrace, [{ opacity: 1 }, { opacity: 0 }], { d: 120 }, vf);
    a($('.d-all'), [{ opacity: 0, transform: 'translateX(10px)' }, { opacity: 1, transform: 'none' }], { at: 100, d: 280, e: 'out' }, vf);
    a($('.d-meca'), [{ opacity: 0, transform: 'translateX(-10px)' }, { opacity: 1, transform: 'none' }], { at: 140, d: 280, e: 'out' }, vf);
    var cible = document.querySelector('.barre__logo svg'), fin;
    if (cible && cible.getBoundingClientRect().width) {
      var r = cible.getBoundingClientRect(), s = r.width / 200;
      fin = a(W, [{ transform: 'translate(' + tx + 'px,' + ty + 'px) scale(1)' }, { transform: 'translate(' + (r.left - Lx - O.x + s * O.x) + 'px,' + (r.top - Ly - O.y + s * O.y) + 'px) scale(' + s + ')' }], { at: 480, d: 400, e: 'inout', f: 'forwards' }, vf); // « forwards » : ne masque pas le retrait pendant l'attente
    } else {
      fin = a(W, [{ opacity: 1 }, { opacity: 0 }], { at: 480, d: 300, f: 'forwards' }, vf);
    }
    a(fond, [{ opacity: 1 }, { opacity: 0 }], { at: 540, d: 300 }, vf);
    setTimeout(function () { h.classList.remove('demarrage-attente'); }, 600 / vf); // arrivée du contenu
    await fin.finished.catch(function () {});
    terminer();
  }
  jouer().catch(terminer);
})();
</script>
