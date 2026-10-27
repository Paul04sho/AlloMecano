# Mise en ligne · allomécano

Guide pour publier le site sur un hébergement de production (PHP 8.2, MySQL 8). Les commandes sont à lancer sur le serveur, en SSH, depuis le dossier du projet.

## 1. Prérequis

- PHP 8.2 avec les extensions `pdo_mysql` et `openssl`.
- Composer.
- MySQL 8 (Systalink, ou ton hébergeur).
- Un domaine avec **HTTPS** : sans HTTPS, la géolocalisation, le micro (appel intégré) et les notifications ne fonctionnent pas.

## 2. Récupérer le code

```bash
git clone https://github.com/Paul04sho/AlloMecano.git allomecano
cd allomecano
git checkout main
composer install --no-dev --optimize-autoloader
```

## 3. Fichier `.env`

Crée `.env` à la racine (il n'est jamais dans git). Valeurs à renseigner :

| Clé | Valeur de production |
|---|---|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_URL` | `https://ton-domaine.ci` |
| `APP_CLE` | une clé aléatoire de 32 caractères |
| `DB_HOTE`, `DB_PORT`, `DB_NOM` | celles de la base créée à l'étape 4 |
| `DB_UTILISATEUR`, `DB_MOT_DE_PASSE` | l'utilisateur MySQL, **pas root** |
| `SMTP_*` | le serveur d'envoi des e-mails |
| `VAPID_PUBLIQUE`, `VAPID_PRIVEE`, `VAPID_SUJET` | voir étape 6 |
| `TURN_CLE_ID`, `TURN_CLE_SECRET` | le service TURN de l'appel intégré |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | la connexion Google (facultatif au lancement) |
| `CARTE_SOURCE` | le chemin du fond de carte (étape 7) |

Ne mets jamais ces valeurs dans le code ni dans git.

## 4. Base de données

La base de production est créée **sans données de démo**. Le script `installer-base.php` refuse `--reinitialiser` en production, et le fichier `demo.sql` ne doit pas être chargé.

```bash
php scripts/installer-base.php
```

Cette commande charge seulement `database/schema.sql` : les 34 tables et les paramètres (`parametres`) : rayons 2/5/10 km, bonus 0,75, malus 1,3, numéros 185/180/170, etc.

Si tu préfères importer le fichier à la main (phpMyAdmin ou ligne de commande) :

```bash
mysql -h HOTE -u UTILISATEUR -p NOM_DE_LA_BASE < database/schema.sql
```

Le fichier `database/recherche.sql` est la requête de recherche de mécanos : il est déjà dans le code, rien à charger.

Vérifie ensuite que la base contient bien 34 tables et qu'aucun compte de démo n'existe.

## 5. Compte administrateur

Il n'existe pas de compte admin par défaut en production. Crée-le **une seule fois** et choisis un mot de passe fort. Le compte de démo (`admin@allomecano.test`) ne doit jamais être utilisé en production.

## 6. Clés Web Push

Les notifications des mécanos ont besoin de clés VAPID. À générer une seule fois sur le serveur :

```bash
composer vapid
```

Copie les valeurs dans `.env` (`VAPID_PUBLIQUE`, `VAPID_PRIVEE`, `VAPID_SUJET`). Ne change plus ces clés après : les mécanos devraient se réabonner.

## 7. Fond de carte

Le fond de carte (20 Mo) n'est pas dans git. Télécharge-le et place-le dans `public/assets/carte/abidjan.pmtiles` :

```bash
pmtiles extract https://build.protomaps.com/AAAAMMJJ.pmtiles public/assets/carte/abidjan.pmtiles --bbox=-4.35,5.20,-3.80,5.60 --maxzoom=15
```

Remplace `AAAAMMJJ` par une date de build récente (voir le [README](../README.md)).

## 8. Assets

```bash
composer assets
```

Ça construit les fichiers CSS et JS de production.

## 9. Dossiers et droits

- `storage/` et `public/medias/` : le serveur web doit pouvoir y écrire.
- `.env`, `storage/` : ne sont pas accessibles depuis le web (le site n'expose que `public/`).
- Pour les photos du dossier mécano, les fichiers privés restent dans `storage/`.

## 10. Tâches planifiées (cron)

Une tâche est à programmer (le serveur ne la lance pas tout seul) : la purge des données dont la durée de conservation est dépassée (RG-P1, RG-P2, RG-P4), **toutes les heures**.

```bash
# Toutes les heures
0 * * * * php /chemin/allomecano/scripts/purger.php
```

Sur Systalink, utilise leur planificateur de tâches à la place de `crontab`.

## 11. Premier test

1. Ouvre `https://ton-domaine.ci/` : la page d'accueil doit s'afficher sans erreur.
2. Ouvre `https://ton-domaine.ci/connexion` avec le compte admin.
3. Teste une recherche depuis un téléphone, en HTTPS.
4. Vérifie que `APP_DEBUG=0` : une page d'erreur ne doit pas afficher le détail technique.

## Ce qui n'est pas testé sur un vrai hébergement

- L'envoi des e-mails (`SMTP_*`).
- Les notifications push sur un vrai téléphone en HTTPS.
- L'appel intégré avec le service TURN.

À tester avant le 24 octobre 2026.
