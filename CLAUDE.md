# allomécano · guide de travail (équipe Dev_Eyes et Claude Code)

## Le projet
- Plateforme web mobile d'abord pour Abidjan : un automobiliste en panne trouve le mécanicien **vérifié et disponible** le plus proche et l'appelle gratuitement depuis la plateforme.
- Trois profils : client (sans compte, jeton anonyme), mécano (dossier vérifié par l'équipe), admin.
- Présenté à un concours. **Mise en ligne samedi 24 octobre 2026.**
- **Pas de V2** : tout ce qui est dessiné dans Figma est livré, fini, précis. Aucun « on fera plus tard » sur un élément dessiné.
- Hors périmètre (non dessiné) : regroupement des repères superposés sur la carte, numéro masqué.

## Commandes (Windows, XAMPP, cmd ou PowerShell)
| But | Commande |
|---|---|
| Lancer le site | `composer serve` → http://localhost:8000 (MySQL démarré dans le panneau XAMPP) |
| Recréer la base + 8 mécanos de démo | `composer db:demo` (efface la base locale) |
| Tests | `vendor\bin\phpunit` (ou `composer test`) |
| Syntaxe | `php -l fichier.php` |
| Sprite d'icônes | `composer sprite` (source : `scripts/icones.json`) |
| Assets de prod | `composer assets` |
| Clés Web Push | `composer vapid` |
| Vitrine des composants | http://localhost:8000/systeme (masquée en prod) |

Installation pas à pas : `docs/installation-xampp.md`. Comptes de démo : `README.md`.

## À lire avant de coder
1. `docs/organisation.md` : branches, commits, PR, définition de « terminé ».
2. `docs/modele-donnees.html` : 34 tables + 64 règles métier `RG-…`. **Référence des règles.**
3. `app/routes.php` : toutes les URL prévues, avec le code d'écran Figma.
4. `database/schema.sql`, `database/recherche.sql`, `database/seeds/demo.sql`.
5. `docs/mouvement-et-interactions.html` : durées, courbes, micro-interactions (prototype à ouvrir dans le navigateur).

## Où mettre quoi
| Dossier | Contenu |
|---|---|
| `app/Core/` | Router, Request, Response, View, Database, Session, Csrf, Validator, Format, Ulid, Env, Assets. Ne pas y mettre de métier. |
| `app/Controllers/Client/` | Contrôleurs **minces** : lire la requête → appeler un service → rendre une vue. |
| `app/Services/` | Logique métier et SQL, sans HTML. Un test PHPUnit par service (`tests/Unit/`, `tests/Integration/`). |
| `app/Middleware/` | CSRF, connexion, limites de débit. |
| `app/Views/client/` | Vues des écrans client. Layout : `layouts/client` (`$sansBarre`, `$sansPied`, `$css`, `$js`, `$titre`). |
| `app/Views/composants/` | Composants réutilisables, appelés par `composant('nom', [...])`. Docblock en tête = props. |
| `public/assets/css/composants/` | CSS commun (listé dans `Assets::CSS_COMMUN`). |
| `public/assets/css/pages/` | CSS d'une page, passé via `'css' => ['css/pages/x.css']`, chargé seulement sur cette page. |
| `public/assets/js/pages/` | Module ES d'une page, passé via `'js' => ['js/pages/x.js']`. Commun : `js/core/app.js` (`api()`, `toast()`, `chargement()`). |

Nouveau composant : fichier dans `composants/` + CSS + **démonstration sur `/systeme`** (`app/Views/communs/systeme.php`). Jamais un composant écrit en dur dans une page.

## Conventions de code
- **PHP 8.2** sans framework, `declare(strict_types=1);`, PSR-4 `App\` → `app/`, une classe par fichier. Noms en français (comme le socle).
- **SQL** : `Database::instance()->un() / tous() / valeur() / inserer() / modifier() / transaction()`, toujours paramètres liés.
- **Piège** : requêtes préparées **natives** (pas d'émulation) → un même paramètre nommé ne peut pas apparaître deux fois. `database/recherche.sql` réutilise `:lat`, `:lon`, `:rayon` : dans le service, renommer (`:lat1`, `:lat2`…) ou passer en positionnel.
- **Temps** : tout est stocké et lu en UTC (Abidjan = GMT).
- **Identifiants** : URL et API exposent `public_id` (ULID, `Ulid::generer()`) ou `slug`, jamais l'`id` numérique (RG-C8).
- **Compatibilité** : MariaDB 10.4 (local) et MySQL 8 (Systalink). Pas de fonction propre à l'un des deux.
- **Seuils métier** : lus dans la table `parametres` via `Parametres::int()/decimal()/liste()`, jamais en dur (rayons 2/5/10, bonus 0,75, malus 1,3, urgences 185/180/170…).
- **Vues** : toute sortie passe par `e()`. HTML déjà échappé seulement pour les props documentées comme « HTML » (`note.message`, `socle.actions`).
- **Sécurité** : CSRF sur tout POST (`csrf_champ()` dans les formulaires, `api()` côté JS), validation serveur avec `Validator` même si le JS valide, fichiers privés dans `storage/`.
- **CSS** : uniquement les variables de `tokens.css` (`var(--encre)`, `var(--esp-16)`, `var(--coupe-m)`, `var(--t-etat)`…). Aucune valeur copiée de Figma. **Si un jeton manque : le signaler avant d'en créer un.** Classes de texte `.t-…` = noms des styles Figma (`base.css`). Clair et sombre via les jetons (`data-theme` + `prefers-color-scheme`).
- **JS** : modules ES natifs, pas de framework, amélioration progressive (la page marche sans JS quand c'est possible).
- **Dépendances** : Composer limité à web-push, phpmailer, phpunit. Rien d'autre sans accord. Côté navigateur : Leaflet + Protomaps (PMTiles hébergé chez nous).
- **Textes** : exactement ceux de Figma, français soigné. Espaces fines avant `; : ! ?` (`fr_typo()` / `Format::typo`, ou `&#8239;` dans le HTML), montants `Format::fcfa()`, distances `Format::distance()` / `borne()`, dates `Format::date()`, téléphones `Format::telephone()`.
- **Icônes** : `icone('nom', taille)`. 24 par défaut, voyants 32, véhicules 40.

## Figma : la référence visuelle
- Fichier : https://www.figma.com/design/G6hAhkXOYCY3z6ugFyojoO/ (fileKey `G6hAhkXOYCY3z6ugFyojoO`).
- Pages à coder : Système `40:96`, Mobile · Client `40:97`, Mobile · Mécano `40:98`, Communs `40:99`, Desktop `40:100`, Admin `40:101`. Images `34:1055`. Ignorer tout « Archive V1 · … (ne pas coder) ».
- Avant chaque écran : `get_design_context` + `get_screenshot` sur son nœud. Le code renvoyé est une **référence à adapter** à nos composants et jetons, **jamais à coller**.
- Cadres mobiles dessinés en 390 px : vérifier à 360 px (minimum) et 390 px.

### Écrans du parcours client
| Code | Écran | Nœud |
|---|---|---|
| C1 / D0 | Accueil mobile / desktop | 58:15 / 154:14 |
| C2a | Localisation GPS · recherche · confirmation | 66:437 · 198:1963 · 67:462 |
| C2b | Commune, quartier, repère | 68:604 |
| C3 | Ma panne · Choc ou accident | 69:653 · 69:868 |
| C4 | Résultats · sombre | 60:196 · 185:1669 |
| C4b / C4c | Chargement / Aucun mécano disponible | 73:942 / 75:1070 |
| D1 | Résultats desktop · fiche ouverte, numéro révélé | 156:274 · 158:542 |
| C5 | Fiche · sombre · plus disponible | 76:1211 · 185:1697 · 78:1304 |
| C5c | Appel intégré : sonne · en ligne · ne répond pas · terminé | 269:1820 · 269:1866 · 270:1846 · 270:1905 |
| C6 | Avez-vous pu le joindre ? · Non → Adama · Oui, il arrive | 82:1322 · 84:1394 · 86:1488 |
| C6b | Votre avis · Merci | 87:1576 · 88:1609 |
| C7 | Signaler · rempli · envoyé | 89:1612 · 210:1774 · 90:1660 |

### Composants (page Système)
Existent déjà (`app/Views/composants/`) : Bouton 53:148, Champ 53:230, Socle d'action 53:282, Barre de parcours 53:374, Statut 53:406, Note 53:446, Poinçon 49:166, Voyant d'état 49:144, Barre client 56:322 / desktop 56:337, Pied de page 56:636 / 56:670. Toast 53:447 = `toast()` JS. Tuiles véhicule 54:293, voyant 54:390, panneau 54:391, étiquette 54:451, ligne de choix 54:467, segment 54:499 = CSS dans `selecteurs.css` (démo sur `/systeme`), à transformer en composants PHP quand un écran les utilise.

À créer en composants : Étiquette Vérifié 49:175, Vous êtes ici 49:130, Repère carte 49:32, Légende-repère 49:254, Voyant de bord 48:214, Ligne de classement 54:251, Stepper 54:500, Avis 54:674, Bloc « Ce qu'il faut lui dire » 54:675, Ligne commune 56:734, Fond de carte 56:736.

## Règles métier du client (détail dans `docs/modele-donnees.html`)
Recherche RG-R1→R9 · appels RG-A1→A12 · issue de l'appel RG-I1→I8 · avis RG-AV1→AV8 · signalement RG-S1→S3 · client anonyme RG-C7 · identifiants RG-C8 · purge RG-P1/P2. **Citer le code RG dans le code** (commentaire là où la règle s'applique), dans les commits et les PR.

