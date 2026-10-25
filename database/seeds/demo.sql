-- Données de DÉMONSTRATION (jamais en production) : 1 admin, 8 mécanos fictifs disponibles.
-- Chargées par : php scripts/installer-base.php --demo
-- Mot de passe de tous les comptes de démo : Demo2026!
-- Numéros fictifs (+225 07 00 00 00 01 à 08) : avant une démo devant le jury, mettre les numéros de l'équipe
-- pour que « Appeler » et WhatsApp sonnent chez nous et jamais chez un inconnu.

-- Les quartiers viennent des données de départ (schema.sql) : on s'appuie sur leurs slugs.

-- password_hash('Demo2026!', PASSWORD_DEFAULT)
SET @mdp = '$2y$10$KVlxifPk5tt/5uHd2Q9XlONhWClsok4m7kZzGQS0Ulmfe40TS6TNu';

INSERT INTO admins (prenom,nom,email,mot_de_passe_hash) VALUES ('Aïcha','Bamba','admin@allomecano.test',@mdp);

INSERT INTO mecaniciens (public_id,slug,prenom,nom,telephone,metier,annees_experience,presentation,atelier_adresse,atelier_quartier_id,atelier_lat,atelier_lon,rayon_km,tarif_diagnostic_fcfa,tarif_deplacement_fcfa,statut_dossier,identite_verifiee_le,mot_de_passe_hash,cgu_version,cgu_acceptees_le,etat,etat_depuis,dispo_debut,dispo_lat,dispo_lon,dispo_precision_m,dispo_quartier_id,position_maj_le,vu_le,note_moyenne,nb_avis,taux_joignabilite) VALUES
('01JDEMO0000000000000000001','koffi-yao-riviera-3','Koffi','Yao','+2250700000001','Électricien auto',12,'Batterie, démarreur, alternateur. Je viens avec mon booster.','Derrière le marché de Riviera 3',(SELECT id FROM quartiers WHERE slug='cocody-riviera-3'),5.3652,-3.9478,10,5000,3000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3655,-3.9490,15,(SELECT id FROM quartiers WHERE slug='cocody-riviera-3'),NOW(),NOW(),4.8,37,96),
('01JDEMO0000000000000000002','awa-kone-riviera-2','Awa','Koné','+2250700000002','Mécanicienne générale',8,'Diagnostic rapide, vidange, freins.','Carrefour Riviera 2',(SELECT id FROM quartiers WHERE slug='cocody-riviera-2'),5.3601,-3.9602,5,3000,2000,'valide','2026-04-02',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3595,-3.9610,20,(SELECT id FROM quartiers WHERE slug='cocody-riviera-2'),NOW(),NOW(),4.6,21,91),
('01JDEMO0000000000000000003','seydou-traore-angre','Seydou','Traoré','+2250700000003','Vulcanisateur',15,'Pneus, chambres à air, équilibrage. Voitures et motos.','Station Angré 8e tranche',(SELECT id FROM quartiers WHERE slug='cocody-angre-8e-tranche'),5.3951,-3.9902,10,2000,2000,'valide','2026-02-20',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3940,-3.9880,25,(SELECT id FROM quartiers WHERE slug='cocody-angre-8e-tranche'),NOW(),NOW(),4.9,58,98),
('01JDEMO0000000000000000004','jean-marc-ouattara-deux-plateaux','Jean-Marc','Ouattara','+2250700000004','Motoriste',20,'Moteur, surchauffe, joint de culasse.','Rue des Jardins, Deux-Plateaux',(SELECT id FROM quartiers WHERE slug='cocody-deux-plateaux-vallon'),5.3702,-4.0003,20,7000,5000,'valide','2026-01-15',@mdp,'1.0',NOW(),'en_intervention',NOW(),NOW(),5.3710,-3.9990,30,(SELECT id FROM quartiers WHERE slug='cocody-deux-plateaux-vallon'),NOW(),NOW(),4.7,44,88),
('01JDEMO0000000000000000005','ibrahim-diallo-zone-4','Ibrahim','Diallo','+2250700000005','Dépanneur',10,'Remorquage plateau, accidents, carrosserie.','Boulevard VGE, Zone 4',(SELECT id FROM quartiers WHERE slug='marcory-zone-4'),5.2902,-3.9752,20,10000,15000,'valide','2026-05-08',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2905,-3.9760,20,(SELECT id FROM quartiers WHERE slug='marcory-zone-4'),NOW(),NOW(),4.5,19,93),
('01JDEMO0000000000000000006','moussa-bakayoko-niangon','Moussa','Bakayoko','+2250700000006','Mécanicien moto',6,'Motos et tricycles, toutes marques.','Niangon, près de la pharmacie',(SELECT id FROM quartiers WHERE slug='yopougon-niangon'),5.3352,-4.1052,10,2000,1500,'valide','2026-06-30',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3360,-4.1040,20,(SELECT id FROM quartiers WHERE slug='yopougon-niangon'),NOW(),NOW(),4.4,12,85),
('01JDEMO0000000000000000007','esther-n-guessan-plateau','Esther','N''Guessan','+2250700000007','Électricienne auto',9,'Électronique, valise de diagnostic, clim.','Avenue Chardy',(SELECT id FROM quartiers WHERE slug='plateau-centre'),5.3231,-4.0191,5,6000,3000,'valide','2026-03-27',@mdp,'1.0',NOW(),'indisponible',NOW(),NULL,NULL,NULL,NULL,NULL,NULL,NOW(),4.9,31,97),
('01JDEMO0000000000000000008','ange-kouassi-arras','Ange','Kouassi','+2250700000008','Mécanicien poids lourds',18,'Camions, bus, freins à air.','Rue 12, Arras',(SELECT id FROM quartiers WHERE slug='treichville-arras'),5.2951,-4.0051,20,8000,8000,'valide','2026-02-03',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2945,-4.0060,25,(SELECT id FROM quartiers WHERE slug='treichville-arras'),NOW(),NOW(),4.6,26,90);

