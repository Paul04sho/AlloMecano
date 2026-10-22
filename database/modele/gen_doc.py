import os, sys, html, re
sys.path.insert(0, os.path.dirname(__file__)); D=os.path.join(os.path.dirname(__file__),'..')
from model import T, LATE_FKS, SEED, DOMAINS
from content import PERIMETRE, DECISIONS, REGLES, TACHES, CONSERVATION, A_VERIFIER, SPEC_CHANGES
E = lambda s: html.escape(str(s), quote=True)
TB = {t['name']:t for t in T}
n_fk = sum(len(t['fks']) for t in T)+len(LATE_FKS)
n_cols = sum(len(t['cols']) for t in T)
SQL = open(os.path.join(D,'schema.sql')).read()
RECH = open(os.path.join(D,'recherche.sql')).read()

# ---------------------------------------------------------------- Mermaid ER
def short(ty):
    t=ty.split('(')[0].split()[0].lower()
    return {'tinyint':'int','smallint':'int','mediumtext':'text'}.get(t,t)
GROUPS=[
 ("Mécano, dossier et disponibilité",["mecaniciens","mecanicien_specialites","specialites","mecanicien_vehicules","types_vehicule","pieces_dossier","types_piece","historique_dossier","historique_disponibilite","abonnements_push","photos","quartiers","communes","admins"]),
 ("Recherche, mise en relation et appel",["clients","recherches","resultats_recherche","pannes","panne_specialite","specialites","types_vehicule","quartiers","contacts","signalisation","mecaniciens"]),
 ("Confiance : avis, signalements, suspensions",["avis","avis_etiquettes","etiquettes_avis","signalements","suspensions","contacts","clients","mecaniciens"]),
 ("Système : admins, journal, notifications, contact",["admins","journal_admin","notifications","jetons_reinitialisation","messages_contact","mecaniciens"]),
]
KEYCOLS={"mecaniciens":["statut_dossier","etat","dispo_lat","dispo_lon","rayon_km"],"contacts":["canal","statut","issue_client","issue_mecano"],
 "recherches":["type_vehicule","panne_id","rayon_km"],"avis":["note","statut"],"pieces_dossier":["statut"],"signalements":["motif","statut"]}
def er(names):
    s=["erDiagram"]
    allfk=[(t['name'],)+fk for t in T for fk in t['fks']]+[(a,b,c,d,e) for a,b,c,d,e in LATE_FKS]
    for n in names:
        t=TB[n]; fkcols={f[0] for f in t['fks']}|{b for a,b,c,d,e in LATE_FKS if a==n}
        keep=[c for c in t['cols'] if c[0] in t['pk'] or c[0] in fkcols or c[0] in KEYCOLS.get(n,[])]
        s.append(f"  {n} {{")
        for c in keep:
            tag=[]
            if c[0] in t['pk']: tag.append("PK")
            if c[0] in fkcols: tag.append("FK")
            s.append(f"    {short(c[1])} {c[0]} {','.join(tag)}".rstrip())
        s.append("  }")
    seen=set()
    for child,col,parent,pcol,od in allfk:
        if child in names and parent in names:
            ct=TB[child]; nullable=[c for c in ct['cols'] if c[0]==col][0][2]
            uniq=any(cs==[col] for _,cs in ct['uniques'])
            left="|o" if nullable else "||"; right="o|" if uniq else "o{"
            key=(child,col,parent)
            if key in seen: continue
            seen.add(key)
            s.append(f'  {parent} {left}--{right} {child} : "{col}"')
    return "\n".join(s)

ST_DOSSIER="""stateDiagram-v2
  [*] --> brouillon
  brouillon --> en_attente : envoi (pièces + CGU)
  en_attente --> valide : toutes les pièces validées
  en_attente --> a_reprendre : une pièce à reprendre
  a_reprendre --> en_attente : nouvel envoi
  en_attente --> refuse : refus motivé
  valide --> en_attente : nom ou portrait modifié
  valide --> suspendu : décision admin
  suspendu --> valide : levée
  refuse --> [*]"""
