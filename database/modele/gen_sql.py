import os, sys; sys.path.insert(0, os.path.dirname(__file__))
from model import T, LATE_FKS, SEED, DOMAINS, QUARTIERS
def q(s): return "'"+str(s).replace("'","''")+"'"
out=[]
out.append("""-- =====================================================================
-- allomécano · schéma MySQL 8 / MariaDB 10.6+ · InnoDB · utf8mb4
-- Généré depuis model.py : ne pas modifier à la main, modifier le modèle.
-- Toutes les dates sont en UTC (= heure d'Abidjan, GMT toute l'année).
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;
""")
dn=dict((d[0],d[1]) for d in DOMAINS)
for t in T:
    lines=[]
    for c in t['cols']:
        n,ty,nul,de,desc=c
        l=f"  `{n}` {ty}"+("" if nul else " NOT NULL")
        if de is not None: l+=f" DEFAULT {de}" if "ON UPDATE" not in de else f" DEFAULT {de}"
        if desc: l+=f" COMMENT {q(desc)}"
        lines.append(l)
    lines.append("  PRIMARY KEY ("+", ".join(f"`{p}`" for p in t['pk'])+")")
    for nm,cs in t['uniques']: lines.append(f"  UNIQUE KEY `{nm}` ("+", ".join(f"`{x}`" for x in cs)+")")
    for nm,cs in t['indexes']: lines.append(f"  KEY `{nm}` ("+", ".join(f"`{x}`" for x in cs)+")")
    for i,(col,rt,rc,od) in enumerate(t['fks']):
        lines.append(f"  CONSTRAINT `fk_{t['name']}_{col}` FOREIGN KEY (`{col}`) REFERENCES `{rt}` (`{rc}`) ON DELETE {od} ON UPDATE CASCADE")
    for nm,ex in t['checks']: lines.append(f"  CONSTRAINT `{nm}` CHECK ({ex})")
    out.append(f"-- {dn[t['dom']]} · {t['role']}\nDROP TABLE IF EXISTS `{t['name']}`;\nCREATE TABLE `{t['name']}` (\n"+",\n".join(lines)+f"\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT={q(t['role'][:1000])};\n")
for tb,col,rt,rc,od in LATE_FKS:
    out.append(f"ALTER TABLE `{tb}` ADD CONSTRAINT `fk_{tb}_{col}` FOREIGN KEY (`{col}`) REFERENCES `{rt}` (`{rc}`) ON DELETE {od} ON UPDATE CASCADE;\n")
out.append("SET FOREIGN_KEY_CHECKS = 1;\n")
# ---- seed
S=SEED; sd=["-- =====================================================================\n-- Données de départ (référentiels et paramètres)\n-- Coordonnées des communes : centres approximatifs, À VÉRIFIER.\n-- ====================================================================="]
# 6e valeur facultative d'une commune : ouverte (0 = « Bientôt » sur l'accueil ; 1 par défaut)
sd.append("INSERT INTO communes (nom,slug,lat,lon,rive,ouverte,ordre) VALUES\n"+",\n".join(f"({q(n)},{q(s)},{la},{lo},{q(r)},{(o or [1])[0]},{i+1})" for i,(n,s,la,lo,r,*o) in enumerate(S['communes']))+";")
# Quartiers (C2b) : slug = commune-quartier, centres À VÉRIFIER
sd.append("INSERT INTO quartiers (commune_id,nom,slug,alias,lat,lon) VALUES\n"+",\n".join(f"((SELECT id FROM communes WHERE slug={q(c)}),{q(n)},{q(c+'-'+s)},{q(a) if a else 'NULL'},{la:.4f},{lo:.4f})" for c,n,s,a,la,lo in QUARTIERS)+";")
sd.append("INSERT INTO types_vehicule (code,libelle,ordre) VALUES\n"+",\n".join(f"({q(c)},{q(l)},{o})" for c,l,o in S['types_vehicule'])+";")
sd.append("INSERT INTO specialites (code,libelle,ordre) VALUES\n"+",\n".join(f"({q(c)},{q(l)},{i+1})" for i,(c,l) in enumerate(S['specialites']))+";")
sd.append("INSERT INTO pannes (code,libelle,picto,est_danger,ordre) VALUES\n"+",\n".join(f"({q(c)},{q(l)},{q(p)},{d},{i+1})" for i,(c,l,p,d) in enumerate(S['pannes']))+";")
rows=[]
for p,ss in S['panne_specialite'].items():
    for s_ in ss: rows.append(f"((SELECT id FROM pannes WHERE code={q(p)}),(SELECT id FROM specialites WHERE code={q(s_)}))")
sd.append("INSERT INTO panne_specialite (panne_id,specialite_id) VALUES\n"+",\n".join(rows)+";")
sd.append("INSERT INTO types_piece (code,libelle,obligatoire,ordre) VALUES\n"+",\n".join(f"({q(c)},{q(l)},{o},{r})" for c,l,o,r in S['types_piece'])+";")
sd.append("INSERT INTO etiquettes_avis (code,libelle,polarite,ordre) VALUES\n"+",\n".join(f"({q(c)},{q(l)},{q(p)},{i+1})" for i,(c,l,p) in enumerate(S['etiquettes_avis']))+";")
sd.append("INSERT INTO parametres (cle,valeur,type,description) VALUES\n"+",\n".join(f"({q(c)},{q(v)},{q(t)},{q(d)})" for c,v,t,d in S['parametres'])+";")
open(os.path.join(os.path.dirname(__file__),'..','schema.sql'),'w').write("\n".join(out)+"\n"+"\n\n".join(sd)+"\n")
print(len(T),"tables")