INSERT INTO mecanicien_vehicules (mecanicien_id,type_vehicule)
SELECT m.id, v.code FROM mecaniciens m JOIN types_vehicule v ON
  (m.public_id IN ('01JDEMO0000000000000000001','01JDEMO0000000000000000002','01JDEMO0000000000000000004','01JDEMO0000000000000000005','01JDEMO0000000000000000007') AND v.code='voiture')
  OR (m.public_id='01JDEMO0000000000000000003' AND v.code IN ('voiture','moto'))
  OR (m.public_id='01JDEMO0000000000000000006' AND v.code='moto')
  OR (m.public_id='01JDEMO0000000000000000008' AND v.code IN ('camion','voiture'))
  OR (m.public_id='01JDEMO0000000000000000005' AND v.code='camion');

INSERT INTO mecanicien_specialites (mecanicien_id,specialite_id)
SELECT m.id, s.id FROM mecaniciens m JOIN specialites s ON
  (m.public_id='01JDEMO0000000000000000001' AND s.code IN ('electricite_auto','batterie','demarreur_alternateur'))
  OR (m.public_id='01JDEMO0000000000000000002' AND s.code IN ('mecanique_generale','freins'))
  OR (m.public_id='01JDEMO0000000000000000003' AND s.code IN ('pneus'))
  OR (m.public_id='01JDEMO0000000000000000004' AND s.code IN ('moteur','refroidissement','mecanique_generale'))
  OR (m.public_id='01JDEMO0000000000000000005' AND s.code IN ('remorquage','carrosserie'))
  OR (m.public_id='01JDEMO0000000000000000006' AND s.code IN ('moto'))
  OR (m.public_id='01JDEMO0000000000000000007' AND s.code IN ('electricite_auto','batterie'))
  OR (m.public_id='01JDEMO0000000000000000008' AND s.code IN ('freins','moteur','mecanique_generale'));

-- Portraits de démo (photos publiques, RG-DO9) : visages des maquettes, copiés dans public/medias/demo
-- par scripts/installer-base.php --demo. En production, ils sont générés depuis la pièce validée.
INSERT INTO photos (mecanicien_id,type,dossier,largeurs,ratio,couleur_dominante,focal_x,focal_y)
SELECT m.id,'portrait',CONCAT('demo/',p.fichier),IF(p.fichier='doyen','160,360,656','160,360,604'),p.ratio,p.couleur,0.50,0.30
FROM mecaniciens m JOIN (
  SELECT '01JDEMO0000000000000000001' AS public_id,'koffi' AS fichier,0.798 AS ratio,'#6f665b' AS couleur UNION ALL
  SELECT '01JDEMO0000000000000000002','awa',0.798,'#786e64' UNION ALL
  SELECT '01JDEMO0000000000000000003','adama',0.798,'#8a7969' UNION ALL
  SELECT '01JDEMO0000000000000000004','serge',0.798,'#8e8378' UNION ALL
  SELECT '01JDEMO0000000000000000005','doyen',0.928,'#93877d' UNION ALL
  SELECT '01JDEMO0000000000000000006','adama',0.798,'#8a7969' UNION ALL
  SELECT '01JDEMO0000000000000000007','awa',0.798,'#786e64' UNION ALL
  SELECT '01JDEMO0000000000000000008','serge',0.798,'#8e8378'
) p ON p.public_id = m.public_id;

-- Photo d'atelier (fiche C5 · « Son atelier ») : substitut IMG-01 en attendant IMG-10, comme dans la maquette.
INSERT INTO photos (mecanicien_id,type,dossier,largeurs,ratio,couleur_dominante,focal_x,focal_y)
SELECT id,'atelier','demo/atelier','780,1264',1.491,'#8f7f6c',0.50,0.50 FROM mecaniciens;