ST_DISPO="""stateDiagram-v2
  [*] --> indisponible
  indisponible --> disponible : « Me rendre disponible » + GPS
  disponible --> indisponible : manuel, 12 h, 3 appels manqués, suspension
  disponible --> en_intervention : « J'y vais »
  en_intervention --> disponible : « Je suis libre » ou 2 h
  en_intervention --> indisponible : session de plus de 12 h"""
ST_APPEL="""stateDiagram-v2
  [*] --> initie : clic « Appeler »
  initie --> sonne : notification envoyée
  initie --> occupe : mécano déjà en appel
  initie --> echoue : micro refusé
  sonne --> decroche : le mécano décroche
  sonne --> manque : 30 s sans réponse
  sonne --> annule : le client raccroche
  sonne --> echoue : audio impossible (15 s)
  decroche --> termine : l'un raccroche
  manque --> [*] : mécano suivant
  occupe --> [*] : mécano suivant
  echoue --> [*] : son numéro
  termine --> [*] : « Vous y allez ? »"""
SEQ="""sequenceDiagram
  participant C as Client
  participant S as Serveur PHP + MySQL
  participant P as Service de push
  participant M as Mécano
  C->>S: Créer le contact (canal app)
  S->>S: Règles A3, A4, A10
  S-->>C: Contact + identifiants du relais (1 h)
  loop toutes les 5 s, 30 s max
    S->>P: Notification « Appel entrant »
    P->>M: Sonnerie et vibration
  end
  C->>S: Offre audio
  M->>S: Décrocher, puis réponse audio
  C->>S: Interrogation chaque seconde
  S-->>C: Réponse du mécano
  C-->>M: Audio direct, ou via le relais
  M->>S: Raccrocher (durée enregistrée)
  M->>S: « J'y vais »
  S-->>C: « Koffi arrive »"""

# ---------------------------------------------------------------- HTML
def dict_table(t):
    fkmap={f[0]:f for f in t['fks']}
    for a,b,c,d,e in LATE_FKS:
        if a==t['name']: fkmap[b]=(b,c,d,e)
    uqcols={cs[0] for _,cs in t['uniques'] if len(cs)==1}
    rows=[]
    for n,ty,nul,de,desc in t['cols']:
        tags=[]
        if n in t['pk']: tags.append('<span class="k pk">PK</span>')
        if n in uqcols: tags.append('<span class="k uq">UQ</span>')
        fk=fkmap.get(n)
        link=f'<a href="#t-{fk[1]}">{fk[1]}.{fk[2]}</a> <span class="od">{fk[3].lower()}</span>' if fk else ''
        de2='' if de is None else E(de.replace("CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP","maintenant, mis à jour").replace("CURRENT_TIMESTAMP(3)","maintenant").replace("CURRENT_TIMESTAMP","maintenant").strip("'"))
        rows.append(f'<tr><td class="cn">{E(n)} {"".join(tags)}</td><td class="ty">{E(ty.replace(" AUTO_INCREMENT"," auto"))}</td><td class="nu">{"oui" if nul else "—"}</td><td class="ty">{de2}</td><td>{E(desc)}</td><td class="fk">{link}</td></tr>')
    idx=[f'<code>{E(nm)}</code> ({", ".join(cs)})' for nm,cs in t['uniques']+t['indexes']]
    chk=[f'<code>{E(ex)}</code>' for nm,ex in t['checks']]
    refs=sorted({x['name'] for x in T for f in x['fks'] if f[1]==t['name'] and x['name']!=t['name']}|{a for a,b,c,d,e in LATE_FKS if c==t['name']})
    meta=[]
    if idx: meta.append('<b>Index</b> '+' · '.join(idx))
    if chk: meta.append('<b>Contrôles</b> '+' · '.join(chk))
    if refs: meta.append('<b>Référencée par</b> '+' · '.join(f'<a href="#t-{r}">{r}</a>' for r in refs))
    if t['note']: meta.append('<b>Note</b> '+E(t['note']))
    return f'''<article class="tcard" id="t-{t['name']}" data-q="{E((t['name']+' '+t['role']+' '+' '.join(c[0] for c in t['cols'])).lower())}">
  <header><h4>{t['name']}</h4><span class="ver">{E(t['version'])}</span></header>
  <p class="role">{E(t['role'])}</p>
  <div class="tbl"><table class="cols"><thead><tr><th>Colonne</th><th>Type</th><th>Nul</th><th>Défaut</th><th>Description</th><th>Lien</th></tr></thead><tbody>{''.join(rows)}</tbody></table></div>
  <div class="meta">{'<br>'.join(meta)}</div>
</article>'''

