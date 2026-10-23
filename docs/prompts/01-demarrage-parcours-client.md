# Prompt de démarrage · Claude Code en local · Parcours client

## Avant de coller le prompt (à faire une fois)

1. Ouvrir un terminal dans `C:\xampp\htdocs\allomecano`, être sur la dernière version :
   ```bat
   git checkout feature/socle
   git pull
   ```
2. Connecter Figma à Claude Code (pour qu'il lise les maquettes) :
   ```bat
   claude mcp add --transport http figma https://mcp.figma.com/mcp
   ```
   Puis lancer `claude`, taper `/mcp`, choisir **figma** et se connecter avec le compte qui a accès au fichier.
   Variante : dans l'application Figma desktop, activer le serveur MCP (menu Préférences), puis
   `claude mcp add --transport http figma-desktop http://127.0.0.1:3845/mcp`.
3. Démarrer MySQL dans XAMPP. Lancer `claude` dans le dossier du projet, puis coller tout le bloc ci-dessous.

---

## Le prompt

````text
Tu travailles avec moi sur allomécano, en local sur mon PC Windows (XAMPP). On va coder le PARCOURS CLIENT, écran par écran. Lis tout ce message avant d'agir.

# 1. Le projet

allomécano est une plateforme web, mobile d'abord, pour Abidjan (Côte d'Ivoire) : un automobiliste en panne trouve en quelques secondes le mécanicien vérifié et disponible le plus proche, et l'appelle gratuitement sans quitter la plateforme.