Décisions prises (ne pas rediscuter) :
- Trois façons d'appeler : appel intégré gratuit en premier, appel direct `tel:`, WhatsApp.
- Chaque appui crée un contact **avant** d'ouvrir l'appel (RG-A1). Avis seulement après un vrai contact (RG-AV1).
- Client sans compte. Position exacte effacée après 24 h (RG-P1).
- Véhicule = filtre strict (RG-R2) ; la panne ne fait que remonter les spécialistes (bonus 0,75, RG-R6) ; autre rive de la lagune = malus 1,3.
- Recherche depuis la position de disponibilité du mécano, dans son rayon ; paliers 2/5/10 km ; liste figée (RG-R7).
- Numéros d'urgence 185 / 180 / 170 dans `parametres`.

## Terminé, ça veut dire
- Fidèle au Figma : textes exacts, espacements, typo, couleurs par jetons ; **tous les états dessinés** (vide, chargement, erreur, succès, indisponible).
- Mobile 360 px **et** desktop 1440 px quand un écran desktop existe ; clair **et** sombre.
- Animations de `docs/mouvement-et-interactions.html`, avec version `prefers-reduced-motion`.
- Accessibilité : HTML sémantique, libellés sur tous les champs, focus visible, ordre de tabulation logique, contrastes, zones tactiles ≥ 44 px, `aria-live` sur les changements dynamiques.
- Performance Android d'entrée de gamme en 3G : pas de framework JS, images WebP dimensionnées, CSS/JS de page chargés seulement sur leur page.
- Règles métier respectées et citées ; requêtes préparées ; `php -l` et `vendor\bin\phpunit` verts.

