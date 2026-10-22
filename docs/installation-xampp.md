# Installer allomécano en local avec XAMPP (Windows)

Compter 15 minutes. À faire une seule fois par poste.

## 1. Prérequis

| Outil | Version | Où |
|---|---|---|
| XAMPP | avec **PHP 8.2 ou plus** (MariaDB 10.4 inclus) | apachefriends.org |
| Composer | dernière version | getcomposer.org → `Composer-Setup.exe` |
| Git | dernière version | git-scm.com |

Pendant l'installation de Composer, choisir `C:\xampp\php\php.exe` comme PHP et cocher « Add to PATH ».
Vérifier dans un nouveau terminal :

```bat
php -v          :: doit afficher 8.2 ou plus
composer -V
git --version
```

## 2. Activer les extensions PHP

Ouvrir `C:\xampp\php\php.ini` (XAMPP → Apache → Config → PHP (php.ini)) et vérifier que ces lignes **n'ont pas** de `;` devant :

```ini
extension=curl
extension=gd
extension=mbstring
extension=openssl
extension=pdo_mysql
```

`extension=gmp` et `extension=intl` sont conseillées (notifications push plus rapides). Enregistrer, puis redémarrer Apache s'il tourne.

## 3. Récupérer le projet

```bat
cd C:\xampp\htdocs
git clone https://github.com/Paul04sho/AlloMecano.git allomecano
cd allomecano
git checkout feature/socle
composer install
copy .env.example .env
```

Tant que le socle n'est pas fusionné, il faut être sur `feature/socle` : `main` ne contient encore que le README.

Le `.env` d'origine correspond déjà à XAMPP (utilisateur `root`, mot de passe vide). Si ton MySQL a un mot de passe, renseigne `DB_MOT_DE_PASSE`.

## 4. Créer la base

Dans le panneau XAMPP, démarrer **MySQL**. Puis, au choix :

**A. En une commande (recommandé)**

```bat
composer db:demo
```

Résultat attendu :

```
Chargé : schema.sql
Chargé : demo.sql
Base « allomecano » prête : 34 tables.
```

`composer db:demo` **efface et recrée** la base locale `allomecano`. Pour la base sans les mécanos de démo : `php scripts/installer-base.php`.

**B. Avec phpMyAdmin** (http://localhost/phpmyadmin)

1. Nouvelle base : nom `allomecano`, interclassement `utf8mb4_unicode_ci`.
2. Onglet Importer → `database/schema.sql` → Exécuter.
3. Onglet Importer → `database/seeds/demo.sql` → Exécuter.

## 5. Lancer le site

**A. Le plus simple : le serveur intégré de PHP**

```bat
composer serve
```

Ouvrir http://localhost:8000. Apache n'est pas nécessaire, seulement MySQL.

**B. Avec Apache de XAMPP** (plus proche de l'hébergement Systalink)

Le site doit être servi depuis le dossier `public/`, à la racine d'un domaine. `http://localhost/allomecano/public` **ne marche pas**.

1. Ajouter à la fin de `C:\xampp\apache\conf\extra\httpd-vhosts.conf` :

   ```apache
   <VirtualHost *:80>
       ServerName allomecano.test
       DocumentRoot "C:/xampp/htdocs/allomecano/public"
       <Directory "C:/xampp/htdocs/allomecano/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

2. Ouvrir le Bloc-notes **en administrateur**, puis `C:\Windows\System32\drivers\etc\hosts`, et ajouter :

   ```
   127.0.0.1  allomecano.test
   ```

3. Dans `.env` : `APP_URL=http://allomecano.test`
4. Redémarrer Apache, ouvrir http://allomecano.test

## 6. Vérifier que tout marche

| Page | Ce qu'on doit voir |
|---|---|
| `/` | L'accueil provisoire |
| `/systeme` | Tous les composants. Tester le bouton clair / sombre |
| `/nimporte-quoi` | La page « Route barrée. » |

Tests : `vendor\bin\phpunit` → `OK (13 tests, 28 assertions)`.

Comptes de démo : mot de passe `Demo2026!`. Admin : `admin@allomecano.test`. Mécanos : `+225 07 00 00 00 01` à `08`.

## Problèmes fréquents

| Message | Solution |
|---|---|
| `could not find driver` | `extension=pdo_mysql` désactivée dans php.ini (étape 2) |
| `SQLSTATE[HY000] [2002]` | MySQL n'est pas démarré dans XAMPP |
| `Access denied for user 'root'` | Mot de passe MySQL à mettre dans `DB_MOT_DE_PASSE` |
| `Your requirements could not be resolved… ext-…` | Une extension manque (étape 2) |
| `'php' n'est pas reconnu` | Ajouter `C:\xampp\php` au PATH, puis rouvrir le terminal |
| Port 3306 ou 80 déjà pris | Changer le port dans XAMPP, puis `DB_PORT` dans `.env` |
| Page blanche ou 404 avec Apache | `DocumentRoot` doit pointer sur `public/` et `AllowOverride All` doit être présent |