- Trois profils : client (sans compte), mécano (compte vérifié par l'équipe), admin.
- Équipe : 3 développeurs (Dev_Eyes) : Paul Emmanuel, Axelle, et moi.
- Présenté à un CONCOURS. Mise en ligne le samedi 24 octobre 2026. Il n'y a pas de V2 : tout ce qui est dessiné dans Figma fait partie de la version présentée et doit être fini, précis, soigné. Pas de demi-mesure, pas de « on fera plus tard » sur un élément dessiné.
- Hors périmètre (non dessiné) : zoom/regroupement des repères qui se superposent sur la carte, numéro masqué.

# 2. Ce qui existe déjà (branche feature/socle)

Lis ces fichiers en premier, dans cet ordre :
1. README.md
2. docs/organisation.md (branches, commits, PR, définition de « terminé »)
3. docs/modele-donnees.html (34 tables + 64 règles métier codées RG-…). C'est la référence des règles.
4. app/routes.php (toutes les routes prévues, avec le code d'écran Figma en commentaire)
5. database/schema.sql, database/recherche.sql, database/seeds/demo.sql
6. app/Core/*, app/helpers.php, app/Views/layouts/*, app/Views/composants/*, app/Views/communs/systeme.php
7. public/assets/css/tokens.css, base.css, composants/*.css, public/assets/js/core/app.js
8. docs/mouvement-et-interactions.html : la spécification des animations et micro-interactions (démarrage, transitions, parcours panne, boutons, toasts, réseau, règles communes). Ouvre-la aussi dans le navigateur : c'est un prototype interactif avec les durées et courbes.

Déjà fait : routeur, requêtes, réponses, vues avec layouts, sessions, CSRF, validation (téléphone +225 → E.164), formats français (Format::fcfa, date, distance, typo avec espaces fines), ULID, design system (tokens clair/sombre issus de Figma, polices Archivo + Atkinson Hyperlegible auto-hébergées, sprite de 53 icônes via icone('nom')), composants PHP (bouton, champ, voyant, poinçon, statut, note, barre client, barre de parcours, socle d'action, pied de page), page /systeme (vitrine), page d'erreur X6, base + 8 mécanos de démo, 13 tests PHPUnit.

# 3. Stack et environnement

- PHP 8.2+ SANS framework, PSR-4 `App\` → app/, PDO requêtes préparées, `declare(strict_types=1);`.
- MySQL/MariaDB : en local MariaDB 10.4 (XAMPP), en ligne Systalink (Apache, .htaccess). Reste compatible MariaDB 10.4 et MySQL 8.
- HTML, CSS, JavaScript natif en modules ES, sans build. PWA (manifest + service worker à venir).
- Carte : Leaflet + Protomaps (tuiles PMTiles hébergées chez nous). Appel intégré : WebRTC, signalisation PHP/MySQL par interrogation, relais Cloudflare TURN. Notifications : Web Push (VAPID) + e-mail SMTP. PAS de SMS.
- Composer : minishlink/web-push, phpmailer, phpunit (dev). Pas d'autre dépendance sans me demander.
- Windows : commandes en cmd/PowerShell. Site lancé avec `composer serve` → http://localhost:8000. Base recréée avec `composer db:demo`. Tests : `vendor\bin\phpunit`.

Pièges connus :
- PDO est en requêtes préparées NATIVES (pas d'émulation) : un même paramètre nommé ne peut pas apparaître deux fois. database/recherche.sql réutilise :lat, :lon, :rayon plusieurs fois : dans le service, renomme-les (:lat1, :lat2…) ou passe en paramètres positionnels.
- Tout est stocké et lu en UTC (Abidjan = GMT).
- Les URL et l'API exposent public_id (ULID) ou slug, jamais l'id numérique (RG-C8).

# 4. Figma : la référence visuelle

Fichier : https://www.figma.com/design/G6hAhkXOYCY3z6ugFyojoO/ (fileKey G6hAhkXOYCY3z6ugFyojoO).
Avant de coder un écran, appelle get_design_context (et get_screenshot) sur son nœud. Le code renvoyé par Figma est une RÉFÉRENCE : adapte-le à nos composants et à nos jetons, ne le colle jamais tel quel.

Pages à coder : Système (40:96), Mobile · Client (40:97), Mobile · Mécano (40:98), Communs (40:99), Desktop (40:100), Admin (40:101). Page Images (34:1055) : visuels. Tout ce qui s'appelle « Archive V1 · … (ne pas coder) » est à ignorer.

Écrans du parcours client :
| Code | Écran | Nœud |
|---|---|---|
| C1 | Accueil (mobile) | 58:15 |
| D0 | Accueil (desktop) | 154:14 |
| C2a | Localisation GPS | 66:437 |
| C2a | Localisation GPS (recherche) | 198:1963 |
| C2a | Confirmation de la position | 67:462 |
| C2b | Commune, quartier, repère | 68:604 |
| C3 | Ma panne | 69:653 |
| C3 | Choc ou accident | 69:868 |
| C4 | Résultats | 60:196 |
| C4 | Résultats, sombre | 185:1669 |
| C4b | Chargement | 73:942 |
| C4c | Aucun mécano disponible | 75:1070 |
| D1 | Résultats (desktop) | 156:274 |
| C5 | Fiche mécanicien | 76:1211 |
| C5 | Fiche, sombre | 185:1697 |
| C5 | Plus disponible | 78:1304 |
| D1 | Fiche ouverte, numéro révélé (desktop) | 158:542 |
| C5c | Appel intégré : ça sonne / en ligne / ne répond pas / terminé | 269:1820, 269:1866, 270:1846, 270:1905 |
| C6 | Avez-vous pu le joindre ? | 82:1322 |
| C6 | Non : on vous propose Adama | 84:1394 |
| C6 | Oui, il arrive | 86:1488 |
| C6b | Votre avis / Merci | 87:1576, 88:1609 |
| C7 | Signaler / rempli / envoyé | 89:1612, 210:1774, 90:1660 |

Composants Figma (page Système) et leur équivalent :
Bouton 53:148, Champ 53:230, Socle d'action 53:282, Barre de parcours 53:374, Statut 53:406, Note 53:446, Toast 53:447, Poinçon 49:166, Étiquette Vérifié 49:175, Voyant d'état 49:144, Vous êtes ici 49:130, Repère carte 49:32, Voyant de bord 48:214, Ligne de classement 54:251, Tuile véhicule 54:293, Tuile voyant 54:390, Panneau des voyants 54:391, Étiquette de choix 54:451, Ligne de choix 54:467, Segment 54:499, Stepper 54:500, Avis 54:674, Bloc « Ce qu'il faut lui dire » 54:675, Barre client 56:322, Barre desktop 56:337, Pied de page 56:636 / 56:670, Ligne commune 56:734, Fond de carte 56:736, Légende-repère 49:254.
Ceux qui existent déjà en PHP sont dans app/Views/composants/ et visibles sur /systeme. Les autres (ligne de classement, repère carte, vous êtes ici, ligne commune, avis, bloc message, tuiles, stepper…) sont à créer comme composants réutilisables, PAS en dur dans une page, et à ajouter sur /systeme.

# 5. Règles de fidélité et de qualité

- Couleurs, espacements, rayons, ombres, polices : uniquement les variables de tokens.css (var(--encre), var(--esp-16)…). Jamais une valeur en dur copiée de Figma. Si un jeton manque, dis-le avant d'en créer un.
- Textes : exactement ceux de Figma, français soigné, espaces fines avant ; : ! ? (fr_typo / Format::typo), montants Format::fcfa, dates Format::date.
- Chaque écran : mobile 360 px ET desktop 1440 px quand un écran desktop existe ; clair ET sombre ; tous les états dessinés (vide, chargement, erreur, succès, indisponible).
- Animations : celles de docs/mouvement-et-interactions.html, avec une version prefers-reduced-motion.
- Accessibilité : HTML sémantique, libellés sur tous les champs, focus visible, ordre de tabulation logique, contrastes, zones tactiles ≥ 44 px, aria-live pour les changements dynamiques.
- Performance (Android d'entrée de gamme, 3G) : pas de framework JS, images WebP dimensionnées, CSS de page chargé seulement sur sa page (Assets::css), JS de page en module.
- Sécurité : e() sur toute sortie, CSRF sur tout POST, requêtes préparées, validation côté serveur même si le JS valide.
- Contrôleurs minces → logique métier dans app/Services/ → vues dans app/Views/client/. Tests PHPUnit pour chaque service.
- Respecte les règles métier et cite leur code (RG-R1…) dans le code là où elles s'appliquent. Règles du client : RG-R1 à R9 (recherche), RG-A1 à A12 (appels), RG-I1 à I8 (issue de l'appel), RG-AV1 à AV8 (avis), RG-S1 à S3 (signalement), RG-C7 (client anonyme par jeton), RG-C8, RG-P1/P2 (purge des positions).
- Décisions déjà prises (ne pas rediscuter) : trois façons d'appeler (appel intégré gratuit en premier, appel direct tel:, WhatsApp) ; chaque appui crée un contact AVANT d'ouvrir l'appel ; avis seulement après un vrai contact ; client sans compte ; position exacte effacée après 24 h ; le véhicule filtre strictement, la panne ne fait que remonter les spécialistes (bonus 0,75) ; autre rive de la lagune = malus 1,3 ; recherche depuis la position de disponibilité du mécano, dans son rayon ; numéros d'urgence 185 / 180 / 170 (dans Parametres).

# 6. Méthode de travail : partie par partie

On avance UNE partie à la fois. Pour chaque partie :
1. Lis les nœuds Figma concernés (get_design_context + get_screenshot), les règles RG concernées et le prototype d'animation correspondant.
2. Présente-moi un plan court : fichiers créés/modifiés, composants nouveaux, routes, service, requêtes, questions éventuelles. ATTENDS mon accord.
3. Code. Crée une branche par partie depuis feature/socle (ou main si le socle y est fusionné) : ecran/c1-accueil, ecran/c2-position…
4. Vérifie toi-même : php -l, vendor\bin\phpunit, la page dans le navigateur à 360 px et 1440 px, clair et sombre, et compare à la capture Figma. Liste les écarts restants.
5. Montre-moi le résultat et les écarts. Après ma validation : commit au format « C1 · accueil : … (RG-…) ». Push et PR seulement quand je te le dis. Jamais de commit sur main, jamais de force push.

Découpage prévu :
- Partie 1 : C1 + D0 · Accueil (mobile + desktop), avec la barre client, le pied de page et les numéros d'urgence.
- Partie 2 : C2a + C2b · Position (GPS avec précision, confirmation, repli commune/quartier/repère), jeton client anonyme (RG-C7).
- Partie 3 : C3 · Ma panne (tuiles véhicule, voyants, cas Choc ou accident avec la note danger).
- Partie 4 : C4 / C4b / C4c + D1 · Résultats : RechercheService (paliers 2/5/10 km, liste figée), liste, carte Leaflet, états chargement et aucun résultat.
- Partie 5 : C5 + D1 fiche · Fiche mécanicien (preuve de vérification, bornes, avis, « Ce qu'il faut lui dire », plus disponible, numéro révélé + QR sur desktop).
- Partie 6 : C6 · Contact direct et WhatsApp (contact créé avant, « Avez-vous pu le joindre ? », proposition du suivant).
- Partie 7 : C5c · Appel intégré (WebRTC) côté client.
- Partie 8 : C6b · Avis. Partie 9 : C7 · Signalement.

# 7. Ta première tâche (et seulement celle-là)

ÉTAPE 0 — Avant tout code :
1. Lis les fichiers de la section 2 et explore le dépôt.
2. Vérifie que l'environnement marche : `php -v`, `composer db:demo`, `vendor\bin\phpunit`, puis http://localhost:8000/systeme. Vérifie que l'accès Figma marche (get_metadata sur 40:97).
3. Rédige un fichier CLAUDE.md à la racine du dépôt, en français, court et dense (150 lignes maximum), qui servira à toutes les sessions et à toute l'équipe :
   - le projet en 5 lignes, l'échéance, « pas de V2 » ;
   - commandes (serve, db:demo, tests, sprite, assets) ;
   - architecture et où mettre quoi (Core, Controllers, Services, Views, composants, assets/css/pages, assets/js/pages) ;
   - conventions de code (PHP, SQL, vues, CSS par jetons, JS modules, textes français) et les pièges connus de la section 3 ;
   - Figma : fileKey, pages, tableau des écrans client et des composants avec leurs nœuds, règle « référence à adapter, pas à coller » ;
   - règles de fidélité et définition de « terminé » (section 5) ;
   - méthode de travail partie par partie et règles git (section 6) ;
   - où trouver les règles métier et les décisions déjà prises.
   Ne recopie pas ce qui se lit facilement dans le code : pointe vers les fichiers.
4. Montre-moi le CLAUDE.md et la liste de ce que tu as vérifié (ce qui marche, ce qui ne marche pas). ARRÊTE-TOI là et attends ma validation avant la partie 1.
````