## Méthode : une partie à la fois
1. Lire les nœuds Figma, les règles RG et le prototype d'animation concernés.
2. Présenter un plan court (fichiers, composants, routes, service, requêtes, questions). **Attendre l'accord.**
3. Coder sur une branche par partie, partie de `feature/socle` (ou de `main` quand le socle y est fusionné) : `ecran/c1-accueil`, `ecran/c2-position`…
4. Vérifier soi-même : `php -l`, `vendor\bin\phpunit`, la page dans le navigateur à 360 et 1440 px, clair et sombre, comparée à la capture Figma. Lister les écarts restants.
5. Montrer le résultat et les écarts. Après validation : commit `C1 · accueil : … (RG-…)`.

Découpage : 1 · C1 + D0 accueil · 2 · C2a + C2b position, jeton client (RG-C7) · 3 · C3 panne · 4 · C4/C4b/C4c + D1 résultats (RechercheService, carte) · 5 · C5 + D1 fiche · 6 · C6 contact direct et WhatsApp · 7 · C5c appel intégré · 8 · C6b avis · 9 · C7 signalement.

Git : jamais de commit sur `main`, jamais de force push. Push et PR seulement sur demande explicite. PR vers `main` avec captures mobile clair, mobile sombre, desktop ; une relecture d'un autre membre.
