# Organisation de l'équipe

Objectif : la plateforme en ligne **samedi 24 octobre 2026**, complète et finie. Tout ce qui est dessiné dans le Figma fait partie de la version présentée.

## Les trois chantiers

Chaque écran a un code Figma (C4, M1c, A2, X6…), repris dans `app/routes.php`, les branches, les commits et les PR.

| Chantier | Écrans | Contenu |
|---|---|---|
| **A · Parcours client** | C1 → C7, D0, D1 | Accueil, position (GPS ou quartier), panne, résultats liste et carte, fiche mécano, contact, avis, signalement |
| **B · Mécano et admin** | M0 → M5, A0 → A5 | Inscription en étapes et pièces du dossier, interrupteur de disponibilité, demandes, avis, profil ; vérification des dossiers, signalements, journal |
| **C · Appel, socle, communs** | C5c, M5, M1f, X1 → X7 | Appel intégré WebRTC et ses solutions de repli, notifications push et e-mail, connexion, mot de passe, pages légales, hors-ligne, PWA, tâches planifiées, mise en ligne |

Qui prend quoi : à décider ensemble. Le socle (`feature/socle`) est commun à tous et doit être fusionné en premier.

## Branches

- `main` : toujours déployable. Personne ne pousse directement dessus.
- Une branche par écran ou par fonctionnalité, partie de `main` à jour :
  `ecran/c4-resultats`, `ecran/m1-inscription`, `appel/signalisation`, `correctif/note-arrondi`.
- Branches courtes : une PR par jour au minimum. Plus une branche vit, plus la fusion fait mal.

## Commits

Au présent, en français, avec le code de l'écran et les règles métier touchées :

```
C4 · liste des résultats triée par distance (RG-R4, RG-R6)
M2 · l'interrupteur passe en « En intervention » pendant 2 h (RG-D4)
correctif · le numéro +225 refusait les espaces
```

## Pull requests

1. Ouvrir la PR vers `main` avec le modèle (il s'affiche tout seul) et des captures mobile clair, mobile sombre, ordinateur.
2. **Une relecture d'un autre membre** de l'équipe avant fusion. Le relecteur ouvre la page sur son téléphone.
3. Les contrôles automatiques (syntaxe PHP, schéma de la base, tests) doivent passer.
4. Fusion en « Squash and merge ». Supprimer la branche après.

## Terminé, ça veut dire

- Fidèle au Figma : espacements, typographie, couleurs par jetons (jamais de couleur en dur), tous les états dessinés (vide, chargement, erreur, succès).
- Mobile 360 px et ordinateur 1440 px. Clair et sombre.
- Animations du dossier Mouvement, avec la version « animations réduites ».
- Utilisable au clavier, focus visible, libellés sur tous les champs.
- Testé sur un vrai Android d'entrée de gamme, en 3G si possible.
- Règles métier respectées et citées. Aucune requête SQL hors requête préparée.

## Règles de code

- **PHP** : `declare(strict_types=1);`, une classe par fichier sous `App\`. Contrôleurs minces : ils lisent la requête, appellent un service, rendent une vue. La logique métier va dans `app/Services/`.
- **SQL** : toujours `Database::instance()` et des paramètres liés. Les requêtes préparées sont natives (pas d'émulation) : un même nom de paramètre ne peut pas apparaître deux fois dans une requête, utiliser `:lat1`, `:lat2`…
- **Identifiants publics** : les URL et l'API utilisent `public_id` (ULID) ou `slug`, jamais l'`id` numérique.
- **Vues** : tout affichage passe par `e()`. Réutiliser les composants (`composant('bouton', […])`) avant d'en créer un. Un nouveau composant va aussi sur `/systeme`.
- **CSS** : jetons de `tokens.css` uniquement. Un fichier par page dans `assets/css/pages/`, chargé seulement sur cette page.
- **JavaScript** : modules ES natifs, `assets/js/pages/<page>.js`. Les appels serveur passent par `api()` de `core/app.js` (jeton CSRF ajouté tout seul).
- **Textes** : français soigné, espaces fines avant `; : ! ?` (le helper `fr_typo()` s'en charge), montants avec `Format::fcfa()`, dates avec `Format::date()`.
- **Fichiers privés** : pièces d'identité dans `storage/pieces/`, jamais dans `public/`.

## Données de démo

`composer db:demo` recrée la base locale avec 8 mécanos fictifs autour de Cocody, Marcory, Yopougon, Plateau et Treichville. Mot de passe : `Demo2026!`. Avant la démo devant le jury, remplacer leurs numéros par ceux de l'équipe.
