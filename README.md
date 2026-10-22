# allomécano

> Un automobiliste en panne à Abidjan trouve en quelques secondes le mécanicien vérifié et disponible le plus proche, et l'appelle gratuitement depuis la plateforme.

![Page d'accueil du site](<design/Accueil · Desktop - AlloMécano.png>)

## Ce que fait la plateforme

- **Client, sans compte** : position GPS ou quartier → véhicule (voiture, moto, camion) → panne → liste et carte des mécanos disponibles, triés par distance, spécialistes en avant.
- **Contact** : appel intégré gratuit (navigateur à navigateur), appel direct, WhatsApp. Avis possible seulement après un vrai contact.
- **Confiance** : un mécano n'apparaît qu'après vérification de son identité (pièce, visage, diplôme ou attestation) par l'équipe.
- **Mécano** : inscription en étapes, interrupteur « Disponible », demandes reçues, avis, profil.
- **Admin** : file des dossiers à vérifier, signalements, journal de toutes les actions.

## Stack

| Couche | Choix |
|---|---|
| Serveur | PHP 8.2+, sans framework (`App\` en PSR-4, PDO, requêtes préparées) |
| Base | MySQL 8.0.16+ ou MariaDB 10.4+ (`database/schema.sql`, 34 tables) |
| Interface | HTML, CSS, JavaScript natif (modules ES), mobile d'abord, PWA |
| Carte | Leaflet + tuiles Protomaps hébergées chez nous |
| Appel intégré | WebRTC, signalisation PHP/MySQL, relais Cloudflare TURN |
| Notifications | Web Push (VAPID) et e-mail SMTP. Pas de SMS. |
| Hébergement | Systalink (Apache, `public/.htaccess`) |

Dépendances Composer : `minishlink/web-push`, `phpmailer/phpmailer`, `phpunit/phpunit` (dev).

## Démarrer en local

Prérequis : PHP 8.2+ avec `pdo_mysql`, `mbstring`, `curl`, `openssl`, `gd` ; Composer ; MySQL ou MariaDB.

**Sous Windows avec XAMPP** : suivre [`docs/installation-xampp.md`](docs/installation-xampp.md) pas à pas.

```bash
git clone https://github.com/Paul04sho/AlloMecano.git
cd AlloMecano
composer install
cp .env.example .env          # puis renseigner DB_UTILISATEUR / DB_MOT_DE_PASSE
composer db:demo              # crée la base + 8 mécanos fictifs (efface la base locale)
composer serve                # http://localhost:8000
```

- `http://localhost:8000/systeme` : tous les composants, en clair et en sombre (masqué en production).
- Comptes de démo : mot de passe `Demo2026!` pour tous. Admin : `admin@allomecano.test`. Mécanos : `+225 07 00 00 00 01` à `08` (numéros fictifs : mettre ceux de l’équipe avant une démo).
- `composer test` lance les tests.

## Arborescence

```
app/
  Core/          Router, Request, Response, View, Database, Session, Csrf, Validator, Format, Ulid, Env, Assets
  Controllers/   Client/ · Mecano/ · Admin/ · Communs/ · Api/
  Services/      Logique métier (recherche, contacts, dossier, push…), sans HTML
  Middleware/    CSRF, connexion mécano, connexion admin, limites de débit
  Views/         layouts/ · composants/ · client/ · mecano/ · admin/ · communs/
  routes.php     Toutes les URL, avec le code de l'écran Figma
public/          Seul dossier exposé : index.php, .htaccess, assets/, medias/, sw.js
database/        schema.sql, recherche.sql, seeds/, modele/ (générateur du schéma et de la doc)
scripts/         Installation de la base, sprite d'icônes, construction des assets, clés VAPID
storage/         Pièces d'identité et fichiers privés (hors du web), journaux
tests/           PHPUnit
docs/            modele-donnees.html (tables + 64 règles métier), organisation.md
```

## Références

- **Maquettes** : [Figma allomécano](https://www.figma.com/design/G6hAhkXOYCY3z6ugFyojoO/). Ne coder que les pages Système, Mobile · Client, Mobile · Mécano, Communs, Desktop, Admin. Tout ce qui est marqué « Archive V1 » est à ignorer.
- **Modèle de données et règles métier** : [`docs/modele-donnees.html`](docs/modele-donnees.html). Chaque règle a un code (RG-R6, RG-A5…) à citer dans les commits et les PR.
- **Organisation de l'équipe** : [`docs/organisation.md`](docs/organisation.md).

## Mise en ligne (Systalink)

1. Pointer le domaine sur `public/` (jamais sur la racine du projet).
2. `composer install --no-dev --optimize-autoloader`, puis `composer assets`.
3. `.env` avec `APP_ENV=prod`, `APP_DEBUG=0`, une `APP_CLE` neuve, les clés VAPID (`composer vapid`) et le SMTP.
4. `php scripts/installer-base.php` (sans `--demo`).
5. `storage/` en écriture pour PHP, hors du dossier public.

## Équipe

Dev_Eyes, trois développeurs : Paul Emmanuel, Axelle et [à compléter]. Contact : deveyesunlocked@gmail.com