dom_sections=[]
for code,label,desc in DOMAINS:
    ts=[t for t in T if t['dom']==code]
    dom_sections.append(f'<section class="dom"><h3>{E(label)} <small>{len(ts)} tables</small></h3><p class="note">{E(desc)}</p>{"".join(dict_table(t) for t in ts)}</section>')

def rules_html():
    out=[]
    for g,rs in REGLES:
        items=''.join(f'<li id="rg-{c.lower()}"><span class="code">RG-{c}</span><div><b>{E(ti)}</b><p>{E(tx)}</p><span class="tabs">{E(tb)}</span></div></li>' for c,ti,tx,tb in rs)
        out.append(f'<div class="rgroup"><h3>{E(g)}</h3><ol class="rules">{items}</ol></div>')
    return ''.join(out)

perim=''.join(f'<div class="pcard"><div class="ph"><h3>{E(n)}</h3><span class="ver">{v}</span></div><ul>{"".join(f"<li>{E(i)}</li>" for i in items)}</ul></div>' for n,v,items in PERIMETRE)
decis=''.join(f'<tr><td class="lab">{E(a)}</td><td>{E(b)}</td></tr>' for a,b in DECISIONS)
changes=''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td></tr>' for a,b in SPEC_CHANGES)
taches=''.join(f'<tr><td class="ty">{E(a)}</td><td><code>{E(b)}</code></td><td>{E(c)}</td></tr>' for a,b,c in TACHES)
conserv=''.join(f'<tr><td>{E(a)}</td><td>{E(b)}</td><td class="ty">{E(c)}</td></tr>' for a,b,c in CONSERVATION)
verif=''.join(f'<tr><td class="lab">{E(a)}</td><td>{E(b)}</td></tr>' for a,b in A_VERIFIER)
params=''.join(f'<tr><td><code>{E(c)}</code></td><td class="ty">{E(v)}</td><td>{E(d)}</td></tr>' for c,v,t_,d in SEED['parametres'])
ers=''.join(f'<figure class="plan"><figcaption>{E(g)}</figcaption><pre class="mermaid">{E(er(ns))}</pre></figure>' for g,ns in GROUPS)
alone=[t['name'] for t in T if not t['fks'] and not any(f[1]==t['name'] for x in T for f in x['fks'])]

