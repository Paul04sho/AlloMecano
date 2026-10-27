-- Données de DÉMONSTRATION (jamais en production) : 1 admin, 8 mécanos fictifs disponibles.
-- Chargées par : php scripts/installer-base.php --demo
-- Mot de passe de tous les comptes de démo : Demo2026!
-- Numéros fictifs (+225 07 00 00 00 01 à 08) : avant une démo devant le jury, mettre les numéros de l'équipe
-- pour que « Appeler » et WhatsApp sonnent chez nous et jamais chez un inconnu.

-- Les quartiers viennent des données de départ (schema.sql) : on s'appuie sur leurs slugs.

-- password_hash('Demo2026!', PASSWORD_DEFAULT)
SET @mdp = '$2y$10$KVlxifPk5tt/5uHd2Q9XlONhWClsok4m7kZzGQS0Ulmfe40TS6TNu';

INSERT INTO admins (prenom,nom,email,mot_de_passe_hash) VALUES ('Aïcha','Bamba','admin@allomecano.test',@mdp);

INSERT INTO mecaniciens (public_id,slug,prenom,nom,telephone,metier,annees_experience,presentation,atelier_adresse,atelier_quartier_id,atelier_lat,atelier_lon,rayon_km,tarif_diagnostic_fcfa,tarif_deplacement_fcfa,statut_dossier,identite_verifiee_le,mot_de_passe_hash,cgu_version,cgu_acceptees_le,etat,etat_depuis,dispo_debut,dispo_lat,dispo_lon,dispo_precision_m,dispo_quartier_id,position_maj_le,vu_le,note_moyenne,nb_avis,taux_joignabilite,appels_manques_suite,cree_le) VALUES
('01JDEMO0000000000000000001','koffi-yao-riviera-3','Koffi','Yao','+2250700000001','Électricien auto',12,'Batterie, démarreur, alternateur. Je viens avec mon booster.','Derrière le marché de Riviera 3',(SELECT id FROM quartiers WHERE slug='cocody-riviera-3'),5.3652,-3.9478,10,5000,3000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3655,-3.9490,15,(SELECT id FROM quartiers WHERE slug='cocody-riviera-3'),NOW(),NOW(),4.8,37,96,0,NOW()),
('01JDEMO0000000000000000002','awa-kone-riviera-2','Awa','Koné','+2250700000002','Mécanicienne générale',8,'Diagnostic rapide, vidange, freins.','Carrefour Riviera 2',(SELECT id FROM quartiers WHERE slug='cocody-riviera-2'),5.3601,-3.9602,5,3000,2000,'valide','2026-04-02',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3595,-3.9610,20,(SELECT id FROM quartiers WHERE slug='cocody-riviera-2'),NOW(),NOW(),4.6,21,91,0,NOW()),
('01JDEMO0000000000000000003','seydou-traore-angre','Seydou','Traoré','+2250700000003','Vulcanisateur',15,'Pneus, chambres à air, équilibrage. Voitures et motos.','Station Angré 8e tranche',(SELECT id FROM quartiers WHERE slug='cocody-angre-8e-tranche'),5.3951,-3.9902,10,2000,2000,'valide','2026-02-20',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3940,-3.9880,25,(SELECT id FROM quartiers WHERE slug='cocody-angre-8e-tranche'),NOW(),NOW(),4.9,58,98,0,NOW()),
('01JDEMO0000000000000000004','jean-marc-ouattara-deux-plateaux','Jean-Marc','Ouattara','+2250700000004','Motoriste',20,'Moteur, surchauffe, joint de culasse.','Rue des Jardins, Deux-Plateaux',(SELECT id FROM quartiers WHERE slug='cocody-deux-plateaux-vallon'),5.3702,-4.0003,20,7000,5000,'valide','2026-01-15',@mdp,'1.0',NOW(),'en_intervention',NOW(),NOW(),5.3710,-3.9990,30,(SELECT id FROM quartiers WHERE slug='cocody-deux-plateaux-vallon'),NOW(),NOW(),4.7,44,88,0,NOW()),
('01JDEMO0000000000000000005','ibrahim-diallo-zone-4','Ibrahim','Diallo','+2250700000005','Dépanneur',10,'Remorquage plateau, accidents, carrosserie.','Boulevard VGE, Zone 4',(SELECT id FROM quartiers WHERE slug='marcory-zone-4'),5.2902,-3.9752,20,10000,15000,'valide','2026-05-08',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2905,-3.9760,20,(SELECT id FROM quartiers WHERE slug='marcory-zone-4'),NOW(),NOW(),4.5,19,93,0,NOW()),
('01JDEMO0000000000000000006','moussa-bakayoko-niangon','Moussa','Bakayoko','+2250700000006','Mécanicien moto',6,'Motos et tricycles, toutes marques.','Niangon, près de la pharmacie',(SELECT id FROM quartiers WHERE slug='yopougon-niangon'),5.3352,-4.1052,10,2000,1500,'valide','2026-06-30',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3360,-4.1040,20,(SELECT id FROM quartiers WHERE slug='yopougon-niangon'),NOW(),NOW(),4.4,12,85,0,NOW()),
('01JDEMO0000000000000000007','esther-n-guessan-plateau','Esther','N''Guessan','+2250700000007','Électricienne auto',9,'Électronique, valise de diagnostic, clim.','Avenue Chardy',(SELECT id FROM quartiers WHERE slug='plateau-centre'),5.3231,-4.0191,5,6000,3000,'valide','2026-03-27',@mdp,'1.0',NOW(),'indisponible',NOW(),NULL,NULL,NULL,NULL,NULL,NULL,NOW(),4.9,31,97,0,NOW()),
('01JDEMO0000000000000000008','ange-kouassi-arras','Ange','Kouassi','+2250700000008','Mécanicien poids lourds',18,'Camions, bus, freins à air.','Rue 12, Arras',(SELECT id FROM quartiers WHERE slug='treichville-arras'),5.2951,-4.0051,20,8000,8000,'valide','2026-02-03',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2945,-4.0060,25,(SELECT id FROM quartiers WHERE slug='treichville-arras'),NOW(),NOW(),4.6,26,90,0,NOW()),
-- ABOBO (3)
('01JDEMO0000000000000000101','moussa-diallo-abobo','Moussa','Diallo','+2250700000101','Mécanicien',10,'Démo','Abobo',(SELECT id FROM quartiers WHERE slug='abobo-gare'),5.4160,-4.0180,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.4190,-4.0200,15,(SELECT id FROM quartiers WHERE slug='abobo-gare'),NOW(),NOW(),4.5,12,90,0,NOW()),
('01JDEMO0000000000000000102','ibrahim-sidibe-abobo','Ibrahim','Sidibé','+2250700000102','Électricien',5,'Démo','Abobo',(SELECT id FROM quartiers WHERE slug='abobo-pk-18'),5.4560,-4.0450,20,4000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.4190,-4.0200,15,(SELECT id FROM quartiers WHERE slug='abobo-pk-18'),NOW(),NOW(),4.2,5,90,0,NOW()),
('01JDEMO0000000000000000103','abdoulaye-soro-abobo','Abdoulaye','Soro','+2250700000103','Vulcanisateur',7,'Démo','Abobo',(SELECT id FROM quartiers WHERE slug='abobo-avocatier'),5.4300,-4.0350,20,2000,1000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.4190,-4.0200,15,(SELECT id FROM quartiers WHERE slug='abobo-avocatier'),NOW(),NOW(),4.7,19,90,0,NOW()),
-- ADJAMÉ (4)
('01JDEMO0000000000000000104','bakary-kone-adjame','Bakary','Koné','+2250700000104','Mécanicien',8,'Démo','Adjamé',(SELECT id FROM quartiers WHERE slug='adjame-liberte'),5.3580,-4.0180,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3600,-4.0230,15,(SELECT id FROM quartiers WHERE slug='adjame-liberte'),NOW(),NOW(),4.4,8,90,0,NOW()),
('01JDEMO0000000000000000105','salif-coulibaly-adjame','Salif','Coulibaly','+2250700000105','Dieseliste',6,'Démo','Adjamé',(SELECT id FROM quartiers WHERE slug='adjame-williamsville'),5.3720,-4.0220,20,5000,3000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3600,-4.0230,15,(SELECT id FROM quartiers WHERE slug='adjame-williamsville'),NOW(),NOW(),4.6,14,90,0,NOW()),
('01JDEMO0000000000000000106','ousmane-traore-adjame','Ousmane','Traoré','+2250700000106','Électricien',4,'Démo','Adjamé',(SELECT id FROM quartiers WHERE slug='adjame-bracodi'),5.3530,-4.0230,20,4000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3600,-4.0230,15,(SELECT id FROM quartiers WHERE slug='adjame-bracodi'),NOW(),NOW(),4.1,3,90,0,NOW()),
('01JDEMO0000000000000000107','lamine-cisse-adjame','Lamine','Cissé','+2250700000107','Mécanicien',12,'Démo','Adjamé',(SELECT id FROM quartiers WHERE slug='adjame-220-logements'),5.3650,-4.0130,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3600,-4.0230,15,(SELECT id FROM quartiers WHERE slug='adjame-220-logements'),NOW(),NOW(),4.8,22,90,0,NOW()),
-- ANYAMA (2)
('01JDEMO0000000000000000108','sekou-keita-anyama','Sékou','Keita','+2250700000108','Mécanicien',9,'Démo','Anyama',(SELECT id FROM quartiers WHERE slug='anyama-centre'),5.4950,-4.0520,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.4950,-4.0520,15,(SELECT id FROM quartiers WHERE slug='anyama-centre'),NOW(),NOW(),4.3,6,90,0,NOW()),
('01JDEMO0000000000000000109','amadou-toure-anyama','Amadou','Touré','+2250700000109','Électricien',11,'Démo','Anyama',(SELECT id FROM quartiers WHERE slug='anyama-ebimpe'),5.4700,-4.0900,20,5000,3000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.4950,-4.0520,15,(SELECT id FROM quartiers WHERE slug='anyama-ebimpe'),NOW(),NOW(),4.5,11,90,0,NOW()),
-- BINGERVILLE (6)
('01JDEMO0000000000000000110','charles-bamba-bingerville','Charles','Bamba','+2250700000110','Mécanicien',15,'Démo','Bingerville',(SELECT id FROM quartiers WHERE slug='bingerville-centre'),5.3550,-3.8850,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3550,-3.8850,15,(SELECT id FROM quartiers WHERE slug='bingerville-centre'),NOW(),NOW(),4.6,25,90,0,NOW()),
('01JDEMO0000000000000000111','koffi-kouadio-bingerville','Koffi','Kouadio','+2250700000111','Vulcanisateur',9,'Démo','Bingerville',(SELECT id FROM quartiers WHERE slug='bingerville-akandje'),5.3800,-3.9000,20,2000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3550,-3.8850,15,(SELECT id FROM quartiers WHERE slug='bingerville-akandje'),NOW(),NOW(),4.4,9,90,0,NOW()),
('01JDEMO0000000000000000112','gnamien-aka-bingerville','Gnamien','Aka','+2250700000112','Électricien',4,'Démo','Bingerville',(SELECT id FROM quartiers WHERE slug='bingerville-gbagba'),5.3460,-3.8730,20,4000,3000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3550,-3.8850,15,(SELECT id FROM quartiers WHERE slug='bingerville-gbagba'),NOW(),NOW(),4.2,4,90,0,NOW()),
('01JDEMO0000000000000000113','didier-dago-bingerville','Didier','Dago','+2250700000113','Mécanicien',13,'Démo','Bingerville',(SELECT id FROM quartiers WHERE slug='bingerville-abatta'),5.3700,-3.9300,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3550,-3.8850,15,(SELECT id FROM quartiers WHERE slug='bingerville-abatta'),NOW(),NOW(),4.7,13,90,0,NOW()),
('01JDEMO0000000000000000114','arnaud-ngoran-bingerville','Arnaud','N''Goran','+2250700000114','Motoriste',21,'Démo','Bingerville',(SELECT id FROM quartiers WHERE slug='bingerville-feh-kesse'),5.3620,-3.8700,20,6000,4000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3550,-3.8850,15,(SELECT id FROM quartiers WHERE slug='bingerville-feh-kesse'),NOW(),NOW(),4.9,31,90,0,NOW()),
('01JDEMO0000000000000000115','marc-kouassi-bingerville','Marc','Kouassi','+2250700000115','Mécanicien',7,'Démo','Bingerville',(SELECT id FROM quartiers WHERE slug='bingerville-santai'),5.3450,-3.9050,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.3550,-3.8850,15,(SELECT id FROM quartiers WHERE slug='bingerville-santai'),NOW(),NOW(),4.3,7,90,0,NOW()),
-- KOUMASSI (1)
('01JDEMO0000000000000000116','seydou-diomande-koumassi','Seydou','Diomandé','+2250700000116','Mécanicien',16,'Démo','Koumassi',(SELECT id FROM quartiers WHERE slug='koumassi-centre'),5.2970,-3.9500,20,3000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2970,-3.9500,15,(SELECT id FROM quartiers WHERE slug='koumassi-centre'),NOW(),NOW(),4.5,16,90,0,NOW()),
-- PORT-BOUËT (2)
('01JDEMO0000000000000000117','pacome-agnero-port-bouet','Pacôme','Agnéro','+2250700000117','Mécanicien',20,'Démo','Port-Bouët',(SELECT id FROM quartiers WHERE slug='port-bouet-centre'),5.2550,-3.9400,20,4000,3000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2560,-3.9300,15,(SELECT id FROM quartiers WHERE slug='port-bouet-centre'),NOW(),NOW(),4.6,20,90,0,NOW()),
('01JDEMO0000000000000000118','zana-kone-port-bouet','Zana','Kone','+2250700000118','Électricien',12,'Démo','Port-Bouët',(SELECT id FROM quartiers WHERE slug='port-bouet-gonzagueville'),5.2450,-3.8750,20,4000,2000,'valide','2026-03-12',@mdp,'1.0',NOW(),'disponible',NOW(),NOW(),5.2560,-3.9300,15,(SELECT id FROM quartiers WHERE slug='port-bouet-gonzagueville'),NOW(),NOW(),4.4,12,90,0,NOW());

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
