# Définition unique du modèle allomécano : sert à générer schema.sql ET la documentation.
# Colonne : (nom, type SQL, nullable, défaut SQL ou None, description)
# FK : (colonne, table, colonne_ref, ON DELETE)

ENUM = lambda *v: "ENUM(" + ",".join(f"'{x}'" for x in v) + ")"
DT = "DATETIME"
B = "TINYINT(1)"

DOMAINS = [
 ("ref", "Référentiels", "Listes stables, modifiables par l'équipe sans toucher au code."),
 ("meca", "Mécaniciens et dossier", "Le compte du mécano, son dossier de vérification et sa disponibilité."),
 ("client", "Clients et recherches", "Le client anonyme, ses recherches et les résultats proposés."),
 ("contact", "Mises en relation et appels", "Chaque tentative de contact, quel que soit le canal, et la signalisation de l'appel intégré."),
 ("avis", "Avis et signalements", "La confiance : ce que disent les clients et ce qu'ils signalent."),
 ("sys", "Administration et système", "Admins, connexions, notifications, journal et tâches planifiées."),
]

T = []
def table(name, dom, role, cols, pk, fks=(), uniques=(), indexes=(), checks=(), version="V1", note=None):
    T.append(dict(name=name, dom=dom, role=role, cols=cols, pk=pk, fks=list(fks), uniques=list(uniques),
                  indexes=list(indexes), checks=list(checks), version=version, note=note))