-- Avis de démonstration (RG-AV1 à AV6) : autant d'avis que mecaniciens.nb_avis, répartis pour retrouver note_moyenne
-- (sur 10 avis : round((note-4)×10) à 5 étoiles, les autres à 4). Chaque avis repose sur un contact terminé.
INSERT INTO clients (jeton_hash,prenom) VALUES (SHA2('client-de-demonstration',256),'Démo');

INSERT INTO contacts (public_id,client_id,mecanicien_id,canal,statut,cree_le,resume,quartier_libelle,position_partagee)
SELECT CONCAT('01JDEMOC',LPAD(m.id,3,'0'),LPAD(n.k,15,'0')),
       (SELECT id FROM clients WHERE jeton_hash=SHA2('client-de-demonstration',256)),
       m.id,'tel','termine',
       UTC_TIMESTAMP() - INTERVAL IF(n.k=1,3,IF(n.k=2,14,n.k*5+10)) DAY,
       'Voiture · Batterie','Riviera 2',1
FROM mecaniciens m
JOIN (SELECT a.d+10*b.d+1 AS k FROM
        (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
        (SELECT 0 d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b) n
  ON n.k <= m.nb_avis;

INSERT INTO avis (contact_id,mecanicien_id,client_id,note,commentaire,prenom_affiche,panne_libelle,reponse_mecano,reponse_le,cree_le)
SELECT c.id, c.mecanicien_id, c.client_id,
       IF(MOD(k.k-1,10) < ROUND((m.note_moyenne-4)*10),5,4),
       CASE WHEN m.id=1 AND k.k=1 THEN 'Arrivé en 20 minutes, batterie changée sur place. Il a annoncé le prix avant de commencer.'
            WHEN m.id=1 AND k.k=2 THEN 'Démarreur bloqué devant chez moi. Il a trouvé le relais grillé en dix minutes et m’a montré la pièce.'
            ELSE ELT(1+MOD(k.k,6),
              'Il est venu vite et il a bien expliqué la panne avant de toucher à quoi que ce soit.',
              'Prix annoncé au départ, prix payé à la fin. Je recommande.',
              'Travail propre, il a gardé les pièces changées pour me les montrer.',
              'Dépannée en moins d’une demi-heure, très poli.',
              'Il a rappelé le lendemain pour vérifier que tout marchait.',
              'Sérieux, ponctuel, et il connaît bien son métier.') END,
       IF(m.id=1 AND k.k=1,'Aminata',IF(m.id=1 AND k.k=2,'Bintou',ELT(1+MOD(k.k+m.id,8),'Yao','Fatou','Kader','Mariam','Serge','Aïcha','Ibrahim',NULL))),
       CASE m.id WHEN 1 THEN IF(k.k=1,'Batterie',IF(k.k=2,'Démarreur',ELT(1+MOD(k.k,3),'Batterie','Démarreur','Alternateur')))
                 WHEN 2 THEN ELT(1+MOD(k.k,3),'Freins','Vidange','Diagnostic')
                 WHEN 3 THEN ELT(1+MOD(k.k,3),'Pneu crevé','Équilibrage','Pneu crevé')
                 WHEN 4 THEN ELT(1+MOD(k.k,3),'Surchauffe','Moteur','Joint de culasse')
                 WHEN 5 THEN ELT(1+MOD(k.k,3),'Remorquage','Accident','Carrosserie')
                 WHEN 6 THEN ELT(1+MOD(k.k,3),'Moto','Chaîne','Embrayage')
                 WHEN 7 THEN ELT(1+MOD(k.k,3),'Batterie','Électronique','Climatisation')
                 ELSE ELT(1+MOD(k.k,3),'Freins à air','Camion','Moteur') END,
       IF(m.id=1 AND k.k=1,'Merci Aminata, bonne route !',NULL),
       IF(m.id=1 AND k.k=1,c.cree_le + INTERVAL 2 HOUR,NULL),
       c.cree_le + INTERVAL 1 HOUR
FROM contacts c
JOIN mecaniciens m ON m.id=c.mecanicien_id
JOIN (SELECT CAST(SUBSTRING(public_id,12) AS UNSIGNED) AS k, id FROM contacts WHERE public_id LIKE '01JDEMOC%') k ON k.id=c.id
WHERE c.public_id LIKE '01JDEMOC%';

INSERT INTO avis_etiquettes (avis_id,etiquette_id)
SELECT a.id, 1+MOD(a.id,4) FROM avis a WHERE a.note=5 AND MOD(a.id,2)=0;

-- Les mécanos de démo ont déjà activé leurs appels (M1f) : on arrive directement sur le tableau de bord.
UPDATE mecaniciens SET appels_actives_le = NOW() WHERE statut_dossier = 'valide';
