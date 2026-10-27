-- =====================================================================
-- allomécano · schéma MySQL 8 / MariaDB 10.6+ · InnoDB · utf8mb4
-- Généré depuis model.py : ne pas modifier à la main, modifier le modèle.
-- Toutes les dates sont en UTC (= heure d'Abidjan, GMT toute l'année).
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Référentiels · Les 13 communes du District d'Abidjan.
DROP TABLE IF EXISTS `communes`;
CREATE TABLE `communes` (
  `id` TINYINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `nom` VARCHAR(60) NOT NULL COMMENT 'Nom affiché (« Cocody »)',
  `slug` VARCHAR(60) NOT NULL COMMENT 'Pour les URL (« cocody »)',
  `lat` DECIMAL(9,6) NOT NULL COMMENT 'Centre approximatif',
  `lon` DECIMAL(9,6) NOT NULL COMMENT 'Centre approximatif',
  `rive` ENUM('nord','sud') NOT NULL COMMENT 'Côté de la lagune Ébrié (état « Autre rive » des résultats)',
  `ouverte` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = « Bientôt » dans la liste des communes',
  `ordre` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_communes_nom` (`nom`),
  UNIQUE KEY `uq_communes_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Les 13 communes du District d''Abidjan.';

-- Référentiels · Quartiers par commune, pour la recherche sans GPS. Liste à constituer depuis OpenStreetMap puis relue.
DROP TABLE IF EXISTS `quartiers`;
CREATE TABLE `quartiers` (
  `id` SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `commune_id` TINYINT UNSIGNED NOT NULL COMMENT 'Commune',
  `nom` VARCHAR(80) NOT NULL COMMENT '« Riviera 2 »',
  `slug` VARCHAR(100) NOT NULL COMMENT '« cocody-riviera-2 »',
  `alias` VARCHAR(255) COMMENT 'Autres noms pour l''autocomplétion (« Riv 2, Riviera II »)',
  `lat` DECIMAL(9,6) NOT NULL COMMENT 'Centre du quartier',
  `lon` DECIMAL(9,6) NOT NULL COMMENT 'Centre du quartier',
  `actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Proposé dans la recherche',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quartiers_slug` (`slug`),
  UNIQUE KEY `uq_quartiers_commune_nom` (`commune_id`, `nom`),
  KEY `idx_quartiers_nom` (`nom`),
  CONSTRAINT `fk_quartiers_commune_id` FOREIGN KEY (`commune_id`) REFERENCES `communes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quartiers par commune, pour la recherche sans GPS. Liste à constituer depuis OpenStreetMap puis relue.';

-- Référentiels · Voiture, moto, camion. Filtre strict de la recherche.
DROP TABLE IF EXISTS `types_vehicule`;
CREATE TABLE `types_vehicule` (
  `code` VARCHAR(10) NOT NULL COMMENT '« voiture », « moto », « camion »',
  `libelle` VARCHAR(30) NOT NULL COMMENT 'Libellé affiché',
  `ordre` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Voiture, moto, camion. Filtre strict de la recherche.';

-- Référentiels · Ce que le mécano sait réparer (étiquettes de la fiche).
DROP TABLE IF EXISTS `specialites`;
CREATE TABLE `specialites` (
  `id` SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `code` VARCHAR(40) NOT NULL COMMENT '« electricite_auto »',
  `libelle` VARCHAR(60) NOT NULL COMMENT '« Électricité auto »',
  `ordre` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  `actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Proposée à l''inscription',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_specialites_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ce que le mécano sait réparer (étiquettes de la fiche).';

-- Référentiels · Les tuiles « Quelle est la panne ? » de l'écran C3.
DROP TABLE IF EXISTS `pannes`;
CREATE TABLE `pannes` (
  `id` SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `code` VARCHAR(40) NOT NULL COMMENT '« batterie »',
  `libelle` VARCHAR(60) NOT NULL COMMENT '« Batterie »',
  `picto` VARCHAR(30) NOT NULL COMMENT 'Nom du picto voyant de bord',
  `est_danger` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Affiche le rappel « Appelez le 185 d''abord » (choc ou accident)',
  `ordre` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  `actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Proposée au client',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pannes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Les tuiles « Qu''est-ce qui s''allume ? » de l''écran C3.';

-- Référentiels · Quelles spécialités traitent quelle panne. Sert à faire remonter les spécialistes (la panne ne filtre pas).
DROP TABLE IF EXISTS `panne_specialite`;
CREATE TABLE `panne_specialite` (
  `panne_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Panne',
  `specialite_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Spécialité qui la traite',
  PRIMARY KEY (`panne_id`, `specialite_id`),
  CONSTRAINT `fk_panne_specialite_panne_id` FOREIGN KEY (`panne_id`) REFERENCES `pannes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_panne_specialite_specialite_id` FOREIGN KEY (`specialite_id`) REFERENCES `specialites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quelles spécialités traitent quelle panne. Sert à faire remonter les spécialistes (la panne ne filtre pas).';

-- Référentiels · Les pièces du dossier de vérification.
DROP TABLE IF EXISTS `types_piece`;
CREATE TABLE `types_piece` (
  `code` VARCHAR(20) NOT NULL COMMENT '« cni_recto », « cni_verso », « permis », « diplome », « portrait », « atelier »',
  `libelle` VARCHAR(60) NOT NULL COMMENT 'Libellé affiché',
  `obligatoire` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Bloque l''envoi du dossier si absente',
  `ordre` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre (sens des facettes de l''écrou)',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Les pièces du dossier de vérification.';

-- Référentiels · Étiquettes rapides proposées avec la note.
DROP TABLE IF EXISTS `etiquettes_avis`;
CREATE TABLE `etiquettes_avis` (
  `id` TINYINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `code` VARCHAR(30) NOT NULL COMMENT '« rapide », « prix_respecte »…',
  `libelle` VARCHAR(50) NOT NULL COMMENT 'Libellé affiché',
  `polarite` ENUM('positive','negative') NOT NULL COMMENT 'Proposée selon la note donnée',
  `ordre` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordre d''affichage',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_etiquettes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Étiquettes rapides proposées avec la note.';

-- Référentiels · Tous les seuils des règles métier (rayons, délais, durées de conservation). Modifiables sans redéployer.
DROP TABLE IF EXISTS `parametres`;
CREATE TABLE `parametres` (
  `cle` VARCHAR(60) NOT NULL COMMENT '« appel.sonnerie_s »',
  `valeur` VARCHAR(255) NOT NULL COMMENT 'Valeur (texte)',
  `type` ENUM('int','decimal','texte','liste') NOT NULL DEFAULT 'int' COMMENT 'Pour la conversion côté PHP',
  `description` VARCHAR(255) NOT NULL COMMENT 'Règle métier concernée',
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Dernière modification',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tous les seuils des règles métier (rayons, délais, durées de conservation). Modifiables sans redéployer.';

-- Administration et système · Comptes de l'équipe allomécano. Un seul rôle en V1 ; chaque action est journalisée.
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `prenom` VARCHAR(60) NOT NULL COMMENT 'Prénom (sert au filigrane « A. Bamba »)',
  `nom` VARCHAR(60) NOT NULL COMMENT 'Nom',
  `email` VARCHAR(190) NOT NULL COMMENT 'Identifiant de connexion',
  `mot_de_passe_hash` VARCHAR(255) COMMENT 'password_hash() ; vide si connexion Google seulement',
  `google_sub` VARCHAR(64) COMMENT 'Identifiant Google (connexion Google)',
  `actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Compte utilisable',
  `derniere_connexion_le` DATETIME COMMENT 'Dernière connexion réussie',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Création',
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Dernière modification',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  UNIQUE KEY `uq_admins_google` (`google_sub`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comptes de l''équipe allomécano. Un seul rôle en V1 ; chaque action est journalisée.';

-- Mécaniciens et dossier · Le mécanicien : compte, profil public, statut du dossier et disponibilité actuelle (colonnes dispo_*), plus quelques valeurs calculées pour aller vite à la recherche.
DROP TABLE IF EXISTS `mecaniciens`;
CREATE TABLE `mecaniciens` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant interne',
  `public_id` CHAR(26) NOT NULL COMMENT 'ULID utilisé dans les URL et l''API (jamais l''id)',
  `slug` VARCHAR(90) COMMENT '« koffi-yao-riviera-3 », créé à la validation',
  `prenom` VARCHAR(60) NOT NULL COMMENT 'Prénom',
  `nom` VARCHAR(60) NOT NULL COMMENT 'Nom',
  `date_naissance` DATE COMMENT 'Déclarée à l''inscription, comparée à la pièce d''identité (A2 · concordances)',
  `telephone` VARCHAR(20) NOT NULL COMMENT 'Identifiant de connexion, format E.164 (+2250707123456)',
  `whatsapp` VARCHAR(20) COMMENT 'Numéro WhatsApp s''il est différent',
  `email` VARCHAR(190) COMMENT 'Facultatif, sert à réinitialiser le mot de passe',
  `email_verifie_le` DATETIME COMMENT 'Lien de vérification cliqué',
  `mot_de_passe_hash` VARCHAR(255) COMMENT 'password_hash() ; vide si Google seulement',
  `google_sub` VARCHAR(64) COMMENT 'Connexion Google',
  `metier` VARCHAR(80) NOT NULL COMMENT '« Électricien auto »',
  `annees_experience` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Années de métier',
  `presentation` VARCHAR(500) COMMENT 'Texte libre de la fiche',
  `atelier_adresse` VARCHAR(160) COMMENT '« Derrière le marché de Riviera 3 »',
  `atelier_quartier_id` SMALLINT UNSIGNED COMMENT 'Quartier de l''atelier (affichage seulement)',
  `atelier_lat` DECIMAL(9,6) COMMENT 'Position de l''atelier (affichage seulement)',
  `atelier_lon` DECIMAL(9,6),
  `rayon_km` TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Rayon d''intervention : 2, 5, 10 ou 20 km',
  `tarif_diagnostic_fcfa` INT UNSIGNED COMMENT '« Diagnostic dès » (indicatif)',
  `tarif_deplacement_fcfa` INT UNSIGNED COMMENT 'Déplacement (indicatif)',
  `statut_dossier` ENUM('brouillon','en_attente','a_reprendre','valide','refuse','suspendu') NOT NULL DEFAULT 'brouillon' COMMENT 'Voir la machine à états du dossier',
  `dossier_envoye_le` DATETIME COMMENT 'Dernier envoi pour vérification',
  `identite_verifiee_le` DATE COMMENT 'Date affichée : « Visage vérifié le 12 mars 2026 »',
  `valide_par_admin_id` SMALLINT UNSIGNED COMMENT 'Admin qui a validé',
  `motif_refus` VARCHAR(500) COMMENT 'Motif communiqué en cas de refus',
  `cgu_version` VARCHAR(10) COMMENT 'Version des CGU acceptée',
  `cgu_acceptees_le` DATETIME COMMENT 'Consentement',
  `etat` ENUM('indisponible','disponible','en_intervention') NOT NULL DEFAULT 'indisponible' COMMENT 'Disponibilité actuelle',
  `etat_depuis` DATETIME COMMENT 'Début de l''état actuel',
  `dispo_debut` DATETIME COMMENT 'Début de la session de disponibilité (règle des 12 h)',
  `dispo_lat` DECIMAL(9,6) COMMENT 'Position de disponibilité : base de la recherche',
  `dispo_lon` DECIMAL(9,6),
  `dispo_precision_m` SMALLINT UNSIGNED COMMENT 'Précision GPS de cette position',
  `dispo_quartier_id` SMALLINT UNSIGNED COMMENT 'Quartier le plus proche (« Riviera 3 »)',
  `position_maj_le` DATETIME COMMENT 'Dernière mise à jour de la position',
  `vu_le` DATETIME COMMENT 'Dernière fois que l''app mécano était ouverte (appel intégré possible si < 24 h)',
  `mot_de_passe_modifie_le` DATETIME COMMENT 'Dernier changement du mot de passe (M4) ; vide : depuis la création du compte',
  `appels_actives_le` DATETIME COMMENT 'Fin de « Activer les appels » (M1f) : installation, notifications, essai de sonnerie',
  `note_moyenne` DECIMAL(2,1) COMMENT 'Calculée depuis avis publiés',
  `nb_avis` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Calculé',
  `taux_joignabilite` TINYINT UNSIGNED COMMENT '% d''appels intégrés décrochés sur 30 jours (calculé)',
  `appels_manques_suite` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Appels intégrés manqués d''affilée (règle injoignable)',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Création du compte',
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Dernière modification',
  `supprime_le` DATETIME COMMENT 'Suppression (données personnelles anonymisées)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meca_public` (`public_id`),
  UNIQUE KEY `uq_meca_slug` (`slug`),
  UNIQUE KEY `uq_meca_tel` (`telephone`),
  UNIQUE KEY `uq_meca_email` (`email`),
  UNIQUE KEY `uq_meca_google` (`google_sub`),
  KEY `idx_meca_recherche` (`statut_dossier`, `etat`, `dispo_lat`, `dispo_lon`),
  KEY `idx_meca_statut` (`statut_dossier`, `dossier_envoye_le`),
  CONSTRAINT `fk_mecaniciens_atelier_quartier_id` FOREIGN KEY (`atelier_quartier_id`) REFERENCES `quartiers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mecaniciens_dispo_quartier_id` FOREIGN KEY (`dispo_quartier_id`) REFERENCES `quartiers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mecaniciens_valide_par_admin_id` FOREIGN KEY (`valide_par_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_meca_rayon` CHECK (rayon_km IN (2,5,10,20)),
  CONSTRAINT `chk_meca_auth` CHECK (mot_de_passe_hash IS NOT NULL OR google_sub IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Le mécanicien : compte, profil public, statut du dossier et disponibilité actuelle (colonnes dispo_*), plus quelques valeurs calculées pour aller vite à la recherche.';

-- Mécaniciens et dossier · Spécialités d'un mécano (plusieurs).
DROP TABLE IF EXISTS `mecanicien_specialites`;
CREATE TABLE `mecanicien_specialites` (
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `specialite_id` SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`mecanicien_id`, `specialite_id`),
  CONSTRAINT `fk_mecanicien_specialites_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mecanicien_specialites_specialite_id` FOREIGN KEY (`specialite_id`) REFERENCES `specialites` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Spécialités d''un mécano (plusieurs).';

-- Mécaniciens et dossier · Types de véhicules qu'il répare (filtre strict).
DROP TABLE IF EXISTS `mecanicien_vehicules`;
CREATE TABLE `mecanicien_vehicules` (
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `type_vehicule` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`mecanicien_id`, `type_vehicule`),
  CONSTRAINT `fk_mecanicien_vehicules_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mecanicien_vehicules_type_vehicule` FOREIGN KEY (`type_vehicule`) REFERENCES `types_vehicule` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types de véhicules qu''il répare (filtre strict).';

-- Mécaniciens et dossier · Chaque fichier envoyé pour la vérification. Un nouvel envoi remplace l'ancien sans l'effacer (historique).
DROP TABLE IF EXISTS `pieces_dossier`;
CREATE TABLE `pieces_dossier` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Identifiant',
  `mecanicien_id` INT UNSIGNED NOT NULL COMMENT 'Propriétaire',
  `type_piece` VARCHAR(20) NOT NULL COMMENT 'Type de pièce',
  `fichier` VARCHAR(255) COMMENT 'Chemin relatif HORS du dossier public ; vidé à la suppression du fichier',
  `mime` VARCHAR(40) NOT NULL COMMENT 'image/jpeg, image/webp, application/pdf',
  `taille_octets` INT UNSIGNED NOT NULL COMMENT 'Après recompression',
  `empreinte_sha256` CHAR(64) NOT NULL COMMENT 'Détecte les doublons et les altérations',
  `largeur` SMALLINT UNSIGNED COMMENT 'Pixels',
  `hauteur` SMALLINT UNSIGNED COMMENT 'Pixels',
  `statut` ENUM('envoyee','validee','a_reprendre','remplacee') NOT NULL DEFAULT 'envoyee' COMMENT 'Décision sur cette pièce',
  `motif` VARCHAR(300) COMMENT 'Motif si à reprendre',
  `examinee_par_admin_id` SMALLINT UNSIGNED COMMENT 'Examinateur',
  `examinee_le` DATETIME COMMENT 'Date de décision',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Envoi',
  `fichier_supprime_le` DATETIME COMMENT 'Effacement du fichier (refus + 6 mois, suppression du compte)',
  PRIMARY KEY (`id`),
  KEY `idx_pieces_meca` (`mecanicien_id`, `type_piece`, `statut`),
  CONSTRAINT `fk_pieces_dossier_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pieces_dossier_type_piece` FOREIGN KEY (`type_piece`) REFERENCES `types_piece` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pieces_dossier_examinee_par_admin_id` FOREIGN KEY (`examinee_par_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chaque fichier envoyé pour la vérification. Un nouvel envoi remplace l''ancien sans l''effacer (historique).';

-- Mécaniciens et dossier · Photos PUBLIQUES du mécano (portrait, atelier), générées depuis la pièce envoyée : versions WebP allégées, sans EXIF, servies depuis public/medias. Les pièces d'identité ne sont jamais exposées.
DROP TABLE IF EXISTS `photos`;
CREATE TABLE `photos` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `type` ENUM('portrait','atelier') NOT NULL COMMENT 'Portrait (fiche, repère, listes) ou atelier (fiche)',
  `piece_id` INT UNSIGNED COMMENT 'Pièce d''origine (portrait ou atelier validé)',
  `dossier` VARCHAR(120) NOT NULL COMMENT 'Chemin relatif dans public/medias (« m/01J…/portrait-3 »)',
  `largeurs` VARCHAR(40) NOT NULL DEFAULT '160,360,720' COMMENT 'Largeurs générées en WebP',
  `ratio` DECIMAL(4,3) NOT NULL COMMENT 'Largeur / hauteur (réserve la place, pas de saut de page)',
  `couleur_dominante` CHAR(7) NOT NULL COMMENT 'Fond affiché pendant le chargement (#6B5A48)',
  `focal_x` DECIMAL(3,2) NOT NULL DEFAULT 0.50 COMMENT 'Point de cadrage horizontal (object-position)',
  `focal_y` DECIMAL(3,2) NOT NULL DEFAULT 0.30 COMMENT 'Point de cadrage vertical',
  `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Une seule photo active par type ; l''ancienne passe à 0',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_photos_meca` (`mecanicien_id`, `type`, `active`),
  CONSTRAINT `fk_photos_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_photos_piece_id` FOREIGN KEY (`piece_id`) REFERENCES `pieces_dossier` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Photos PUBLIQUES du mécano (portrait, atelier), générées depuis la pièce envoyée : versions WebP allégées, sans EXIF, servies depuis public/medias. Les pièces d''identité ne sont jamais exposées.';

-- Mécaniciens et dossier · Chaque changement de statut du dossier, avec son auteur.
DROP TABLE IF EXISTS `historique_dossier`;
CREATE TABLE `historique_dossier` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `de_statut` VARCHAR(20) COMMENT 'Statut avant',
  `vers_statut` VARCHAR(20) NOT NULL COMMENT 'Statut après',
  `admin_id` SMALLINT UNSIGNED COMMENT 'Vide si action du mécano ou du système',
  `motif` VARCHAR(500),
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_histdos_meca` (`mecanicien_id`, `cree_le`),
  CONSTRAINT `fk_historique_dossier_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_historique_dossier_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chaque changement de statut du dossier, avec son auteur.';

-- Mécaniciens et dossier · Périodes de disponibilité. Sert aux statistiques et à expliquer un passage automatique.
DROP TABLE IF EXISTS `historique_disponibilite`;
CREATE TABLE `historique_disponibilite` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `etat` ENUM('indisponible','disponible','en_intervention') NOT NULL COMMENT 'État de la période',
  `raison` ENUM('manuel','auto_12h','auto_injoignable','auto_fin_intervention','intervention','suspension','position_perimee') NOT NULL COMMENT 'Pourquoi on est entré dans cet état',
  `quartier_id` SMALLINT UNSIGNED COMMENT 'Quartier au début de la période',
  `debut` DATETIME NOT NULL,
  `fin` DATETIME COMMENT 'Vide tant que la période dure',
  PRIMARY KEY (`id`),
  KEY `idx_histdispo_meca` (`mecanicien_id`, `debut`),
  CONSTRAINT `fk_historique_disponibilite_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_historique_disponibilite_quartier_id` FOREIGN KEY (`quartier_id`) REFERENCES `quartiers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Périodes de disponibilité. Sert aux statistiques et à expliquer un passage automatique.';

-- Mécaniciens et dossier · Suspensions décidées par un admin.
DROP TABLE IF EXISTS `suspensions`;
CREATE TABLE `suspensions` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `admin_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Qui suspend',
  `motif` VARCHAR(500) NOT NULL COMMENT 'Communiqué au mécano',
  `debut` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fin_prevue` DATETIME COMMENT '24 h, 7 jours, ou vide = jusqu''à nouvel ordre',
  `levee_le` DATETIME,
  `levee_par_admin_id` SMALLINT UNSIGNED,
  `signalement_id` INT UNSIGNED COMMENT 'Signalement à l''origine, s''il y en a un',
  PRIMARY KEY (`id`),
  KEY `idx_susp_meca` (`mecanicien_id`, `debut`),
  CONSTRAINT `fk_suspensions_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_suspensions_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_suspensions_levee_par_admin_id` FOREIGN KEY (`levee_par_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suspensions décidées par un admin.';

-- Mécaniciens et dossier · Téléphones du mécano capables de recevoir un appel intégré (Web Push).
DROP TABLE IF EXISTS `abonnements_push`;
CREATE TABLE `abonnements_push` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `endpoint` VARCHAR(600) NOT NULL COMMENT 'URL du service de push du navigateur',
  `endpoint_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 de l''endpoint (index unique)',
  `cle_p256dh` VARCHAR(120) NOT NULL COMMENT 'Clé publique du navigateur',
  `cle_auth` VARCHAR(40) NOT NULL COMMENT 'Secret d''authentification',
  `appareil` VARCHAR(120) COMMENT '« Chrome · Android »',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dernier_succes_le` DATETIME COMMENT 'Dernier push accepté',
  `echecs_suite` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Révoqué après 3 échecs ou un 404/410',
  `revoque_le` DATETIME,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_endpoint` (`endpoint_hash`),
  KEY `idx_push_meca` (`mecanicien_id`, `revoque_le`),
  CONSTRAINT `fk_abonnements_push_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Téléphones du mécano capables de recevoir un appel intégré (Web Push).';

-- Clients et recherches · Le client, sans compte : un jeton aléatoire gardé dans son navigateur. Prénom et téléphone facultatifs, jamais publics.
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `jeton_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 du jeton du navigateur (le jeton lui-même n''est jamais stocké)',
  `prenom` VARCHAR(40) COMMENT '« Pour que le mécano vous reconnaisse »',
  `telephone` VARCHAR(20) COMMENT 'Facultatif, jamais affiché publiquement',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vu_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Dernière visite',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clients_jeton` (`jeton_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Le client, sans compte : un jeton aléatoire gardé dans son navigateur. Prénom et téléphone facultatifs, jamais publics.';

-- Clients et recherches · Une recherche de mécano. La position exacte est effacée au bout de 24 h, la ligne au bout de 90 jours.
DROP TABLE IF EXISTS `recherches`;
CREATE TABLE `recherches` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `public_id` CHAR(26) NOT NULL COMMENT 'ULID (URL de la page résultats)',
  `client_id` BIGINT UNSIGNED NOT NULL,
  `type_vehicule` VARCHAR(10) NOT NULL COMMENT 'Filtre strict',
  `panne_id` SMALLINT UNSIGNED NOT NULL COMMENT 'Fait remonter les spécialistes',
  `precisions` VARCHAR(300) COMMENT '« ça fait clic quand je tourne la clé »',
  `source_position` ENUM('gps','quartier','carte') NOT NULL COMMENT 'GPS, quartier choisi, ou point déplacé sur la carte',
  `quartier_id` SMALLINT UNSIGNED COMMENT 'Quartier choisi ou le plus proche',
  `lat` DECIMAL(9,6) COMMENT 'Effacée après 24 h',
  `lon` DECIMAL(9,6) COMMENT 'Effacée après 24 h',
  `precision_m` INT UNSIGNED COMMENT 'Précision GPS réelle',
  `repere` VARCHAR(160) COMMENT '« devant la pharmacie des Jardins »',
  `rayon_km` TINYINT UNSIGNED NOT NULL COMMENT 'Rayon réellement utilisé (2, 5 ou 10)',
  `nb_resultats` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `position_effacee_le` DATETIME,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rech_public` (`public_id`),
  KEY `idx_rech_client` (`client_id`, `cree_le`),
  KEY `idx_rech_date` (`cree_le`),
  CONSTRAINT `fk_recherches_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_recherches_type_vehicule` FOREIGN KEY (`type_vehicule`) REFERENCES `types_vehicule` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_recherches_panne_id` FOREIGN KEY (`panne_id`) REFERENCES `pannes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_recherches_quartier_id` FOREIGN KEY (`quartier_id`) REFERENCES `quartiers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Une recherche de mécano. La position exacte est effacée au bout de 24 h, la ligne au bout de 90 jours.';

-- Clients et recherches · La liste ordonnée montrée au client. Sert à proposer « le suivant » sans relancer la recherche.
DROP TABLE IF EXISTS `resultats_recherche`;
CREATE TABLE `resultats_recherche` (
  `recherche_id` BIGINT UNSIGNED NOT NULL,
  `rang` TINYINT UNSIGNED NOT NULL COMMENT '1 = le plus proche après pondération',
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `distance_m` INT UNSIGNED NOT NULL COMMENT 'À vol d''oiseau (Haversine)',
  `specialiste` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'A une spécialité liée à la panne',
  `autre_rive` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Le mécano est de l''autre côté de la lagune (RG-R9)',
  PRIMARY KEY (`recherche_id`, `rang`),
  UNIQUE KEY `uq_res_meca` (`recherche_id`, `mecanicien_id`),
  KEY `idx_res_meca` (`mecanicien_id`),
  CONSTRAINT `fk_resultats_recherche_recherche_id` FOREIGN KEY (`recherche_id`) REFERENCES `recherches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_resultats_recherche_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='La liste ordonnée montrée au client. Sert à proposer « le suivant » sans relancer la recherche.';

-- Clients et recherches · Comptage anonyme des recherches sans résultat, par zone d'environ 1 km (savoir où recruter). Aucun lien vers le client. 90 jours.
DROP TABLE IF EXISTS `zones_sans_resultat`;
CREATE TABLE `zones_sans_resultat` (
  `zone_id` VARCHAR(12) NOT NULL COMMENT 'Geohash de précision 6 (≈ 1,2 × 0,6 km), calculable en PHP sans librairie',
  `jour` DATE NOT NULL,
  `type_vehicule` VARCHAR(10) NOT NULL,
  `nb` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`zone_id`, `jour`, `type_vehicule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comptage anonyme des recherches sans résultat, par zone d''environ 1 km (savoir où recruter). Aucun lien vers le client. 90 jours.';

-- Mécaniciens et dossier · Consultations de la fiche, pour le tableau de bord du mécano (M2). Anonyme : un client compte une fois par jour.
DROP TABLE IF EXISTS `vues_fiche`;
CREATE TABLE `vues_fiche` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `client_id` BIGINT UNSIGNED COMMENT 'Vide si le client est purgé',
  `jour` DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vue_jour` (`mecanicien_id`, `client_id`, `jour`),
  KEY `idx_vue_meca_jour` (`mecanicien_id`, `jour`),
  CONSTRAINT `fk_vues_fiche_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vues_fiche_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Une ligne par client et par jour : le compteur « ont vu votre fiche » de M2. Effacé au bout de 90 jours.';

-- Mises en relation et appels · Une tentative de contact client → mécano, par l'un des 3 canaux. C'est la table centrale : appel, issue, reproposition, droit à l'avis.
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `public_id` CHAR(26) NOT NULL COMMENT 'ULID (suivi en direct côté client et côté mécano)',
  `recherche_id` BIGINT UNSIGNED COMMENT 'Vidé quand la recherche est purgée (90 j)',
  `client_id` BIGINT UNSIGNED COMMENT 'Vidé si le client est purgé',
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `canal` ENUM('app','tel','whatsapp') NOT NULL COMMENT 'Appel intégré, appel direct, WhatsApp',
  `rang` TINYINT UNSIGNED COMMENT 'Rang du mécano dans les résultats',
  `contact_precedent_id` BIGINT UNSIGNED COMMENT 'Contact raté qui a mené à cette reproposition',
  `statut` ENUM('initie','sonne','decroche','termine','manque','occupe','echoue','annule') NOT NULL DEFAULT 'initie' COMMENT 'tel/WhatsApp restent « initie » ; voir la machine à états de l''appel',
  `cree_le` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT 'Clic sur le bouton',
  `sonne_le` DATETIME(3) COMMENT 'Push envoyé au mécano',
  `decroche_le` DATETIME(3),
  `termine_le` DATETIME(3),
  `duree_s` SMALLINT UNSIGNED COMMENT 'Durée de conversation (appel intégré)',
  `fin_par` ENUM('client','mecanicien','delai','reseau','micro_refuse') COMMENT 'Cause de fin',
  `absence_s` SMALLINT UNSIGNED COMMENT 'tel/WhatsApp : temps passé hors d''allomécano',
  `resume` VARCHAR(120) NOT NULL COMMENT '« Voiture · Batterie », gardé',
  `quartier_libelle` VARCHAR(80) COMMENT '« Riviera 2 », gardé',
  `position_partagee` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Interrupteur « Lien de ma position »',
  `distance_m` INT UNSIGNED COMMENT 'Distance à vol d''oiseau entre le client et le mécano au moment du contact : gardée quand la position est effacée (RG-P1)',
  `lat` DECIMAL(9,6) COMMENT 'Envoyée au mécano contacté, effacée après 24 h',
  `lon` DECIMAL(9,6) COMMENT 'Effacée après 24 h',
  `repere` VARCHAR(160) COMMENT 'Effacé après 24 h',
  `issue_client` ENUM('arrive','pas_entendus','pas_reponse') COMMENT 'Réponse du client',
  `raison_desaccord` ENUM('prix','delai','specialite','autre') COMMENT 'Si « pas entendus » (facultatif, jamais public)',
  `issue_client_le` DATETIME,
  `issue_mecano` ENUM('j_y_vais','pas_pour_moi') COMMENT 'Réponse du mécano',
  `issue_mecano_le` DATETIME,
  `intervention_fin_le` DATETIME COMMENT 'Fin de l''état « en intervention »',
  `intervention_fin_par` ENUM('mecanicien','client','auto'),
  `donnees_effacees_le` DATETIME COMMENT 'Position et repère effacés',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_public` (`public_id`),
  KEY `idx_contact_meca` (`mecanicien_id`, `cree_le`),
  KEY `idx_contact_client` (`client_id`, `cree_le`),
  KEY `idx_contact_actif` (`statut`, `mecanicien_id`),
  KEY `idx_contact_purge` (`donnees_effacees_le`, `cree_le`),
  CONSTRAINT `fk_contacts_recherche_id` FOREIGN KEY (`recherche_id`) REFERENCES `recherches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contacts_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contacts_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_contacts_contact_precedent_id` FOREIGN KEY (`contact_precedent_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Une tentative de contact client → mécano, par l''un des 3 canaux. C''est la table centrale : appel, issue, reproposition, droit à l''avis.';

-- Mises en relation et appels · Messages techniques de l'appel intégré (offre, réponse, candidats réseau, raccrocher), lus par interrogation régulière. Effacés 1 h après la fin de l'appel.
DROP TABLE IF EXISTS `signalisation`;
CREATE TABLE `signalisation` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT 'Ordre des messages',
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `emetteur` ENUM('client','mecanicien') NOT NULL,
  `type` ENUM('offre','reponse','candidat','raccroche','refus','sonne') NOT NULL,
  `charge` MEDIUMTEXT COMMENT 'JSON (SDP ou candidat ICE)',
  `cree_le` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_sig_contact` (`contact_id`, `id`),
  CONSTRAINT `fk_signalisation_contact_id` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Messages techniques de l''appel intégré (offre, réponse, candidats réseau, raccrocher), lus par interrogation régulière. Effacés 1 h après la fin de l''appel.';

-- Avis et signalements · Avis d'un client, rattaché à UN contact réel (anti-faux avis).
DROP TABLE IF EXISTS `avis`;
CREATE TABLE `avis` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `contact_id` BIGINT UNSIGNED NOT NULL COMMENT 'Un avis par contact',
  `mecanicien_id` INT UNSIGNED NOT NULL COMMENT 'Recopié pour les requêtes de la fiche',
  `client_id` BIGINT UNSIGNED COMMENT 'Vidé à l''anonymisation',
  `note` TINYINT UNSIGNED NOT NULL COMMENT '1 à 5',
  `commentaire` VARCHAR(600) COMMENT 'Facultatif',
  `prenom_affiche` VARCHAR(40) COMMENT '« Aminata » ; vide = « Un client »',
  `panne_libelle` VARCHAR(60) NOT NULL COMMENT '« Batterie », affiché sous l''avis',
  `statut` ENUM('publie','masque') NOT NULL DEFAULT 'publie',
  `masque_par_admin_id` SMALLINT UNSIGNED,
  `motif_masquage` VARCHAR(300),
  `reponse_mecano` VARCHAR(600) COMMENT 'Réponse publique du mécano (une seule, modifiable)',
  `reponse_le` DATETIME COMMENT 'Date de la réponse',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Modifiable 24 h',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avis_contact` (`contact_id`),
  KEY `idx_avis_fiche` (`mecanicien_id`, `statut`, `cree_le`),
  CONSTRAINT `fk_avis_contact_id` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_avis_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_avis_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_avis_masque_par_admin_id` FOREIGN KEY (`masque_par_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_avis_note` CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Avis d''un client, rattaché à UN contact réel (anti-faux avis).';

-- Avis et signalements · Étiquettes choisies avec un avis.
DROP TABLE IF EXISTS `avis_etiquettes`;
CREATE TABLE `avis_etiquettes` (
  `avis_id` INT UNSIGNED NOT NULL,
  `etiquette_id` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`avis_id`, `etiquette_id`),
  CONSTRAINT `fk_avis_etiquettes_avis_id` FOREIGN KEY (`avis_id`) REFERENCES `avis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_avis_etiquettes_etiquette_id` FOREIGN KEY (`etiquette_id`) REFERENCES `etiquettes_avis` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Étiquettes choisies avec un avis.';

-- Avis et signalements · Signalement d'un mécano par un client. Le mécano ne sait jamais qui l'a signalé.
DROP TABLE IF EXISTS `signalements`;
CREATE TABLE `signalements` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `public_id` CHAR(26) NOT NULL,
  `mecanicien_id` INT UNSIGNED NOT NULL,
  `contact_id` BIGINT UNSIGNED COMMENT 'Contact concerné s''il existe',
  `client_id` BIGINT UNSIGNED,
  `motif` ENUM('identite','comportement','prix_abusif','pas_venu','securite','autre') NOT NULL COMMENT '« identite » rouvre la comparaison photo ↔ pièce',
  `description` VARCHAR(1000),
  `photo_fichier` VARCHAR(255) COMMENT 'Hors du dossier public, EXIF supprimé, 300 Ko max',
  `statut` ENUM('nouveau','en_cours','sans_suite','sanction') NOT NULL DEFAULT 'nouveau',
  `traite_par_admin_id` SMALLINT UNSIGNED,
  `traite_le` DATETIME,
  `decision` VARCHAR(500),
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sig_public` (`public_id`),
  KEY `idx_sig_statut` (`statut`, `cree_le`),
  KEY `idx_sig_meca` (`mecanicien_id`),
  CONSTRAINT `fk_signalements_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_signalements_contact_id` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_signalements_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_signalements_traite_par_admin_id` FOREIGN KEY (`traite_par_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Signalement d''un mécano par un client. Le mécano ne sait jamais qui l''a signalé.';

-- Administration et système · Liens de réinitialisation du mot de passe (mécano ou admin), valables 30 min, à usage unique.
DROP TABLE IF EXISTS `jetons_reinitialisation`;
CREATE TABLE `jetons_reinitialisation` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED,
  `admin_id` SMALLINT UNSIGNED,
  `jeton_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 du jeton envoyé par e-mail',
  `expire_le` DATETIME NOT NULL COMMENT '+30 min',
  `utilise_le` DATETIME,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jeton` (`jeton_hash`),
  CONSTRAINT `fk_jetons_reinitialisation_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jetons_reinitialisation_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liens de réinitialisation du mot de passe (mécano ou admin), valables 30 min, à usage unique.';

-- Administration et système · Pour bloquer 15 min après 5 échecs.
DROP TABLE IF EXISTS `tentatives_connexion`;
CREATE TABLE `tentatives_connexion` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `identifiant` VARCHAR(190) NOT NULL COMMENT 'Téléphone ou e-mail normalisé',
  `ip` VARBINARY(16) NOT NULL COMMENT 'inet_pton()',
  `reussie` TINYINT(1) NOT NULL,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tent_id` (`identifiant`, `cree_le`),
  KEY `idx_tent_ip` (`ip`, `cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pour bloquer 15 min après 5 échecs.';

-- Administration et système · File d'envoi des notifications (push et e-mail). Le SMS arrive en V2 avec un nouveau canal.
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `mecanicien_id` INT UNSIGNED,
  `admin_id` SMALLINT UNSIGNED,
  `type` ENUM('appel_entrant','dossier_recu','dossier_valide','dossier_a_reprendre','dossier_refuse','essai_sonnerie','indispo_auto','fin_intervention_auto','nouvel_avis','suspension','reinitialisation','nouveau_dossier','nouveau_signalement','nouveau_message') NOT NULL,
  `canal` ENUM('push','email') NOT NULL,
  `statut` ENUM('en_attente','envoyee','echec') NOT NULL DEFAULT 'en_attente',
  `charge` JSON COMMENT 'Titre, texte, lien',
  `tentatives` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `envoyee_le` DATETIME,
  `erreur` VARCHAR(255),
  PRIMARY KEY (`id`),
  KEY `idx_notif_file` (`statut`, `cree_le`),
  CONSTRAINT `fk_notifications_mecanicien_id` FOREIGN KEY (`mecanicien_id`) REFERENCES `mecaniciens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='File d''envoi des notifications (push et e-mail). Le SMS arrive en V2 avec un nouveau canal.';

-- Administration et système · Toutes les actions des admins, y compris chaque consultation de pièce et chaque numéro de client affiché. On n'y écrit qu'en ajout.
DROP TABLE IF EXISTS `journal_admin`;
CREATE TABLE `journal_admin` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  `admin_id` SMALLINT UNSIGNED NOT NULL,
  `action` VARCHAR(40) NOT NULL COMMENT '« consulter_piece », « valider_dossier », « afficher_numero »…',
  `cible_type` ENUM('mecanicien','piece','avis','signalement','client','contact','admin','message') NOT NULL,
  `cible_id` BIGINT UNSIGNED NOT NULL,
  `details` JSON COMMENT 'Motif, ancien et nouveau statut…',
  `ip` VARBINARY(16),
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_journal_cible` (`cible_type`, `cible_id`),
  KEY `idx_journal_admin` (`admin_id`, `cree_le`),
  CONSTRAINT `fk_journal_admin_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Toutes les actions des admins, y compris chaque consultation de pièce et chaque numéro de client affiché. On n''y écrit qu''en ajout.';

-- Administration et système · Messages du formulaire Contact (X4), traités par un admin.
DROP TABLE IF EXISTS `messages_contact`;
CREATE TABLE `messages_contact` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `profil` ENUM('client','mecano','partenaire','presse','autre') NOT NULL COMMENT 'Qui écrit',
  `nom` VARCHAR(80) NOT NULL,
  `telephone` VARCHAR(20) COMMENT 'Au moins un moyen de réponse',
  `email` VARCHAR(190),
  `message` VARCHAR(2000) NOT NULL,
  `statut` ENUM('nouveau','traite') NOT NULL DEFAULT 'nouveau',
  `traite_par_admin_id` SMALLINT UNSIGNED,
  `traite_le` DATETIME,
  `ip` VARBINARY(16) COMMENT 'Anti-abus',
  `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_statut` (`statut`, `cree_le`),
  CONSTRAINT `fk_messages_contact_traite_par_admin_id` FOREIGN KEY (`traite_par_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_msg_contact` CHECK (telephone IS NOT NULL OR email IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Messages du formulaire Contact (X4), traités par un admin.';

-- Administration et système · Exécutions des tâches planifiées (cron), pour vérifier qu'elles tournent.
DROP TABLE IF EXISTS `journal_taches`;
CREATE TABLE `journal_taches` (
  `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
  `tache` VARCHAR(60) NOT NULL COMMENT '« purge_positions », « dispo_12h »…',
  `debut` DATETIME NOT NULL,
  `fin` DATETIME,
  `ok` TINYINT(1),
  `lignes` INT UNSIGNED COMMENT 'Lignes traitées',
  `message` VARCHAR(255),
  PRIMARY KEY (`id`),
  KEY `idx_taches` (`tache`, `debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Exécutions des tâches planifiées (cron), pour vérifier qu''elles tournent.';

ALTER TABLE `suspensions` ADD CONSTRAINT `fk_suspensions_signalement_id` FOREIGN KEY (`signalement_id`) REFERENCES `signalements` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Données de départ (référentiels et paramètres)
-- Coordonnées des communes : centres approximatifs, À VÉRIFIER.
-- =====================================================================

INSERT INTO communes (nom,slug,lat,lon,rive,ouverte,ordre) VALUES
('Abobo','abobo',5.419,-4.02,'nord',1,1),
('Adjamé','adjame',5.36,-4.023,'nord',1,2),
('Anyama','anyama',5.495,-4.052,'nord',1,3),
('Attécoubé','attecoube',5.333,-4.04,'nord',1,4),
('Bingerville','bingerville',5.355,-3.885,'nord',1,5),
('Cocody','cocody',5.36,-3.97,'nord',1,6),
('Koumassi','koumassi',5.297,-3.95,'sud',1,7),
('Marcory','marcory',5.303,-3.98,'sud',1,8),
('Plateau','plateau',5.323,-4.019,'nord',1,9),
('Port-Bouët','port-bouet',5.256,-3.93,'sud',1,10),
('Songon','songon',5.32,-4.26,'nord',0,11),
('Treichville','treichville',5.293,-4.005,'sud',1,12),
('Yopougon','yopougon',5.345,-4.09,'nord',1,13);

INSERT INTO quartiers (commune_id,nom,slug,alias,lat,lon) VALUES
((SELECT id FROM communes WHERE slug='abobo'),'Abobo Gare','abobo-gare','Gare d''Abobo',5.4160,-4.0180),
((SELECT id FROM communes WHERE slug='abobo'),'PK 18','abobo-pk-18','PK18',5.4560,-4.0450),
((SELECT id FROM communes WHERE slug='abobo'),'Avocatier','abobo-avocatier',NULL,5.4300,-4.0350),
((SELECT id FROM communes WHERE slug='abobo'),'Sagbé','abobo-sagbe','Sagbe',5.4330,-4.0480),
((SELECT id FROM communes WHERE slug='abobo'),'Anonkoua-Kouté','abobo-anonkoua-koute','Anonkoua',5.4480,-4.0050),
((SELECT id FROM communes WHERE slug='abobo'),'Akeikoi','abobo-akeikoi','Akéikoi',5.4580,-4.0200),
((SELECT id FROM communes WHERE slug='abobo'),'Abobo Baoulé','abobo-baoule','Baoulé',5.4210,-3.9990),
((SELECT id FROM communes WHERE slug='abobo'),'N''Dotré','abobo-ndotre','Ndotre, N''Dotre',5.4600,-4.0650),
((SELECT id FROM communes WHERE slug='abobo'),'Samaké','abobo-samake','Samake',5.4060,-4.0300),
((SELECT id FROM communes WHERE slug='abobo'),'Belleville','abobo-belleville',NULL,5.4100,-4.0050),
((SELECT id FROM communes WHERE slug='adjame'),'Adjamé Liberté','adjame-liberte','Liberté',5.3580,-4.0180),
((SELECT id FROM communes WHERE slug='adjame'),'Williamsville','adjame-williamsville','Williams',5.3720,-4.0220),
((SELECT id FROM communes WHERE slug='adjame'),'Bracodi','adjame-bracodi',NULL,5.3530,-4.0230),
((SELECT id FROM communes WHERE slug='adjame'),'220 Logements','adjame-220-logements','220 logts',5.3650,-4.0130),
((SELECT id FROM communes WHERE slug='adjame'),'Marie-Thérèse','adjame-marie-therese','Marie Therese',5.3600,-4.0300),
((SELECT id FROM communes WHERE slug='adjame'),'Habitat Extension','adjame-habitat-extension','Extension',5.3700,-4.0150),
((SELECT id FROM communes WHERE slug='adjame'),'Gare Nord','adjame-gare-nord','Gare UTB',5.3640,-4.0260),
((SELECT id FROM communes WHERE slug='anyama'),'Anyama centre','anyama-centre','Anyama',5.4950,-4.0520),
((SELECT id FROM communes WHERE slug='anyama'),'Ebimpé','anyama-ebimpe','Ebimpe',5.4700,-4.0900),
((SELECT id FROM communes WHERE slug='anyama'),'Akoupé-Zeudji','anyama-akoupe-zeudji','Akoupé',5.5200,-4.0600),
((SELECT id FROM communes WHERE slug='anyama'),'Anyama Adjamé','anyama-adjame','Adjamé d''Anyama',5.4920,-4.0450),
((SELECT id FROM communes WHERE slug='anyama'),'Thomasset','anyama-thomasset',NULL,5.5050,-4.0400),
((SELECT id FROM communes WHERE slug='attecoube'),'Attécoubé centre','attecoube-centre','Attécoubé',5.3330,-4.0400),
((SELECT id FROM communes WHERE slug='attecoube'),'Locodjro','attecoube-locodjro','Locodjoro',5.3330,-4.0600),
((SELECT id FROM communes WHERE slug='attecoube'),'Santé','attecoube-sante','Sante',5.3400,-4.0400),
((SELECT id FROM communes WHERE slug='attecoube'),'Agban','attecoube-agban','Agban village',5.3480,-4.0320),
((SELECT id FROM communes WHERE slug='attecoube'),'Boribana','attecoube-boribana',NULL,5.3270,-4.0300),
((SELECT id FROM communes WHERE slug='attecoube'),'Abobo-Doumé','attecoube-abobo-doume','Abobo Doume',5.3170,-4.0450),
((SELECT id FROM communes WHERE slug='attecoube'),'Sébroko','attecoube-sebroko','Sebroko',5.3420,-4.0500),
((SELECT id FROM communes WHERE slug='bingerville'),'Bingerville centre','bingerville-centre','Bingerville',5.3550,-3.8850),
((SELECT id FROM communes WHERE slug='bingerville'),'Akandjé','bingerville-akandje','Akandje',5.3800,-3.9000),
((SELECT id FROM communes WHERE slug='bingerville'),'Gbagba','bingerville-gbagba',NULL,5.3460,-3.8730),
((SELECT id FROM communes WHERE slug='bingerville'),'Abatta','bingerville-abatta',NULL,5.3700,-3.9300),
((SELECT id FROM communes WHERE slug='bingerville'),'Feh Kessé','bingerville-feh-kesse','Fehkessé',5.3620,-3.8700),
((SELECT id FROM communes WHERE slug='bingerville'),'Santai','bingerville-santai',NULL,5.3450,-3.9050),
((SELECT id FROM communes WHERE slug='cocody'),'Riviera 2','cocody-riviera-2','Riv 2, Riviera II',5.3600,-3.9600),
((SELECT id FROM communes WHERE slug='cocody'),'Riviera 3','cocody-riviera-3','Riv 3, Riviera III',5.3650,-3.9480),
((SELECT id FROM communes WHERE slug='cocody'),'Riviera Palmeraie','cocody-riviera-palmeraie','Palmeraie',5.3720,-3.9350),
((SELECT id FROM communes WHERE slug='cocody'),'Angré 8e tranche','cocody-angre-8e-tranche','Angre, 8e tranche, Angré',5.3950,-3.9900),
((SELECT id FROM communes WHERE slug='cocody'),'Deux-Plateaux Vallon','cocody-deux-plateaux-vallon','2 Plateaux, Vallons, II Plateaux',5.3700,-4.0000),
((SELECT id FROM communes WHERE slug='cocody'),'Riviera Golf','cocody-riviera-golf','Golf',5.3380,-3.9550),
((SELECT id FROM communes WHERE slug='cocody'),'Angré Château','cocody-angre-chateau','Château',5.3950,-3.9780),
((SELECT id FROM communes WHERE slug='cocody'),'Cocody centre','cocody-centre','Cocody',5.3470,-3.9950),
((SELECT id FROM communes WHERE slug='cocody'),'Blockhaus','cocody-blockhaus',NULL,5.3360,-3.9850),
((SELECT id FROM communes WHERE slug='cocody'),'Danga','cocody-danga',NULL,5.3380,-4.0050),
((SELECT id FROM communes WHERE slug='cocody'),'Faya','cocody-faya',NULL,5.3850,-3.9200),
((SELECT id FROM communes WHERE slug='cocody'),'M''Pouto','cocody-mpouto','Mpouto',5.3300,-3.9450),
((SELECT id FROM communes WHERE slug='koumassi'),'Koumassi centre','koumassi-centre','Koumassi',5.2970,-3.9500),
((SELECT id FROM communes WHERE slug='koumassi'),'Remblais','koumassi-remblais',NULL,5.2900,-3.9600),
((SELECT id FROM communes WHERE slug='koumassi'),'Sicogi','koumassi-sicogi',NULL,5.3000,-3.9420),
((SELECT id FROM communes WHERE slug='koumassi'),'Campement','koumassi-campement',NULL,5.2950,-3.9300),
((SELECT id FROM communes WHERE slug='koumassi'),'Prodomo','koumassi-prodomo',NULL,5.3050,-3.9520),
((SELECT id FROM communes WHERE slug='koumassi'),'Grand Marché','koumassi-grand-marche','Marché de Koumassi',5.2990,-3.9450),
((SELECT id FROM communes WHERE slug='marcory'),'Zone 4','marcory-zone-4','Zone IV',5.2900,-3.9750),
((SELECT id FROM communes WHERE slug='marcory'),'Biétry','marcory-bietry','Bietry',5.2830,-3.9700),
((SELECT id FROM communes WHERE slug='marcory'),'Marcory Résidentiel','marcory-residentiel','Résidentiel',5.3050,-3.9850),
((SELECT id FROM communes WHERE slug='marcory'),'Anoumabo','marcory-anoumabo',NULL,5.3000,-3.9950),
((SELECT id FROM communes WHERE slug='marcory'),'Sans Fil','marcory-sans-fil','Sans-fil',5.3100,-3.9800),
((SELECT id FROM communes WHERE slug='marcory'),'Champroux','marcory-champroux',NULL,5.3080,-3.9900),
((SELECT id FROM communes WHERE slug='plateau'),'Plateau centre','plateau-centre','Plateau',5.3230,-4.0190),
((SELECT id FROM communes WHERE slug='plateau'),'Indénié','plateau-indenie','Indenie',5.3330,-4.0130),
((SELECT id FROM communes WHERE slug='plateau'),'Cité administrative','plateau-cite-administrative','Cité admin',5.3250,-4.0230),
((SELECT id FROM communes WHERE slug='plateau'),'Plateau Sud','plateau-sud',NULL,5.3160,-4.0200),
((SELECT id FROM communes WHERE slug='port-bouet'),'Port-Bouët centre','port-bouet-centre','Port-Bouet',5.2550,-3.9400),
((SELECT id FROM communes WHERE slug='port-bouet'),'Gonzagueville','port-bouet-gonzagueville','Gonzague',5.2450,-3.8750),
((SELECT id FROM communes WHERE slug='port-bouet'),'Vridi','port-bouet-vridi','Vridi canal',5.2600,-4.0000),
((SELECT id FROM communes WHERE slug='port-bouet'),'Aéroport','port-bouet-aeroport','Aeroport',5.2560,-3.9300),
((SELECT id FROM communes WHERE slug='port-bouet'),'Adjouffou','port-bouet-adjouffou',NULL,5.2500,-3.9200),
((SELECT id FROM communes WHERE slug='port-bouet'),'Jean Folly','port-bouet-jean-folly',NULL,5.2580,-3.9500),
((SELECT id FROM communes WHERE slug='songon'),'Songon Agban','songon-agban','Songon',5.3200,-4.2600),
((SELECT id FROM communes WHERE slug='songon'),'Songon Kassemblé','songon-kassemble','Kassemble',5.3100,-4.2900),
((SELECT id FROM communes WHERE slug='songon'),'Songon M''Brathé','songon-mbrathe','Mbrathe',5.3300,-4.2300),
((SELECT id FROM communes WHERE slug='songon'),'Songon Dagbé','songon-dagbe','Dagbe',5.3150,-4.2450),
((SELECT id FROM communes WHERE slug='treichville'),'Treichville centre','treichville-centre','Treichville',5.2930,-4.0050),
((SELECT id FROM communes WHERE slug='treichville'),'Arras','treichville-arras',NULL,5.2950,-4.0050),
((SELECT id FROM communes WHERE slug='treichville'),'Biafra','treichville-biafra',NULL,5.2970,-4.0120),
((SELECT id FROM communes WHERE slug='treichville'),'Belleville','treichville-belleville',NULL,5.2880,-4.0100),
((SELECT id FROM communes WHERE slug='treichville'),'Gare de Bassam','treichville-gare-de-bassam','Gare Bassam',5.2920,-3.9970),
((SELECT id FROM communes WHERE slug='treichville'),'Zone portuaire','treichville-zone-portuaire','Port',5.2850,-4.0150),
((SELECT id FROM communes WHERE slug='yopougon'),'Niangon','yopougon-niangon','Niangon Nord, Niangon Sud',5.3350,-4.1050),
((SELECT id FROM communes WHERE slug='yopougon'),'Sideci','yopougon-sideci','Sidéci',5.3350,-4.0750),
((SELECT id FROM communes WHERE slug='yopougon'),'Selmer','yopougon-selmer',NULL,5.3500,-4.0800),
((SELECT id FROM communes WHERE slug='yopougon'),'Maroc','yopougon-maroc',NULL,5.349935,-4.080947),
((SELECT id FROM communes WHERE slug='yopougon'),'Port-Bouët 2','yopougon-port-bouet-2','PB2',5.3380,-4.0920),
((SELECT id FROM communes WHERE slug='yopougon'),'Toits Rouges','yopougon-toits-rouges',NULL,5.3550,-4.0850),
((SELECT id FROM communes WHERE slug='yopougon'),'Andokoi','yopougon-andokoi','Andokoua',5.3650,-4.0900),
((SELECT id FROM communes WHERE slug='yopougon'),'Wassakara','yopougon-wassakara',NULL,5.3400,-4.0650),
((SELECT id FROM communes WHERE slug='yopougon'),'Banco','yopougon-banco','Banco 2',5.3600,-4.0700),
((SELECT id FROM communes WHERE slug='yopougon'),'Koweït','yopougon-koweit','Koweit',5.3300,-4.0900),
((SELECT id FROM communes WHERE slug='yopougon'),'Gesco','yopougon-gesco',NULL,5.3550,-4.1100),
((SELECT id FROM communes WHERE slug='yopougon'),'Yopougon Kouté','yopougon-koute','Kouté',5.3220,-4.0700);

INSERT INTO types_vehicule (code,libelle,ordre) VALUES
('voiture','Voiture',1),
('moto','Moto',2),
('camion','Camion',3);

INSERT INTO specialites (code,libelle,ordre) VALUES
('mecanique_generale','Mécanique générale',1),
('electricite_auto','Électricité auto',2),
('batterie','Batterie',3),
('demarreur_alternateur','Démarreur et alternateur',4),
('moteur','Moteur',5),
('refroidissement','Refroidissement',6),
('pneus','Pneus et vulcanisation',7),
('freins','Freins',8),
('boite_vitesses','Boîte de vitesses',9),
('carrosserie','Carrosserie',10),
('remorquage','Dépannage et remorquage',11),
('moto','Mécanique moto',12);

INSERT INTO pannes (code,libelle,picto,est_danger,ordre) VALUES
('ne_demarre_pas','Ne démarre pas','key',0,1),
('batterie','Batterie','battery',0,2),
('surchauffe','Surchauffe','heat',0,3),
('pneu_creve','Pneu crevé','tyre',0,4),
('huile_fuite','Huile ou fuite','oil',0,5),
('freins','Freins','brake',0,6),
('choc_accident','Choc ou accident','crash',1,7),
('bruit_fumee','Bruit ou fumée','smoke',0,8),
('je_ne_sais_pas','Je ne sais pas','q',0,9);

INSERT INTO panne_specialite (panne_id,specialite_id) VALUES
((SELECT id FROM pannes WHERE code='ne_demarre_pas'),(SELECT id FROM specialites WHERE code='batterie')),
((SELECT id FROM pannes WHERE code='ne_demarre_pas'),(SELECT id FROM specialites WHERE code='demarreur_alternateur')),
((SELECT id FROM pannes WHERE code='ne_demarre_pas'),(SELECT id FROM specialites WHERE code='electricite_auto')),
((SELECT id FROM pannes WHERE code='batterie'),(SELECT id FROM specialites WHERE code='batterie')),
((SELECT id FROM pannes WHERE code='batterie'),(SELECT id FROM specialites WHERE code='electricite_auto')),
((SELECT id FROM pannes WHERE code='surchauffe'),(SELECT id FROM specialites WHERE code='refroidissement')),
((SELECT id FROM pannes WHERE code='surchauffe'),(SELECT id FROM specialites WHERE code='moteur')),
((SELECT id FROM pannes WHERE code='pneu_creve'),(SELECT id FROM specialites WHERE code='pneus')),
((SELECT id FROM pannes WHERE code='huile_fuite'),(SELECT id FROM specialites WHERE code='moteur')),
((SELECT id FROM pannes WHERE code='huile_fuite'),(SELECT id FROM specialites WHERE code='mecanique_generale')),
((SELECT id FROM pannes WHERE code='freins'),(SELECT id FROM specialites WHERE code='freins')),
((SELECT id FROM pannes WHERE code='choc_accident'),(SELECT id FROM specialites WHERE code='carrosserie')),
((SELECT id FROM pannes WHERE code='choc_accident'),(SELECT id FROM specialites WHERE code='remorquage')),
((SELECT id FROM pannes WHERE code='bruit_fumee'),(SELECT id FROM specialites WHERE code='moteur')),
((SELECT id FROM pannes WHERE code='bruit_fumee'),(SELECT id FROM specialites WHERE code='mecanique_generale')),
((SELECT id FROM pannes WHERE code='je_ne_sais_pas'),(SELECT id FROM specialites WHERE code='mecanique_generale'));

INSERT INTO types_piece (code,libelle,obligatoire,ordre) VALUES
('cni_recto','Pièce d''identité (recto)',1,1),
('cni_verso','Pièce d''identité (verso)',1,2),
('permis','Permis de conduire',1,3),
('diplome','Diplôme ou attestation de métier',1,4),
('portrait','Photo de vous',1,5),
('atelier','Photo de l''atelier',0,6);

INSERT INTO etiquettes_avis (code,libelle,polarite,ordre) VALUES
('rapide','Arrivé vite','positive',1),
('prix_clair','Prix clair','positive',2),
('bon_travail','Travail propre','positive',3),
('poli','Poli','positive',4),
('en_retard','En retard','negative',5),
('plus_cher','Plus cher qu’annoncé','negative',6);

INSERT INTO parametres (cle,valeur,type,description) VALUES
('recherche.rayons_km','2,5,10','liste','RG-R5 · paliers d''élargissement'),
('recherche.min_resultats','3','int','RG-R5 · on élargit tant qu''on a moins que ça'),
('recherche.max_resultats','10','int','RG-R6 · repères affichés au maximum'),
('recherche.rayon_etendu_km','20','int','RG-R5 · « Chercher jusqu''à 20 km » : jamais automatique, seulement à la demande du client'),
('recherche.bonus_specialiste','0.75','decimal','RG-R6 · distance d''un spécialiste multipliée par ce facteur'),
('dispo.position_perimee_h','2','int','RG-D2 · « Mettre à jour ma position » devient l''action principale'),
('dispo.duree_max_h','12','int','RG-D3 · passage automatique en indisponible'),
('intervention.fin_auto_h','2','int','RG-D4 · fin automatique de « en intervention »'),
('appel.sonnerie_s','30','int','RG-A5 · durée de sonnerie avant « manqué »'),
('appel.relance_push_s','5','int','RG-A5 · intervalle de relance de la notification'),
('appel.echec_reseau_s','15','int','RG-A7 · connexion audio impossible'),
('appel.vu_recent_h','24','int','RG-A2 · le mécano peut recevoir l''appel intégré'),
('appel.integre_actif','1','int','RG-A2 · 1 = l''appel intégré est le bouton principal quand le navigateur et le mécano le permettent ; 0 = « Appeler » ouvre toujours l''appel direct'),
('retour.absence_min_s','3','int','RG-I4 · absence minimale pour que « Avez-vous pu le joindre ? » monte'),
('retour.bandeau_h','24','int','RG-I4 · durée du bandeau « Avez-vous pu le joindre ? » à la visite suivante'),
('injoignable.seuil','3','int','RG-D5 · appels intégrés manqués d''affilée'),
('injoignable.fenetre_h','2','int','RG-D5 · fenêtre de comptage'),
('antiabus.contacts_client_10min','5','int','RG-A10'),
('antiabus.contacts_meca_h','3','int','RG-A10 · même client vers le même mécano'),
('avis.delai_j','7','int','RG-AV1 · délai pour laisser un avis'),
('avis.duree_min_s','30','int','RG-AV1 · appel intégré assez long pour ouvrir l''avis'),
('avis.modifiable_h','24','int','RG-AV2'),
('signalement.lecture_h','24','int','RG-S2 · délai de lecture par un admin'),
('signalement.critique_h','2','int','A4 · délai cible pour traiter un signalement critique (sécurité, identité, comportement)'),
('dossier.delai_h','48','int','RG-DO3 · réponse annoncée au mécano après l''envoi du dossier'),
('dossier.alerte_h','36','int','A1 · un dossier qui attend plus longtemps passe en alerte'),
('contact.whatsapp','+2250707000000','texte','X4 · numéro WhatsApp affiché (à confirmer avant la mise en ligne)'),
('contact.telephone','+2250707000000','texte','X4 · numéro de téléphone affiché (à confirmer avant la mise en ligne)'),
('contact.email','bonjour@allomecano.ci','texte','X4 · adresse e-mail affichée'),
('signalement.photo_max_ko','300','int','RG-S3 · poids maximum de la photo jointe'),
('avis.seuil_note','3','int','RG-AV6 · en dessous, la fiche affiche « Nouveau »'),
('purge.position_h','24','int','RG-P1'),
('purge.recherches_j','90','int','RG-P2'),
('purge.signalisation_h','1','int','RG-P3'),
('purge.pieces_refus_mois','6','int','RG-P5'),
('auth.essais_max','5','int','RG-C2'),
('auth.blocage_min','15','int','RG-C2'),
('auth.lien_reinit_min','30','int','RG-C3'),
('dossier.delai_annonce_h','48','int','RG-DO3 · délai de réponse annoncé'),
('recherche.malus_autre_rive','1.30','decimal','RG-R9 · distance d''un mécano de l''autre rive multipliée par ce facteur'),
('contact.messages_ip_h','3','int','RG-CT1 · messages du formulaire par adresse IP et par heure'),
('urgence.samu','185','texte','Numéro affiché (choc ou accident)'),
('urgence.pompiers','180','texte','Numéro affiché'),
('urgence.police','170','texte','Numéro affiché');