# ------------------------------------------------------------------ Référentiels
table("communes","ref","Les 13 communes du District d'Abidjan.",[
 ("id","TINYINT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("nom","VARCHAR(60)",False,None,"Nom affiché (« Cocody »)"),
 ("slug","VARCHAR(60)",False,None,"Pour les URL (« cocody »)"),
 ("lat","DECIMAL(9,6)",False,None,"Centre approximatif"),
 ("lon","DECIMAL(9,6)",False,None,"Centre approximatif"),
 ("rive",ENUM("nord","sud"),False,None,"Côté de la lagune Ébrié (état « Autre rive » des résultats)"),
 ("ouverte",B,False,"1","0 = « Bientôt » dans la liste des communes"),
 ("ordre","TINYINT UNSIGNED",False,"0","Ordre d'affichage"),
],["id"],uniques=[("uq_communes_nom",["nom"]),("uq_communes_slug",["slug"])])

table("quartiers","ref","Quartiers par commune, pour la recherche sans GPS. Liste à constituer depuis OpenStreetMap puis relue.",[
 ("id","SMALLINT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("commune_id","TINYINT UNSIGNED",False,None,"Commune"),
 ("nom","VARCHAR(80)",False,None,"« Riviera 2 »"),
 ("slug","VARCHAR(100)",False,None,"« cocody-riviera-2 »"),
 ("alias","VARCHAR(255)",True,None,"Autres noms pour l'autocomplétion (« Riv 2, Riviera II »)"),
 ("lat","DECIMAL(9,6)",False,None,"Centre du quartier"),
 ("lon","DECIMAL(9,6)",False,None,"Centre du quartier"),
 ("actif",B,False,"1","Proposé dans la recherche"),
],["id"],fks=[("commune_id","communes","id","RESTRICT")],
 uniques=[("uq_quartiers_slug",["slug"]),("uq_quartiers_commune_nom",["commune_id","nom"])],indexes=[("idx_quartiers_nom",["nom"])])

table("types_vehicule","ref","Voiture, moto, camion. Filtre strict de la recherche.",[
 ("code","VARCHAR(10)",False,None,"« voiture », « moto », « camion »"),
 ("libelle","VARCHAR(30)",False,None,"Libellé affiché"),
 ("ordre","TINYINT UNSIGNED",False,"0","Ordre d'affichage"),
],["code"])

table("specialites","ref","Ce que le mécano sait réparer (étiquettes de la fiche).",[
 ("id","SMALLINT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("code","VARCHAR(40)",False,None,"« electricite_auto »"),
 ("libelle","VARCHAR(60)",False,None,"« Électricité auto »"),
 ("ordre","TINYINT UNSIGNED",False,"0","Ordre d'affichage"),
 ("actif",B,False,"1","Proposée à l'inscription"),
],["id"],uniques=[("uq_specialites_code",["code"])])

table("pannes","ref","Les tuiles « Qu'est-ce qui s'allume ? » de l'écran C3.",[
 ("id","SMALLINT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("code","VARCHAR(40)",False,None,"« batterie »"),
 ("libelle","VARCHAR(60)",False,None,"« Batterie »"),
 ("picto","VARCHAR(30)",False,None,"Nom du picto voyant de bord"),
 ("est_danger",B,False,"0","Affiche le rappel « Appelez le 185 d'abord » (choc ou accident)"),
 ("ordre","TINYINT UNSIGNED",False,"0","Ordre d'affichage"),
 ("actif",B,False,"1","Proposée au client"),
],["id"],uniques=[("uq_pannes_code",["code"])])

table("panne_specialite","ref","Quelles spécialités traitent quelle panne. Sert à faire remonter les spécialistes (la panne ne filtre pas).",[
 ("panne_id","SMALLINT UNSIGNED",False,None,"Panne"),
 ("specialite_id","SMALLINT UNSIGNED",False,None,"Spécialité qui la traite"),
],["panne_id","specialite_id"],fks=[("panne_id","pannes","id","CASCADE"),("specialite_id","specialites","id","CASCADE")])

table("types_piece","ref","Les pièces du dossier de vérification.",[
 ("code","VARCHAR(20)",False,None,"« cni_recto », « cni_verso », « permis », « diplome », « portrait », « atelier »"),
 ("libelle","VARCHAR(60)",False,None,"Libellé affiché"),
 ("obligatoire",B,False,"1","Bloque l'envoi du dossier si absente"),
 ("ordre","TINYINT UNSIGNED",False,"0","Ordre (sens des facettes de l'écrou)"),
],["code"])

table("etiquettes_avis","ref","Étiquettes rapides proposées avec la note.",[
 ("id","TINYINT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("code","VARCHAR(30)",False,None,"« rapide », « prix_respecte »…"),
 ("libelle","VARCHAR(50)",False,None,"Libellé affiché"),
 ("polarite",ENUM("positive","negative"),False,None,"Proposée selon la note donnée"),
 ("ordre","TINYINT UNSIGNED",False,"0","Ordre d'affichage"),
],["id"],uniques=[("uq_etiquettes_code",["code"])])

table("parametres","ref","Tous les seuils des règles métier (rayons, délais, durées de conservation). Modifiables sans redéployer.",[
 ("cle","VARCHAR(60)",False,None,"« appel.sonnerie_s »"),
 ("valeur","VARCHAR(255)",False,None,"Valeur (texte)"),
 ("type",ENUM("int","decimal","texte","liste"),False,"'int'","Pour la conversion côté PHP"),
 ("description","VARCHAR(255)",False,None,"Règle métier concernée"),
 ("modifie_le",DT,False,"CURRENT_TIMESTAMP","Dernière modification"),
],["cle"])

# ------------------------------------------------------------------ Système (admins d'abord : référencés partout)
table("admins","sys","Comptes de l'équipe allomécano. Un seul rôle en V1 ; chaque action est journalisée.",[
 ("id","SMALLINT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("prenom","VARCHAR(60)",False,None,"Prénom (sert au filigrane « A. Bamba »)"),
 ("nom","VARCHAR(60)",False,None,"Nom"),
 ("email","VARCHAR(190)",False,None,"Identifiant de connexion"),
 ("mot_de_passe_hash","VARCHAR(255)",True,None,"password_hash() ; vide si connexion Google seulement"),
 ("google_sub","VARCHAR(64)",True,None,"Identifiant Google (connexion Google)"),
 ("actif",B,False,"1","Compte utilisable"),
 ("derniere_connexion_le",DT,True,None,"Dernière connexion réussie"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP","Création"),
 ("modifie_le",DT,False,"CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP","Dernière modification"),
],["id"],uniques=[("uq_admins_email",["email"]),("uq_admins_google",["google_sub"])])

# ------------------------------------------------------------------ Mécaniciens
table("mecaniciens","meca","Le mécanicien : compte, profil public, statut du dossier et disponibilité actuelle (colonnes dispo_*), plus quelques valeurs calculées pour aller vite à la recherche.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant interne"),
 ("public_id","CHAR(26)",False,None,"ULID utilisé dans les URL et l'API (jamais l'id)"),
 ("slug","VARCHAR(90)",True,None,"« koffi-yao-riviera-3 », créé à la validation"),
 ("prenom","VARCHAR(60)",False,None,"Prénom"),
 ("nom","VARCHAR(60)",False,None,"Nom"),
 ("telephone","VARCHAR(20)",False,None,"Identifiant de connexion, format E.164 (+2250707123456)"),
 ("whatsapp","VARCHAR(20)",True,None,"Numéro WhatsApp s'il est différent"),
 ("email","VARCHAR(190)",True,None,"Facultatif, sert à réinitialiser le mot de passe"),
 ("email_verifie_le",DT,True,None,"Lien de vérification cliqué"),
 ("mot_de_passe_hash","VARCHAR(255)",True,None,"password_hash() ; vide si Google seulement"),
 ("google_sub","VARCHAR(64)",True,None,"Connexion Google"),
 ("metier","VARCHAR(80)",False,None,"« Électricien auto »"),
 ("annees_experience","TINYINT UNSIGNED",False,"0","Années de métier"),
 ("presentation","VARCHAR(500)",True,None,"Texte libre de la fiche"),
 ("atelier_adresse","VARCHAR(160)",True,None,"« Derrière le marché de Riviera 3 »"),
 ("atelier_quartier_id","SMALLINT UNSIGNED",True,None,"Quartier de l'atelier (affichage seulement)"),
 ("atelier_lat","DECIMAL(9,6)",True,None,"Position de l'atelier (affichage seulement)"),
 ("atelier_lon","DECIMAL(9,6)",True,None,""),
 ("rayon_km","TINYINT UNSIGNED",False,"10","Rayon d'intervention : 2, 5, 10 ou 20 km"),
 ("tarif_diagnostic_fcfa","INT UNSIGNED",True,None,"« Diagnostic dès » (indicatif)"),
 ("tarif_deplacement_fcfa","INT UNSIGNED",True,None,"Déplacement (indicatif)"),
 ("statut_dossier",ENUM("brouillon","en_attente","a_reprendre","valide","refuse","suspendu"),False,"'brouillon'","Voir la machine à états du dossier"),
 ("dossier_envoye_le",DT,True,None,"Dernier envoi pour vérification"),
 ("identite_verifiee_le","DATE",True,None,"Date affichée : « Visage vérifié le 12 mars 2026 »"),
 ("valide_par_admin_id","SMALLINT UNSIGNED",True,None,"Admin qui a validé"),
 ("motif_refus","VARCHAR(500)",True,None,"Motif communiqué en cas de refus"),
 ("cgu_version","VARCHAR(10)",True,None,"Version des CGU acceptée"),
 ("cgu_acceptees_le",DT,True,None,"Consentement"),
 ("etat",ENUM("indisponible","disponible","en_intervention"),False,"'indisponible'","Disponibilité actuelle"),
 ("etat_depuis",DT,True,None,"Début de l'état actuel"),
 ("dispo_debut",DT,True,None,"Début de la session de disponibilité (règle des 12 h)"),
 ("dispo_lat","DECIMAL(9,6)",True,None,"Position de disponibilité : base de la recherche"),
 ("dispo_lon","DECIMAL(9,6)",True,None,""),
 ("dispo_precision_m","SMALLINT UNSIGNED",True,None,"Précision GPS de cette position"),
 ("dispo_quartier_id","SMALLINT UNSIGNED",True,None,"Quartier le plus proche (« Riviera 3 »)"),
 ("position_maj_le",DT,True,None,"Dernière mise à jour de la position"),
 ("vu_le",DT,True,None,"Dernière fois que l'app mécano était ouverte (appel intégré possible si < 24 h)"),
 ("note_moyenne","DECIMAL(2,1)",True,None,"Calculée depuis avis publiés"),
 ("nb_avis","SMALLINT UNSIGNED",False,"0","Calculé"),
 ("taux_joignabilite","TINYINT UNSIGNED",True,None,"% d'appels intégrés décrochés sur 30 jours (calculé)"),
 ("appels_manques_suite","TINYINT UNSIGNED",False,"0","Appels intégrés manqués d'affilée (règle injoignable)"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP","Création du compte"),
 ("modifie_le",DT,False,"CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP","Dernière modification"),
 ("supprime_le",DT,True,None,"Suppression (données personnelles anonymisées)"),
],["id"],
 fks=[("atelier_quartier_id","quartiers","id","SET NULL"),("dispo_quartier_id","quartiers","id","SET NULL"),("valide_par_admin_id","admins","id","SET NULL")],
 uniques=[("uq_meca_public",["public_id"]),("uq_meca_slug",["slug"]),("uq_meca_tel",["telephone"]),("uq_meca_email",["email"]),("uq_meca_google",["google_sub"])],
 indexes=[("idx_meca_recherche",["statut_dossier","etat","dispo_lat","dispo_lon"]),("idx_meca_statut",["statut_dossier","dossier_envoye_le"])],
 checks=[("chk_meca_rayon","rayon_km IN (2,5,10,20)"),("chk_meca_auth","mot_de_passe_hash IS NOT NULL OR google_sub IS NOT NULL")])

table("mecanicien_specialites","meca","Spécialités d'un mécano (plusieurs).",[
 ("mecanicien_id","INT UNSIGNED",False,None,""),("specialite_id","SMALLINT UNSIGNED",False,None,""),
],["mecanicien_id","specialite_id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("specialite_id","specialites","id","RESTRICT")])

table("mecanicien_vehicules","meca","Types de véhicules qu'il répare (filtre strict).",[
 ("mecanicien_id","INT UNSIGNED",False,None,""),("type_vehicule","VARCHAR(10)",False,None,""),
],["mecanicien_id","type_vehicule"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("type_vehicule","types_vehicule","code","RESTRICT")])

table("pieces_dossier","meca","Chaque fichier envoyé pour la vérification. Un nouvel envoi remplace l'ancien sans l'effacer (historique).",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,"Identifiant"),
 ("mecanicien_id","INT UNSIGNED",False,None,"Propriétaire"),
 ("type_piece","VARCHAR(20)",False,None,"Type de pièce"),
 ("fichier","VARCHAR(255)",True,None,"Chemin relatif HORS du dossier public ; vidé à la suppression du fichier"),
 ("mime","VARCHAR(40)",False,None,"image/jpeg, image/webp, application/pdf"),
 ("taille_octets","INT UNSIGNED",False,None,"Après recompression"),
 ("empreinte_sha256","CHAR(64)",False,None,"Détecte les doublons et les altérations"),
 ("largeur","SMALLINT UNSIGNED",True,None,"Pixels"),
 ("hauteur","SMALLINT UNSIGNED",True,None,"Pixels"),
 ("statut",ENUM("envoyee","validee","a_reprendre","remplacee"),False,"'envoyee'","Décision sur cette pièce"),
 ("motif","VARCHAR(300)",True,None,"Motif si à reprendre"),
 ("examinee_par_admin_id","SMALLINT UNSIGNED",True,None,"Examinateur"),
 ("examinee_le",DT,True,None,"Date de décision"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP","Envoi"),
 ("fichier_supprime_le",DT,True,None,"Effacement du fichier (refus + 6 mois, suppression du compte)"),
],["id"],fks=[("mecanicien_id","mecaniciens","id","RESTRICT"),("type_piece","types_piece","code","RESTRICT"),("examinee_par_admin_id","admins","id","SET NULL")],
 indexes=[("idx_pieces_meca",["mecanicien_id","type_piece","statut"])])

table("photos","meca","Photos PUBLIQUES du mécano (portrait, atelier), générées depuis la pièce envoyée : versions WebP allégées, sans EXIF, servies depuis public/medias. Les pièces d'identité ne sont jamais exposées.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("type",ENUM("portrait","atelier"),False,None,"Portrait (fiche, repère, listes) ou atelier (fiche)"),
 ("piece_id","INT UNSIGNED",True,None,"Pièce d'origine (portrait ou atelier validé)"),
 ("dossier","VARCHAR(120)",False,None,"Chemin relatif dans public/medias (« m/01J…/portrait-3 »)"),
 ("largeurs","VARCHAR(40)",False,"'160,360,720'","Largeurs générées en WebP"),
 ("ratio","DECIMAL(4,3)",False,None,"Largeur / hauteur (réserve la place, pas de saut de page)"),
 ("couleur_dominante","CHAR(7)",False,None,"Fond affiché pendant le chargement (#6B5A48)"),
 ("focal_x","DECIMAL(3,2)",False,"0.50","Point de cadrage horizontal (object-position)"),
 ("focal_y","DECIMAL(3,2)",False,"0.30","Point de cadrage vertical"),
 ("active",B,False,"1","Une seule photo active par type ; l'ancienne passe à 0"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("piece_id","pieces_dossier","id","SET NULL")],indexes=[("idx_photos_meca",["mecanicien_id","type","active"])])

table("historique_dossier","meca","Chaque changement de statut du dossier, avec son auteur.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("de_statut","VARCHAR(20)",True,None,"Statut avant"),
 ("vers_statut","VARCHAR(20)",False,None,"Statut après"),
 ("admin_id","SMALLINT UNSIGNED",True,None,"Vide si action du mécano ou du système"),
 ("motif","VARCHAR(500)",True,None,""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("admin_id","admins","id","SET NULL")],indexes=[("idx_histdos_meca",["mecanicien_id","cree_le"])])

table("historique_disponibilite","meca","Périodes de disponibilité. Sert aux statistiques et à expliquer un passage automatique.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("etat",ENUM("indisponible","disponible","en_intervention"),False,None,"État de la période"),
 ("raison",ENUM("manuel","auto_12h","auto_injoignable","auto_fin_intervention","intervention","suspension","position_perimee"),False,None,"Pourquoi on est entré dans cet état"),
 ("quartier_id","SMALLINT UNSIGNED",True,None,"Quartier au début de la période"),
 ("debut",DT,False,None,""),
 ("fin",DT,True,None,"Vide tant que la période dure"),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("quartier_id","quartiers","id","SET NULL")],indexes=[("idx_histdispo_meca",["mecanicien_id","debut"])])

table("suspensions","meca","Suspensions décidées par un admin.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("admin_id","SMALLINT UNSIGNED",False,None,"Qui suspend"),
 ("motif","VARCHAR(500)",False,None,"Communiqué au mécano"),
 ("debut",DT,False,"CURRENT_TIMESTAMP",""),
 ("fin_prevue",DT,True,None,"24 h, 7 jours, ou vide = jusqu'à nouvel ordre"),
 ("levee_le",DT,True,None,""),
 ("levee_par_admin_id","SMALLINT UNSIGNED",True,None,""),
 ("signalement_id","INT UNSIGNED",True,None,"Signalement à l'origine, s'il y en a un"),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("admin_id","admins","id","RESTRICT"),("levee_par_admin_id","admins","id","SET NULL")],
 indexes=[("idx_susp_meca",["mecanicien_id","debut"])],note="La clé vers signalements est ajoutée après la création de cette table (ALTER) pour éviter une dépendance circulaire.")

table("abonnements_push","meca","Téléphones du mécano capables de recevoir un appel intégré (Web Push).",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("endpoint","VARCHAR(600)",False,None,"URL du service de push du navigateur"),
 ("endpoint_hash","CHAR(64)",False,None,"SHA-256 de l'endpoint (index unique)"),
 ("cle_p256dh","VARCHAR(120)",False,None,"Clé publique du navigateur"),
 ("cle_auth","VARCHAR(40)",False,None,"Secret d'authentification"),
 ("appareil","VARCHAR(120)",True,None,"« Chrome · Android »"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
 ("dernier_succes_le",DT,True,None,"Dernier push accepté"),
 ("echecs_suite","TINYINT UNSIGNED",False,"0","Révoqué après 3 échecs ou un 404/410"),
 ("revoque_le",DT,True,None,""),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE")],uniques=[("uq_push_endpoint",["endpoint_hash"])],indexes=[("idx_push_meca",["mecanicien_id","revoque_le"])])

# ------------------------------------------------------------------ Clients & recherches
table("clients","client","Le client, sans compte : un jeton aléatoire gardé dans son navigateur. Prénom et téléphone facultatifs, jamais publics.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("jeton_hash","CHAR(64)",False,None,"SHA-256 du jeton du navigateur (le jeton lui-même n'est jamais stocké)"),
 ("prenom","VARCHAR(40)",True,None,"« Pour que le mécano vous reconnaisse »"),
 ("telephone","VARCHAR(20)",True,None,"Facultatif, jamais affiché publiquement"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
 ("vu_le",DT,False,"CURRENT_TIMESTAMP","Dernière visite"),
],["id"],uniques=[("uq_clients_jeton",["jeton_hash"])])

table("recherches","client","Une recherche de mécano. La position exacte est effacée au bout de 24 h, la ligne au bout de 90 jours.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("public_id","CHAR(26)",False,None,"ULID (URL de la page résultats)"),
 ("client_id","BIGINT UNSIGNED",False,None,""),
 ("type_vehicule","VARCHAR(10)",False,None,"Filtre strict"),
 ("panne_id","SMALLINT UNSIGNED",False,None,"Fait remonter les spécialistes"),
 ("precisions","VARCHAR(300)",True,None,"« ça fait clic quand je tourne la clé »"),
 ("source_position",ENUM("gps","quartier","carte"),False,None,"GPS, quartier choisi, ou point déplacé sur la carte"),
 ("quartier_id","SMALLINT UNSIGNED",True,None,"Quartier choisi ou le plus proche"),
 ("lat","DECIMAL(9,6)",True,None,"Effacée après 24 h"),
 ("lon","DECIMAL(9,6)",True,None,"Effacée après 24 h"),
 ("precision_m","INT UNSIGNED",True,None,"Précision GPS réelle"),
 ("repere","VARCHAR(160)",True,None,"« devant la pharmacie des Jardins »"),
 ("rayon_km","TINYINT UNSIGNED",False,None,"Rayon réellement utilisé (2, 5 ou 10)"),
 ("nb_resultats","TINYINT UNSIGNED",False,"0",""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
 ("position_effacee_le",DT,True,None,""),
],["id"],fks=[("client_id","clients","id","CASCADE"),("type_vehicule","types_vehicule","code","RESTRICT"),("panne_id","pannes","id","RESTRICT"),("quartier_id","quartiers","id","SET NULL")],
 uniques=[("uq_rech_public",["public_id"])],indexes=[("idx_rech_client",["client_id","cree_le"]),("idx_rech_date",["cree_le"])])

table("resultats_recherche","client","La liste ordonnée montrée au client. Sert à proposer « le suivant » sans relancer la recherche.",[
 ("recherche_id","BIGINT UNSIGNED",False,None,""),
 ("rang","TINYINT UNSIGNED",False,None,"1 = le plus proche après pondération"),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("distance_m","INT UNSIGNED",False,None,"À vol d'oiseau (Haversine)"),
 ("specialiste",B,False,"0","A une spécialité liée à la panne"),
 ("autre_rive",B,False,"0","Le mécano est de l'autre côté de la lagune (RG-R9)"),
],["recherche_id","rang"],fks=[("recherche_id","recherches","id","CASCADE"),("mecanicien_id","mecaniciens","id","CASCADE")],
 uniques=[("uq_res_meca",["recherche_id","mecanicien_id"])],indexes=[("idx_res_meca",["mecanicien_id"])])

table("zones_sans_resultat","client","Comptage anonyme des recherches sans résultat, par zone d'environ 1 km (savoir où recruter). Aucun lien vers le client. 90 jours.",[
 ("zone_id","VARCHAR(12)",False,None,"Geohash de précision 6 (≈ 1,2 × 0,6 km), calculable en PHP sans librairie"),
 ("jour","DATE",False,None,""),
 ("type_vehicule","VARCHAR(10)",False,None,""),
 ("nb","SMALLINT UNSIGNED",False,"1",""),
],["zone_id","jour","type_vehicule"])

# ------------------------------------------------------------------ Mises en relation
table("contacts","contact","Une tentative de contact client → mécano, par l'un des 3 canaux. C'est la table centrale : appel, issue, reproposition, droit à l'avis.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("public_id","CHAR(26)",False,None,"ULID (suivi en direct côté client et côté mécano)"),
 ("recherche_id","BIGINT UNSIGNED",True,None,"Vidé quand la recherche est purgée (90 j)"),
 ("client_id","BIGINT UNSIGNED",True,None,"Vidé si le client est purgé"),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("canal",ENUM("app","tel","whatsapp"),False,None,"Appel intégré, appel direct, WhatsApp"),
 ("rang","TINYINT UNSIGNED",True,None,"Rang du mécano dans les résultats"),
 ("contact_precedent_id","BIGINT UNSIGNED",True,None,"Contact raté qui a mené à cette reproposition"),
 ("statut",ENUM("initie","sonne","decroche","termine","manque","occupe","echoue","annule"),False,"'initie'","tel/WhatsApp restent « initie » ; voir la machine à états de l'appel"),
 ("cree_le","DATETIME(3)",False,"CURRENT_TIMESTAMP(3)","Clic sur le bouton"),
 ("sonne_le","DATETIME(3)",True,None,"Push envoyé au mécano"),
 ("decroche_le","DATETIME(3)",True,None,""),
 ("termine_le","DATETIME(3)",True,None,""),
 ("duree_s","SMALLINT UNSIGNED",True,None,"Durée de conversation (appel intégré)"),
 ("fin_par",ENUM("client","mecanicien","delai","reseau","micro_refuse"),True,None,"Cause de fin"),
 ("absence_s","SMALLINT UNSIGNED",True,None,"tel/WhatsApp : temps passé hors d'allomécano"),
 ("resume","VARCHAR(120)",False,None,"« Voiture · Batterie », gardé"),
 ("quartier_libelle","VARCHAR(80)",True,None,"« Riviera 2 », gardé"),
 ("position_partagee",B,False,"1","Interrupteur « Lien de ma position »"),
 ("lat","DECIMAL(9,6)",True,None,"Envoyée au mécano contacté, effacée après 24 h"),
 ("lon","DECIMAL(9,6)",True,None,"Effacée après 24 h"),
 ("repere","VARCHAR(160)",True,None,"Effacé après 24 h"),
 ("issue_client",ENUM("arrive","pas_entendus","pas_reponse"),True,None,"Réponse du client"),
 ("raison_desaccord",ENUM("prix","delai","specialite","autre"),True,None,"Si « pas entendus » (facultatif, jamais public)"),
 ("issue_client_le",DT,True,None,""),
 ("issue_mecano",ENUM("j_y_vais","pas_pour_moi"),True,None,"Réponse du mécano"),
 ("issue_mecano_le",DT,True,None,""),
 ("intervention_fin_le",DT,True,None,"Fin de l'état « en intervention »"),
 ("intervention_fin_par",ENUM("mecanicien","client","auto"),True,None,""),
 ("donnees_effacees_le",DT,True,None,"Position et repère effacés"),
],["id"],fks=[("recherche_id","recherches","id","SET NULL"),("client_id","clients","id","SET NULL"),("mecanicien_id","mecaniciens","id","RESTRICT"),("contact_precedent_id","contacts","id","SET NULL")],
 uniques=[("uq_contact_public",["public_id"])],
 indexes=[("idx_contact_meca",["mecanicien_id","cree_le"]),("idx_contact_client",["client_id","cree_le"]),("idx_contact_actif",["statut","mecanicien_id"]),("idx_contact_purge",["donnees_effacees_le","cree_le"])])

table("signalisation","contact","Messages techniques de l'appel intégré (offre, réponse, candidats réseau, raccrocher), lus par interrogation régulière. Effacés 1 h après la fin de l'appel.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,"Ordre des messages"),
 ("contact_id","BIGINT UNSIGNED",False,None,""),
 ("emetteur",ENUM("client","mecanicien"),False,None,""),
 ("type",ENUM("offre","reponse","candidat","raccroche","refus","sonne"),False,None,""),
 ("charge","MEDIUMTEXT",True,None,"JSON (SDP ou candidat ICE)"),
 ("cree_le","DATETIME(3)",False,"CURRENT_TIMESTAMP(3)",""),
],["id"],fks=[("contact_id","contacts","id","CASCADE")],indexes=[("idx_sig_contact",["contact_id","id"])])

# ------------------------------------------------------------------ Avis & signalements
table("avis","avis","Avis d'un client, rattaché à UN contact réel (anti-faux avis).",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("contact_id","BIGINT UNSIGNED",False,None,"Un avis par contact"),
 ("mecanicien_id","INT UNSIGNED",False,None,"Recopié pour les requêtes de la fiche"),
 ("client_id","BIGINT UNSIGNED",True,None,"Vidé à l'anonymisation"),
 ("note","TINYINT UNSIGNED",False,None,"1 à 5"),
 ("commentaire","VARCHAR(600)",True,None,"Facultatif"),
 ("prenom_affiche","VARCHAR(40)",True,None,"« Aminata » ; vide = « Un client »"),
 ("panne_libelle","VARCHAR(60)",False,None,"« Batterie », affiché sous l'avis"),
 ("statut",ENUM("publie","masque"),False,"'publie'",""),
 ("masque_par_admin_id","SMALLINT UNSIGNED",True,None,""),
 ("motif_masquage","VARCHAR(300)",True,None,""),
 ("reponse_mecano","VARCHAR(600)",True,None,"Réponse publique du mécano (une seule, modifiable)"),
 ("reponse_le",DT,True,None,"Date de la réponse"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
 ("modifie_le",DT,False,"CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP","Modifiable 24 h"),
],["id"],fks=[("contact_id","contacts","id","RESTRICT"),("mecanicien_id","mecaniciens","id","RESTRICT"),("client_id","clients","id","SET NULL"),("masque_par_admin_id","admins","id","SET NULL")],
 uniques=[("uq_avis_contact",["contact_id"])],indexes=[("idx_avis_fiche",["mecanicien_id","statut","cree_le"])],checks=[("chk_avis_note","note BETWEEN 1 AND 5")])

table("avis_etiquettes","avis","Étiquettes choisies avec un avis.",[
 ("avis_id","INT UNSIGNED",False,None,""),("etiquette_id","TINYINT UNSIGNED",False,None,""),
],["avis_id","etiquette_id"],fks=[("avis_id","avis","id","CASCADE"),("etiquette_id","etiquettes_avis","id","RESTRICT")])

table("signalements","avis","Signalement d'un mécano par un client. Le mécano ne sait jamais qui l'a signalé.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("public_id","CHAR(26)",False,None,""),
 ("mecanicien_id","INT UNSIGNED",False,None,""),
 ("contact_id","BIGINT UNSIGNED",True,None,"Contact concerné s'il existe"),
 ("client_id","BIGINT UNSIGNED",True,None,""),
 ("motif",ENUM("identite","comportement","prix_abusif","pas_venu","securite","autre"),False,None,"« identite » rouvre la comparaison photo ↔ pièce"),
 ("description","VARCHAR(1000)",True,None,""),
 ("photo_fichier","VARCHAR(255)",True,None,"Hors du dossier public, EXIF supprimé, 300 Ko max"),
 ("statut",ENUM("nouveau","en_cours","sans_suite","sanction"),False,"'nouveau'",""),
 ("traite_par_admin_id","SMALLINT UNSIGNED",True,None,""),
 ("traite_le",DT,True,None,""),
 ("decision","VARCHAR(500)",True,None,""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],fks=[("mecanicien_id","mecaniciens","id","RESTRICT"),("contact_id","contacts","id","SET NULL"),("client_id","clients","id","SET NULL"),("traite_par_admin_id","admins","id","SET NULL")],
 uniques=[("uq_sig_public",["public_id"])],indexes=[("idx_sig_statut",["statut","cree_le"]),("idx_sig_meca",["mecanicien_id"])],version="V1")

# ------------------------------------------------------------------ Système
table("jetons_reinitialisation","sys","Liens de réinitialisation du mot de passe (mécano ou admin), valables 30 min, à usage unique.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",True,None,""),
 ("admin_id","SMALLINT UNSIGNED",True,None,""),
 ("jeton_hash","CHAR(64)",False,None,"SHA-256 du jeton envoyé par e-mail"),
 ("expire_le",DT,False,None,"+30 min"),
 ("utilise_le",DT,True,None,""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("admin_id","admins","id","CASCADE")],
 uniques=[("uq_jeton",["jeton_hash"])],note="Exactement un des deux propriétaires est renseigné : contrôlé en PHP (MySQL interdit un CHECK sur une colonne de clé étrangère avec ON DELETE).")

table("tentatives_connexion","sys","Pour bloquer 15 min après 5 échecs.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("identifiant","VARCHAR(190)",False,None,"Téléphone ou e-mail normalisé"),
 ("ip","VARBINARY(16)",False,None,"inet_pton()"),
 ("reussie",B,False,None,""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],indexes=[("idx_tent_id",["identifiant","cree_le"]),("idx_tent_ip",["ip","cree_le"])])

table("notifications","sys","File d'envoi des notifications (push et e-mail). Le SMS arrive en V2 avec un nouveau canal.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("mecanicien_id","INT UNSIGNED",True,None,""),
 ("admin_id","SMALLINT UNSIGNED",True,None,""),
 ("type",ENUM("appel_entrant","dossier_recu","dossier_valide","dossier_a_reprendre","dossier_refuse","indispo_auto","fin_intervention_auto","nouvel_avis","suspension","reinitialisation","nouveau_dossier","nouveau_signalement","nouveau_message"),False,None,""),
 ("canal",ENUM("push","email"),False,None,""),
 ("statut",ENUM("en_attente","envoyee","echec"),False,"'en_attente'",""),
 ("charge","JSON",True,None,"Titre, texte, lien"),
 ("tentatives","TINYINT UNSIGNED",False,"0",""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
 ("envoyee_le",DT,True,None,""),
 ("erreur","VARCHAR(255)",True,None,""),
],["id"],fks=[("mecanicien_id","mecaniciens","id","CASCADE"),("admin_id","admins","id","CASCADE")],indexes=[("idx_notif_file",["statut","cree_le"])])

table("journal_admin","sys","Toutes les actions des admins, y compris chaque consultation de pièce et chaque numéro de client affiché. On n'y écrit qu'en ajout.",[
 ("id","BIGINT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("admin_id","SMALLINT UNSIGNED",False,None,""),
 ("action","VARCHAR(40)",False,None,"« consulter_piece », « valider_dossier », « afficher_numero »…"),
 ("cible_type",ENUM("mecanicien","piece","avis","signalement","client","contact","admin","message"),False,None,""),
 ("cible_id","BIGINT UNSIGNED",False,None,""),
 ("details","JSON",True,None,"Motif, ancien et nouveau statut…"),
 ("ip","VARBINARY(16)",True,None,""),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],fks=[("admin_id","admins","id","RESTRICT")],indexes=[("idx_journal_cible",["cible_type","cible_id"]),("idx_journal_admin",["admin_id","cree_le"])])

table("messages_contact","sys","Messages du formulaire Contact (X4), traités par un admin.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("profil",ENUM("client","mecano","partenaire","presse","autre"),False,None,"Qui écrit"),
 ("nom","VARCHAR(80)",False,None,""),
 ("telephone","VARCHAR(20)",True,None,"Au moins un moyen de réponse"),
 ("email","VARCHAR(190)",True,None,""),
 ("message","VARCHAR(2000)",False,None,""),
 ("statut",ENUM("nouveau","traite"),False,"'nouveau'",""),
 ("traite_par_admin_id","SMALLINT UNSIGNED",True,None,""),
 ("traite_le",DT,True,None,""),
 ("ip","VARBINARY(16)",True,None,"Anti-abus"),
 ("cree_le",DT,False,"CURRENT_TIMESTAMP",""),
],["id"],fks=[("traite_par_admin_id","admins","id","SET NULL")],indexes=[("idx_msg_statut",["statut","cree_le"])],checks=[("chk_msg_contact","telephone IS NOT NULL OR email IS NOT NULL")])

table("journal_taches","sys","Exécutions des tâches planifiées (cron), pour vérifier qu'elles tournent.",[
 ("id","INT UNSIGNED AUTO_INCREMENT",False,None,""),
 ("tache","VARCHAR(60)",False,None,"« purge_positions », « dispo_12h »…"),
 ("debut",DT,False,None,""),
 ("fin",DT,True,None,""),
 ("ok",B,True,None,""),
 ("lignes","INT UNSIGNED",True,None,"Lignes traitées"),
 ("message","VARCHAR(255)",True,None,""),
],["id"],indexes=[("idx_taches",["tache","debut"])])

LATE_FKS = [("suspensions","signalement_id","signalements","id","SET NULL")]

# Quartiers par commune (C2b) : centres approximatifs À VÉRIFIER sur OpenStreetMap avant la mise en ligne.
# (commune, nom, slug sans la commune, alias pour l'autocomplétion, lat, lon), dans l'ordre d'affichage : les plus connus d'abord
QUARTIERS = [
 ("abobo","Abobo Gare","gare","Gare d'Abobo",5.4160,-4.0180),("abobo","PK 18","pk-18","PK18",5.4560,-4.0450),
 ("abobo","Avocatier","avocatier",None,5.4300,-4.0350),("abobo","Sagbé","sagbe","Sagbe",5.4330,-4.0480),
 ("abobo","Anonkoua-Kouté","anonkoua-koute","Anonkoua",5.4480,-4.0050),("abobo","Akeikoi","akeikoi","Akéikoi",5.4580,-4.0200),
 ("abobo","Abobo Baoulé","baoule","Baoulé",5.4210,-3.9990),("abobo","N'Dotré","ndotre","Ndotre, N'Dotre",5.4600,-4.0650),
 ("abobo","Samaké","samake","Samake",5.4060,-4.0300),("abobo","Belleville","belleville",None,5.4100,-4.0050),
 ("adjame","Adjamé Liberté","liberte","Liberté",5.3580,-4.0180),("adjame","Williamsville","williamsville","Williams",5.3720,-4.0220),
 ("adjame","Bracodi","bracodi",None,5.3530,-4.0230),("adjame","220 Logements","220-logements","220 logts",5.3650,-4.0130),
 ("adjame","Marie-Thérèse","marie-therese","Marie Therese",5.3600,-4.0300),("adjame","Habitat Extension","habitat-extension","Extension",5.3700,-4.0150),
 ("adjame","Gare Nord","gare-nord","Gare UTB",5.3640,-4.0260),
 ("anyama","Anyama centre","centre","Anyama",5.4950,-4.0520),("anyama","Ebimpé","ebimpe","Ebimpe",5.4700,-4.0900),
 ("anyama","Akoupé-Zeudji","akoupe-zeudji","Akoupé",5.5200,-4.0600),("anyama","Anyama Adjamé","adjame","Adjamé d'Anyama",5.4920,-4.0450),
 ("anyama","Thomasset","thomasset",None,5.5050,-4.0400),
 ("attecoube","Attécoubé centre","centre","Attécoubé",5.3330,-4.0400),("attecoube","Locodjro","locodjro","Locodjoro",5.3330,-4.0600),
 ("attecoube","Santé","sante","Sante",5.3400,-4.0400),("attecoube","Agban","agban","Agban village",5.3480,-4.0320),
 ("attecoube","Boribana","boribana",None,5.3270,-4.0300),("attecoube","Abobo-Doumé","abobo-doume","Abobo Doume",5.3170,-4.0450),
 ("attecoube","Sébroko","sebroko","Sebroko",5.3420,-4.0500),
 ("bingerville","Bingerville centre","centre","Bingerville",5.3550,-3.8850),("bingerville","Akandjé","akandje","Akandje",5.3800,-3.9000),
 ("bingerville","Gbagba","gbagba",None,5.3460,-3.8730),("bingerville","Abatta","abatta",None,5.3700,-3.9300),
 ("bingerville","Feh Kessé","feh-kesse","Fehkessé",5.3620,-3.8700),("bingerville","Santai","santai",None,5.3450,-3.9050),
 ("cocody","Riviera 2","riviera-2","Riv 2, Riviera II",5.3600,-3.9600),("cocody","Riviera 3","riviera-3","Riv 3, Riviera III",5.3650,-3.9480),
 ("cocody","Riviera Palmeraie","riviera-palmeraie","Palmeraie",5.3720,-3.9350),("cocody","Angré 8e tranche","angre-8e-tranche","Angre, 8e tranche, Angré",5.3950,-3.9900),
 ("cocody","Deux-Plateaux Vallon","deux-plateaux-vallon","2 Plateaux, Vallons, II Plateaux",5.3700,-4.0000),
 ("cocody","Riviera Golf","riviera-golf","Golf",5.3380,-3.9550),("cocody","Angré Château","angre-chateau","Château",5.3950,-3.9780),
 ("cocody","Cocody centre","centre","Cocody",5.3470,-3.9950),("cocody","Blockhaus","blockhaus",None,5.3360,-3.9850),
 ("cocody","Danga","danga",None,5.3380,-4.0050),("cocody","Faya","faya",None,5.3850,-3.9200),("cocody","M'Pouto","mpouto","Mpouto",5.3300,-3.9450),
 ("koumassi","Koumassi centre","centre","Koumassi",5.2970,-3.9500),("koumassi","Remblais","remblais",None,5.2900,-3.9600),
 ("koumassi","Sicogi","sicogi",None,5.3000,-3.9420),("koumassi","Campement","campement",None,5.2950,-3.9300),
 ("koumassi","Prodomo","prodomo",None,5.3050,-3.9520),("koumassi","Grand Marché","grand-marche","Marché de Koumassi",5.2990,-3.9450),
 ("marcory","Zone 4","zone-4","Zone IV",5.2900,-3.9750),("marcory","Biétry","bietry","Bietry",5.2830,-3.9700),
 ("marcory","Marcory Résidentiel","residentiel","Résidentiel",5.3050,-3.9850),("marcory","Anoumabo","anoumabo",None,5.3000,-3.9950),
 ("marcory","Sans Fil","sans-fil","Sans-fil",5.3100,-3.9800),("marcory","Champroux","champroux",None,5.3080,-3.9900),
 ("plateau","Plateau centre","centre","Plateau",5.3230,-4.0190),("plateau","Indénié","indenie","Indenie",5.3330,-4.0130),
 ("plateau","Cité administrative","cite-administrative","Cité admin",5.3250,-4.0230),("plateau","Plateau Sud","sud",None,5.3160,-4.0200),
 ("port-bouet","Port-Bouët centre","centre","Port-Bouet",5.2550,-3.9400),("port-bouet","Gonzagueville","gonzagueville","Gonzague",5.2450,-3.8750),
 ("port-bouet","Vridi","vridi","Vridi canal",5.2600,-4.0000),("port-bouet","Aéroport","aeroport","Aeroport",5.2560,-3.9300),
 ("port-bouet","Adjouffou","adjouffou",None,5.2500,-3.9200),("port-bouet","Jean Folly","jean-folly",None,5.2580,-3.9500),
 ("songon","Songon Agban","agban","Songon",5.3200,-4.2600),("songon","Songon Kassemblé","kassemble","Kassemble",5.3100,-4.2900),
 ("songon","Songon M'Brathé","mbrathe","Mbrathe",5.3300,-4.2300),("songon","Songon Dagbé","dagbe","Dagbe",5.3150,-4.2450),
 ("treichville","Treichville centre","centre","Treichville",5.2930,-4.0050),("treichville","Arras","arras",None,5.2950,-4.0050),
 ("treichville","Biafra","biafra",None,5.2970,-4.0120),("treichville","Belleville","belleville",None,5.2880,-4.0100),
 ("treichville","Gare de Bassam","gare-de-bassam","Gare Bassam",5.2920,-3.9970),("treichville","Zone portuaire","zone-portuaire","Port",5.2850,-4.0150),
 ("yopougon","Niangon","niangon","Niangon Nord, Niangon Sud",5.3350,-4.1050),("yopougon","Sideci","sideci","Sidéci",5.3350,-4.0750),
 ("yopougon","Selmer","selmer",None,5.3500,-4.0800),("yopougon","Maroc","maroc",None,5.3480,-4.0700),
 ("yopougon","Port-Bouët 2","port-bouet-2","PB2",5.3380,-4.0920),("yopougon","Toits Rouges","toits-rouges",None,5.3550,-4.0850),
 ("yopougon","Andokoi","andokoi","Andokoua",5.3650,-4.0900),("yopougon","Wassakara","wassakara",None,5.3400,-4.0650),
 ("yopougon","Banco","banco","Banco 2",5.3600,-4.0700),("yopougon","Koweït","koweit","Koweit",5.3300,-4.0900),
 ("yopougon","Gesco","gesco",None,5.3550,-4.1100),("yopougon","Yopougon Kouté","koute","Kouté",5.3220,-4.0700),
]

# ------------------------------------------------------------------ Données de départ
SEED = {
 "communes": [("Abobo","abobo",5.4190,-4.0200,"nord"),("Adjamé","adjame",5.3600,-4.0230,"nord"),("Anyama","anyama",5.4950,-4.0520,"nord"),("Attécoubé","attecoube",5.3330,-4.0400,"nord"),
              ("Bingerville","bingerville",5.3550,-3.8850,"nord"),("Cocody","cocody",5.3600,-3.9700,"nord"),("Koumassi","koumassi",5.2970,-3.9500,"sud"),("Marcory","marcory",5.3030,-3.9800,"sud"),
              ("Plateau","plateau",5.3230,-4.0190,"nord"),("Port-Bouët","port-bouet",5.2560,-3.9300,"sud"),("Songon","songon",5.3200,-4.2600,"nord",0),("Treichville","treichville",5.2930,-4.0050,"sud"),("Yopougon","yopougon",5.3450,-4.0900,"nord")],
 "types_vehicule": [("voiture","Voiture",1),("moto","Moto",2),("camion","Camion",3)],
 "specialites": [("mecanique_generale","Mécanique générale"),("electricite_auto","Électricité auto"),("batterie","Batterie"),("demarreur_alternateur","Démarreur et alternateur"),
                 ("moteur","Moteur"),("refroidissement","Refroidissement"),("pneus","Pneus et vulcanisation"),("freins","Freins"),("boite_vitesses","Boîte de vitesses"),
                 ("carrosserie","Carrosserie"),("remorquage","Dépannage et remorquage"),("moto","Mécanique moto")],
 "pannes": [("ne_demarre_pas","Ne démarre pas","key",0),("batterie","Batterie","battery",0),("surchauffe","Surchauffe","heat",0),("pneu_creve","Pneu crevé","tyre",0),
            ("huile_fuite","Huile ou fuite","oil",0),("freins","Freins","brake",0),("choc_accident","Choc ou accident","crash",1),("bruit_fumee","Bruit ou fumée","smoke",0),("je_ne_sais_pas","Je ne sais pas","q",0)],
 "panne_specialite": {"ne_demarre_pas":["batterie","demarreur_alternateur","electricite_auto"],"batterie":["batterie","electricite_auto"],"surchauffe":["refroidissement","moteur"],
                      "pneu_creve":["pneus"],"huile_fuite":["moteur","mecanique_generale"],"freins":["freins"],"choc_accident":["carrosserie","remorquage"],
                      "bruit_fumee":["moteur","mecanique_generale"],"je_ne_sais_pas":["mecanique_generale"]},
 "types_piece": [("cni_recto","Pièce d'identité (recto)",1,1),("cni_verso","Pièce d'identité (verso)",1,2),("permis","Permis de conduire",1,3),("diplome","Diplôme ou attestation de métier",1,4),("portrait","Photo de vous",1,5),("atelier","Photo de l'atelier",0,6)],
 "etiquettes_avis": [("rapide","Arrivé vite","positive"),("prix_respecte","Prix respecté","positive"),("bon_travail","Travail propre","positive"),("explique","A bien expliqué","positive"),("poli","Poli","positive"),
                     ("en_retard","En retard","negative"),("prix_change","Prix changé","negative"),("pas_repare","Pas réparé","negative")],
 "parametres": [
  ("recherche.rayons_km","2,5,10","liste","RG-R5 · paliers d'élargissement"),
  ("recherche.min_resultats","3","int","RG-R5 · on élargit tant qu'on a moins que ça"),
  ("recherche.max_resultats","10","int","RG-R6 · repères affichés au maximum"),
  ("recherche.bonus_specialiste","0.75","decimal","RG-R6 · distance d'un spécialiste multipliée par ce facteur"),
  ("dispo.position_perimee_h","2","int","RG-D2 · « Mettre à jour ma position » devient l'action principale"),
  ("dispo.duree_max_h","12","int","RG-D3 · passage automatique en indisponible"),
  ("intervention.fin_auto_h","2","int","RG-D4 · fin automatique de « en intervention »"),
  ("appel.sonnerie_s","30","int","RG-A5 · durée de sonnerie avant « manqué »"),
  ("appel.relance_push_s","5","int","RG-A5 · intervalle de relance de la notification"),
  ("appel.echec_reseau_s","15","int","RG-A7 · connexion audio impossible"),
  ("appel.vu_recent_h","24","int","RG-A2 · le mécano peut recevoir l'appel intégré"),
  ("injoignable.seuil","3","int","RG-D5 · appels intégrés manqués d'affilée"),
  ("injoignable.fenetre_h","2","int","RG-D5 · fenêtre de comptage"),
  ("antiabus.contacts_client_10min","5","int","RG-A10"),
  ("antiabus.contacts_meca_h","3","int","RG-A10 · même client vers le même mécano"),
  ("avis.delai_j","7","int","RG-AV1 · délai pour laisser un avis"),
  ("avis.duree_min_s","30","int","RG-AV1 · appel intégré assez long pour ouvrir l'avis"),
  ("avis.modifiable_h","24","int","RG-AV2"),
  ("avis.seuil_note","3","int","RG-AV6 · en dessous, la fiche affiche « Nouveau »"),
  ("purge.position_h","24","int","RG-P1"),
  ("purge.recherches_j","90","int","RG-P2"),
  ("purge.signalisation_h","1","int","RG-P3"),
  ("purge.pieces_refus_mois","6","int","RG-P5"),
  ("auth.essais_max","5","int","RG-C2"),
  ("auth.blocage_min","15","int","RG-C2"),
  ("auth.lien_reinit_min","30","int","RG-C3"),
  ("dossier.delai_annonce_h","48","int","RG-DO3 · délai de réponse annoncé"),
  ("recherche.malus_autre_rive","1.30","decimal","RG-R9 · distance d'un mécano de l'autre rive multipliée par ce facteur"),
  ("contact.messages_ip_h","3","int","RG-CT1 · messages du formulaire par adresse IP et par heure"),
  ("urgence.samu","185","texte","Numéro affiché (choc ou accident)"),
  ("urgence.pompiers","180","texte","Numéro affiché"),
  ("urgence.police","170","texte","Numéro affiché"),
 ],
}
