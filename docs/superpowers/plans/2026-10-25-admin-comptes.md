# Gestion des comptes administrateur — plan

> **Implémentation :** exécuter ce plan dans le worktree dédié, avec tests écrits avant chaque changement de comportement. Pas de commit sans demande.

**Objectif :** utiliser le formulaire actuel de connexion des pros pour donner accès à l’administration aux admins, avec un compte principal et la gestion sécurisée des autres comptes.

**Architecture :** conserver `AuthService` et la session existants. Ajouter les règles de compte admin dans un service dédié, un middleware qui impose le changement du mot de passe temporaire, des contrôleurs/vues admin séparés, une migration additive et une commande de création initiale.

**Technologies :** PHP 8.2, PDO MySQL/MariaDB, PHPUnit, HTML/CSS et modules JavaScript existants.

**Décisions validées :**
- Compte principal : Esmel Rilay, `esmel@rilay.fr`.
- Le mot de passe temporaire est généré de façon cryptographique, stocké uniquement sous forme de hash et changé à la première connexion.
- Seul le principal peut créer, modifier ou désactiver les autres comptes admin. Chaque admin peut gérer ses propres nom, e-mail et mot de passe.
- Aucune suppression physique de compte admin ; les changements sont inscrits dans `journal_admin`, sans valeur de mot de passe.
- Le module réutilise le rail et les jetons admin existants. La migration n’exécute jamais le seed de démo et ne supprime aucune donnée.
- La branche est `ecran/admin-comptes`, basée sur le commit suivi de `ecran/animations` ; les modifications non enregistrées de l’autre worktree restent séparées.

## Tâches

1. **Migration et règles métier**
   - Ajouter `est_principal` et `mot_de_passe_a_changer` à la table `admins` dans le schéma frais.
   - Ajouter une migration/commande PHP additive et relançable qui ajoute les colonnes manquantes sans réinitialiser la base.
   - Écrire les tests d’intégration de `AdminComptesService` : compte principal unique, création admin, profil personnel, changement de mot de passe, désactivation protégée, journal sans secret. Observer l’échec avant l’implémentation.

2. **Connexion et changement initial du mot de passe**
   - Étendre `ExigerAdmin` afin qu’un compte avec changement requis ne voie que l’écran de changement, les ressources nécessaires et la déconnexion.
   - Ajouter le formulaire et le traitement CSRF, vérifier l’ancien mot de passe quand le changement n’est pas imposé, valider les règles déjà utilisées par le projet et effacer le drapeau après réussite.
   - Vérifier la redirection après la connexion admin et le refus d’accès direct aux autres pages tant que le mot de passe n’est pas changé.

3. **Module de gestion**
   - Ajouter les routes et contrôleurs pour `/admin/administrateurs`, `/admin/mon-compte` et les actions POST associées.
   - Afficher le lien de gestion des admins seulement au principal ; afficher le profil personnel à tous les admins.
   - Créer, modifier, activer et désactiver les comptes depuis des formulaires protégés CSRF. Le principal ne peut pas se désactiver lui-même ni être désigné par une action ordinaire.
   - Auditer les actions de compte sans consigner les mots de passe. Réutiliser les composants, jetons, libellés échappés et conventions de responsive existants.

4. **Initialisation et vérification**
   - Ajouter une commande d’initialisation idempotente qui crée ou prépare le compte principal demandé, avec mot de passe temporaire affiché une seule fois et changement forcé.
   - Documenter l’ordre des commandes : migration additive, puis initialisation ; ne pas lancer `composer db:demo`.
   - Exécuter les tests ciblés puis la suite PHPUnit, `php -l` sur les fichiers touchés et une vérification navigateur du parcours de connexion, du changement forcé et des pages de gestion. Rapporter explicitement tout échec préexistant.

## Critères de réussite

- La connexion admin passe par `/connexion` et ouvre `/admin` une fois le mot de passe temporaire remplacé.
- Aucun accès admin ordinaire n’est possible avant ce remplacement.
- Un admin standard ne peut pas appeler les actions de gestion des autres comptes, même en forgeant une requête.
- Le principal peut gérer les comptes, et chaque admin peut modifier son propre profil et son mot de passe.
- L’e-mail doit rester unique ; le principal ne peut pas être désactivé ; aucun mot de passe n’apparaît dans le journal.
- Migration relançable, et aucune ligne existante n’est supprimée.
