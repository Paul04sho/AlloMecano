<?php
/**
 * Crée la base et charge le schéma + les données de départ (+ démo avec --demo).
 * Usage : php scripts/installer-base.php [--demo] [--medias-demo] [--reinitialiser]
 * --medias-demo seul recopie uniquement les photos de démonstration, sans toucher à MySQL.
 * ATTENTION : --reinitialiser supprime toutes les tables. Jamais en production.
 */
declare(strict_types=1);

use App\Core\Env;

require dirname(__DIR__) . '/vendor/autoload.php';
Env::charger(dirname(__DIR__) . '/.env');

$options = array_slice($argv, 1);

/**
 * Copie les photos de démonstration dans public/medias/demo.
 * Ce dossier est ignoré par Git : il doit être matérialisé localement depuis les fichiers suivis.
 */
$copierMediasDemo = static function (): int {
    $source = dirname(__DIR__) . '/database/seeds/medias';
    $cible = dirname(__DIR__) . '/public/medias/demo';
    $fichiers = glob($source . '/*.webp') ?: [];

    if ($fichiers === []) {
        throw new RuntimeException("Aucune photo de démonstration trouvée dans : $source");
    }
    if (!is_dir($cible) && !mkdir($cible, 0775, true) && !is_dir($cible)) {
        throw new RuntimeException("Impossible de créer le dossier de destination : $cible");
    }

    $copies = 0;
    foreach ($fichiers as $fichier) {
        if (!copy($fichier, $cible . '/' . basename($fichier))) {
            throw new RuntimeException("Impossible de copier la photo : " . basename($fichier));
        }
        $copies++;
    }
    return $copies;
};

// Mode de réparation sûr : synchroniser uniquement les images, sans connexion ni requête SQL.
// Ne pas combiner avec --demo ou --reinitialiser, qui sont des opérations de base de données.
if (
    in_array('--medias-demo', $options, true)
    && !in_array('--demo', $options, true)
    && !in_array('--reinitialiser', $options, true)
) {
    if (Env::enProduction()) {
        fwrite(STDERR, "Refusé : les photos de démonstration ne doivent pas être copiées en production.\\n");
        exit(1);
    }
    try {
        $nombre = $copierMediasDemo();
        echo "Photos de démonstration copiées : $nombre\\n";
        exit(0);
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\\n");
        exit(1);
    }
}
$nom = (string) Env::get('DB_NOM', 'allomecano');
if (in_array('--reinitialiser', $options, true) && Env::enProduction()) {
    fwrite(STDERR, "Refusé : --reinitialiser est interdit en production.\n");
    exit(1);
}

$hote = (string) Env::get('DB_HOTE', '127.0.0.1');
$port = (string) Env::get('DB_PORT', '3306');
$utilisateur = (string) Env::get('DB_UTILISATEUR', 'root');
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $hote, $port),
        $utilisateur,
        (string) Env::get('DB_MOT_DE_PASSE', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    $aide = match (true) {
        str_contains($e->getMessage(), '[2002]') => "MySQL ne répond pas sur $hote:$port. Est-il démarré (panneau XAMPP) ? Le port est-il le bon (DB_PORT) ?",
        str_contains($e->getMessage(), '[1045]') => "Identifiants refusés pour « $utilisateur ». Vérifier DB_UTILISATEUR et DB_MOT_DE_PASSE dans .env.",
        str_contains($e->getMessage(), 'could not find driver') => "Extension pdo_mysql absente : la décommenter dans php.ini (extension=pdo_mysql).",
        default => $e->getMessage(),
    };
    fwrite(STDERR, "Connexion impossible. $aide\n");
    exit(1);
}

if (in_array('--reinitialiser', $options, true)) {
    $pdo->exec("DROP DATABASE IF EXISTS `$nom`");
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$nom` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$nom`");
$pdo->exec("SET time_zone = '+00:00'");

$fichiers = [dirname(__DIR__) . '/database/schema.sql'];
if (in_array('--demo', $options, true)) {
    $fichiers[] = dirname(__DIR__) . '/database/seeds/demo.sql';
}
foreach ($fichiers as $fichier) {
    if (!is_file($fichier)) {
        echo "Ignoré (absent) : $fichier\n";
        continue;
    }
    $pdo->exec((string) file_get_contents($fichier));
    echo 'Chargé : ' . basename($fichier) . "\n";
}
// Démo : photos (portraits, ateliers) copiées dans public/medias/demo (dossier hors git).
if (in_array('--demo', $options, true)) {
    echo 'Photos de démo copiées : ' . $copierMediasDemo() . "\n";
}
$tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($nom))->fetchColumn();
echo "Base « $nom » prête : $tables tables.\n";