page=f'''<title>Modèle de données allomécano</title>
<meta name="claude:viewport-fit-sides" content="cover">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=Atkinson+Hyperlegible+Mono:wght@400;700&family=Atkinson+Hyperlegible+Next:wght@400;700&display=swap">
<style>
/* Dossier technique : colonne de lecture + sommaire latéral sur grand écran. Identité allomécano (béton, bitume, balise). */
:root{{--surface:#f4f5f2;--creuse:#e6e8e3;--elevee:#fff;--encre:#15171c;--encre-2:#565b64;--filet:#d3d6cf;--bitume:#15171c;--balise:#ffcb05;--plan:#ffffff;--lien:#15171c;
 --f-d:"Archivo","Arial Narrow",system-ui,sans-serif;--f-b:"Atkinson Hyperlegible Next","Atkinson Hyperlegible",system-ui,sans-serif;--f-m:"Atkinson Hyperlegible Mono",ui-monospace,Menlo,monospace}}
@media (prefers-color-scheme:dark){{:root:not([data-theme="light"]){{--surface:#15171c;--creuse:#1e2127;--elevee:#22252c;--encre:#f4f5f2;--encre-2:#a3a9b1;--filet:#2e323a;--plan:#f4f5f2;--lien:#f4f5f2;color-scheme:dark}}}}
:root[data-theme="dark"]{{--surface:#15171c;--creuse:#1e2127;--elevee:#22252c;--encre:#f4f5f2;--encre-2:#a3a9b1;--filet:#2e323a;--plan:#f4f5f2;--lien:#f4f5f2;color-scheme:dark}}
*{{box-sizing:border-box}}
body{{background:var(--surface);color:var(--encre);font:16px/1.55 var(--f-b);margin:0}}
a{{color:var(--lien);text-underline-offset:3px}}
.layout{{max-width:1240px;margin:0 auto;display:grid;grid-template-columns:220px minmax(0,1fr);gap:48px;padding-block:40px 80px;
 padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px))}}
@media (max-width:1000px){{.layout{{grid-template-columns:minmax(0,1fr)}} nav.side{{position:static!important;max-height:none!important}}}}
nav.side{{position:sticky;top:calc(24px + env(safe-area-inset-top,0px));align-self:start;max-height:calc(100vh - 48px);overflow:auto;display:grid;gap:4px;font:700 13px/1.3 var(--f-m);letter-spacing:.04em}}
nav.side a{{text-decoration:none;padding:6px 0;border-bottom:1px solid var(--filet)}}
nav.side .lb{{font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--encre-2);margin-bottom:6px}}
main{{min-width:0;display:grid;gap:56px}}
.layout>*,main>*,section.s>*,.dom>*,.states>*{{min-width:0}}
.plan svg{{max-width:100%;height:auto}}
.label{{font:700 12px/1.2 var(--f-m);letter-spacing:.09em;text-transform:uppercase;color:var(--encre-2)}}
h1,h2,h3,h4{{font-family:var(--f-d);font-stretch:75%;font-weight:800;line-height:1.04;margin:0;letter-spacing:-.01em;text-wrap:balance}}
h1{{font-size:clamp(40px,6vw,64px)}} h2{{font-size:clamp(30px,4vw,40px)}} h3{{font-size:24px}} h4{{font-size:20px;font-family:var(--f-m);font-stretch:100%;font-weight:700;letter-spacing:0}}
h3 small{{font:400 14px var(--f-m);color:var(--encre-2);margin-left:8px}}
p{{margin:0}} .note{{color:var(--encre-2);max-width:68ch}}
section.s{{display:grid;gap:20px;border-top:2px solid var(--encre);padding-top:20px}}
.hero{{display:grid;gap:16px}} .hero p{{max-width:66ch;color:var(--encre-2)}}
.kpis{{display:flex;flex-wrap:wrap;gap:0;border-top:1px solid var(--filet);border-bottom:1px solid var(--filet)}}
.kpis div{{padding:14px 28px 14px 0;margin-right:28px;display:grid;gap:4px}} .kpis div+div{{border-left:1px solid var(--filet);padding-left:28px;margin-left:-28px}}
.kpis b{{font:800 44px/1 var(--f-d);font-stretch:75%;font-variant-numeric:tabular-nums}} .kpis span{{font:700 11px var(--f-m);letter-spacing:.09em;text-transform:uppercase;color:var(--encre-2)}}
.pgrid{{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}}
.pcard{{background:var(--elevee);padding:18px 18px 14px;display:grid;gap:10px;align-content:start}}
.pcard .ph{{display:flex;justify-content:space-between;gap:10px;align-items:baseline}}
.pcard ul{{margin:0;padding-left:18px;display:grid;gap:6px;font-size:15px}}
.ver{{font:700 11px/1 var(--f-m);letter-spacing:.08em;border:1.5px solid var(--encre);padding:4px 6px;white-space:nowrap;border-radius:2px}}
.tbl{{overflow-x:auto}}
table{{border-collapse:collapse;width:100%;font-size:14px}}
th,td{{text-align:left;vertical-align:top;padding:8px 12px 8px 0;border-bottom:1px solid var(--filet)}}
th{{font:700 11px/1.2 var(--f-m);letter-spacing:.08em;text-transform:uppercase;color:var(--encre-2)}}
td.lab{{font:700 12px/1.4 var(--f-m);letter-spacing:.06em;text-transform:uppercase;white-space:nowrap;color:var(--encre-2)}}
td.ty,td.nu{{font:400 13px/1.4 var(--f-m);white-space:nowrap}}
code{{font:400 13px var(--f-m);background:var(--creuse);padding:1px 5px;border-radius:2px;overflow-wrap:anywhere}}
.plan{{margin:0;background:var(--plan);color:#15171c;padding:16px;overflow-x:auto;clip-path:polygon(24px 0,100% 0,100% 100%,0 100%,0 13.9px)}}
.plan figcaption{{font:700 12px var(--f-m);letter-spacing:.09em;text-transform:uppercase;color:#565b64;margin-bottom:8px}}
.plan pre.mermaid{{margin:0;min-width:640px;background:none}}
.states{{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}}
.rgroup{{display:grid;gap:8px}} .rgroup+.rgroup{{margin-top:20px}}
ol.rules{{list-style:none;margin:0;padding:0;display:grid}}
ol.rules li{{display:grid;grid-template-columns:76px minmax(0,1fr);gap:14px;padding:12px 0;border-bottom:1px solid var(--filet)}}
ol.rules .code{{font:700 13px var(--f-m);background:var(--bitume);color:#f4f5f2;padding:4px 6px;height:max-content;width:max-content;border-radius:2px}}
ol.rules p{{color:var(--encre);max-width:72ch;margin-top:2px}}
ol.rules .tabs{{font:400 12px var(--f-m);color:var(--encre-2)}}
.filter{{display:flex;gap:12px;align-items:center;flex-wrap:wrap;position:sticky;top:env(safe-area-inset-top,0px);background:var(--surface);padding:10px 0;z-index:2;border-bottom:1px solid var(--filet)}}
.filter input{{height:44px;border:2px solid var(--encre-2);border-radius:2px;background:var(--elevee);color:var(--encre);font:400 16px var(--f-b);padding:0 12px;min-width:0;flex:1;max-width:420px}}
.filter input:focus{{outline:3px solid var(--balise);outline-offset:0;border-color:var(--encre)}}
.dom{{display:grid;gap:14px}} .dom+.dom{{margin-top:28px}}
.tcard{{background:var(--elevee);padding:18px;display:grid;gap:10px;min-width:0}}
.tcard header{{display:flex;gap:12px;align-items:baseline;justify-content:space-between;flex-wrap:wrap}}
.tcard .role{{color:var(--encre-2);font-size:15px;max-width:80ch}}
table.cols{{min-width:860px}}
td.cn{{font:700 13px/1.4 var(--f-m);white-space:nowrap}}
.k{{font:700 10px var(--f-m);padding:2px 4px;border-radius:2px;margin-left:4px;vertical-align:1px}}
.k.pk{{background:var(--bitume);color:#f4f5f2}} .k.uq{{border:1px solid var(--encre-2);color:var(--encre-2)}}
td.fk{{font:400 13px var(--f-m);white-space:nowrap}} .od{{color:var(--encre-2);font-size:11px}}
.meta{{font-size:13px;color:var(--encre-2);line-height:1.7}} .meta b{{font:700 11px var(--f-m);letter-spacing:.08em;text-transform:uppercase;color:var(--encre);margin-right:6px}}
.codeblock{{position:relative;background:#15171c;color:#e6e8e3;border-radius:2px}}
.codeblock pre{{margin:0;padding:18px;overflow:auto;font:400 12.5px/1.55 var(--f-m);max-height:560px;white-space:pre}}
.cp{{position:absolute;right:10px;top:10px;background:#2c3037;color:#f4f5f2;border:0;font:700 12px var(--f-m);padding:7px 9px;cursor:pointer;border-radius:2px;z-index:1}}
.cp:focus-visible,a:focus-visible,summary:focus-visible{{outline:3px solid var(--balise);outline-offset:2px}}
details summary{{cursor:pointer;font:700 15px var(--f-b);padding:10px 0}}
.empty{{color:var(--encre-2)}}
</style>

<div class="layout">
<nav class="side" aria-label="Sommaire">
  <span class="lb">Sommaire</span>
  <a href="#perimetre">1 · Périmètre</a><a href="#decisions">2 · Décisions</a><a href="#schema">3 · Schéma</a><a href="#etats">4 · États et appel</a>
  <a href="#regles">5 · Règles métier</a><a href="#dico">6 · Dictionnaire</a><a href="#recherche">7 · Requête de recherche</a><a href="#taches">8 · Tâches planifiées</a>
  <a href="#conservation">9 · Conservation</a><a href="#parametres">10 · Paramètres</a><a href="#verifier">11 · À vérifier</a><a href="#sql">12 · Script SQL</a>
</nav>
<main>
  <header class="hero">
    <span class="label">allomécano · Phase 5 · analyse avant le code</span>
    <h1>Modèle de données</h1>
    <p>Ce que fait la plateforme en V1, toutes les règles métier numérotées, et la base MySQL qui les porte : tables, liens et contraintes. Le script SQL et ce document sont générés depuis la même définition, et le script a été chargé sans erreur sur MariaDB 10.11.</p>
    <div class="kpis"><div><b>{len(T)}</b><span>tables</span></div><div><b>{n_fk}</b><span>clés étrangères</span></div><div><b>{n_cols}</b><span>colonnes</span></div><div><b>{sum(len(r) for _,r in REGLES)}</b><span>règles métier</span></div></div>
  </header>

  <section class="s" id="perimetre"><span class="label">1</span><h2>Périmètre fonctionnel</h2>
    <p class="note">Tout ce qui est dessiné dans le Figma, plus l'appel intégré (8 écrans ajoutés). Tout est livré pour le concours, avec la même finition.</p>
    <div class="pgrid">{perim}</div></section>

  <section class="s" id="decisions"><span class="label">2</span><h2>Décisions validées</h2>
    <div class="tbl"><table><tbody>{decis}</tbody></table></div>
    <h3 style="margin-top:12px">Écarts avec la spec de design</h3><p class="note">À reporter dans la spec et dans les textes des écrans.</p>
    <div class="tbl"><table><thead><tr><th>La spec dit</th><th>On fait</th></tr></thead><tbody>{changes}</tbody></table></div></section>

  <section class="s" id="schema"><span class="label">3</span><h2>Schéma relationnel</h2>
    <p class="note">Quatre vues pour rester lisible. Chaque vue ne montre que les clés et quelques colonnes structurantes ; le détail est dans le dictionnaire. Le trait double signifie « obligatoire », le rond « facultatif », la patte « plusieurs ». Tables sans lien : {", ".join(f"<code>{a}</code>" for a in alone)}.</p>
    {ers}</section>

  <section class="s" id="etats"><span class="label">4</span><h2>États et déroulé de l'appel</h2>
    <div class="states">
      <figure class="plan"><figcaption>Dossier du mécano · mecaniciens.statut_dossier</figcaption><pre class="mermaid">{E(ST_DOSSIER)}</pre></figure>
      <figure class="plan"><figcaption>Disponibilité · mecaniciens.etat</figcaption><pre class="mermaid">{E(ST_DISPO)}</pre></figure>
    </div>
    <figure class="plan"><figcaption>Appel intégré · contacts.statut (canal app)</figcaption><pre class="mermaid">{E(ST_APPEL)}</pre></figure>
    <figure class="plan"><figcaption>Déroulé d'un appel intégré</figcaption><pre class="mermaid">{E(SEQ)}</pre></figure></section>

  <section class="s" id="regles"><span class="label">5</span><h2>Règles métier</h2>
    <p class="note">Chaque règle a un code (RG-…) à citer dans le code, les commits et les tests. Les seuils sont dans la table <code>parametres</code>.</p>
    {rules_html()}</section>

  <section class="s" id="dico"><span class="label">6</span><h2>Dictionnaire des tables</h2>
    <div class="filter"><label class="label" for="q">Filtrer</label><input id="q" type="search" placeholder="table, colonne ou mot (ex. position, avis)" autocomplete="off"><span class="label" id="qn"></span></div>
    {''.join(dom_sections)}<p class="empty" id="qe" hidden>Aucune table ne correspond.</p></section>

  <section class="s" id="recherche"><span class="label">7</span><h2>Requête de recherche</h2>
    <p class="note">Règles RG-R2 à RG-R6 et RG-R9 en une requête préparée (PDO). Un pré-filtre en rectangle utilise l'index avant le calcul exact. On l'exécute palier par palier (2, puis 5, puis 10 km). Testée : sur l'exemple, Koffi (spécialiste, 1,36 km) passe devant Adama (1,82 km) ; un mécano au rayon de 2 km situé à 3,2 km et un dossier non validé sont bien écartés. Un mécano de l'autre rive à 1,70 km passe après Adama (1,70 × 1,3 = 2,21).</p>
    <div class="codeblock"><button class="cp" type="button" data-copy="rech">Copier</button><pre id="rech">{E(RECH)}</pre></div></section>

  <section class="s" id="taches"><span class="label">8</span><h2>Tâches planifiées</h2>
    <p class="note">Un seul point d'entrée (<code>php cron.php</code>) lancé chaque minute si Systalink le permet, chaque exécution est écrite dans <code>journal_taches</code>.</p>
    <div class="tbl"><table><thead><tr><th>Fréquence</th><th>Tâche</th><th>Ce qu'elle fait</th></tr></thead><tbody>{taches}</tbody></table></div></section>

  <section class="s" id="conservation"><span class="label">9</span><h2>Conservation des données</h2>
    <p class="note">À reprendre dans la page Confidentialité. Les durées marquées « à valider » demandent un avis juridique.</p>
    <div class="tbl"><table><thead><tr><th>Donnée</th><th>Pourquoi</th><th>Durée</th></tr></thead><tbody>{conserv}</tbody></table></div></section>

  <section class="s" id="parametres"><span class="label">10</span><h2>Paramètres</h2>
    <p class="note">Valeurs de départ insérées par le script. Modifiables sans redéployer.</p>
    <div class="tbl"><table><thead><tr><th>Clé</th><th>Valeur</th><th>Règle</th></tr></thead><tbody>{params}</tbody></table></div></section>

  <section class="s" id="verifier"><span class="label">11</span><h2>À vérifier avant de coder</h2>
    <div class="tbl"><table><tbody>{verif}</tbody></table></div></section>

  <section class="s" id="sql"><span class="label">12</span><h2>Script SQL complet</h2>
    <p class="note">Création des {len(T)} tables, des clés étrangères et des données de départ (communes, véhicules, spécialités, pannes, pièces, étiquettes, paramètres). À lancer dans une base vide en utf8mb4.</p>
    <details><summary>Afficher le script ({SQL.count(chr(10))} lignes)</summary>
    <div class="codeblock"><button class="cp" type="button" data-copy="sqlc">Copier</button><pre id="sqlc">{E(SQL)}</pre></div></details></section>
</main></div>
<script>
(()=>{{
const q=document.getElementById('q'),cards=[...document.querySelectorAll('.tcard')],doms=[...document.querySelectorAll('.dom')],qn=document.getElementById('qn'),qe=document.getElementById('qe');
const run=()=>{{const v=q.value.trim().toLowerCase();let n=0;cards.forEach(c=>{{const ok=!v||c.dataset.q.includes(v);c.hidden=!ok;if(ok)n++}});
 doms.forEach(d=>d.hidden=![...d.querySelectorAll('.tcard')].some(c=>!c.hidden));qn.textContent=v?n+' / {len(T)}':'';qe.hidden=n>0}};
q.addEventListener('input',run);
document.querySelectorAll('.cp').forEach(b=>b.addEventListener('click',()=>{{const el=document.getElementById(b.dataset.copy);
 const done=()=>{{b.textContent='Copié';setTimeout(()=>b.textContent='Copier',1600)}};
 const sel=()=>{{const r=document.createRange();r.selectNodeContents(el);const s=getSelection();s.removeAllRanges();s.addRange(r);b.textContent='Sélectionné'}};
 if(navigator.clipboard)navigator.clipboard.writeText(el.textContent).then(done,sel);else sel()}}));
}})();
</script>
'''
open(os.path.join(D,'..','docs','modele-donnees.html'),'w').write(page)
print('ok', len(page))
