<?php
/**
 * Crée la base et charge le schéma + les données de départ (+ démo avec --demo).
 * Usage : php scripts/installer-base.php [--demo] [--reinitialiser]
 * ATTENTION : --reinitialiser supprime toutes les tables. Jamais en production.
 */
declare(strict_types=1);

use App\Core\Env;

require dirname(__DIR__) . '/vendor/autoload.php';
Env::charger(dirname(__DIR__) . '/.env');

$options = array_slice($argv, 1);
$nom = (string) Env::get('DB_NOM', 'allomecano');
if (in_array('--reinitialiser', $options, true) && Env::enProduction()) {
    fwrite(STDERR, "Refusé : --reinitialiser est interdit en production.\n");
    exit(1);
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', Env::get('DB_HOTE', '127.0.0.1'), Env::get('DB_PORT', '3306')),
    (string) Env::get('DB_UTILISATEUR', 'root'),
    (string) Env::get('DB_MOT_DE_PASSE', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

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
$tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($nom))->fetchColumn();
echo "Base « $nom » prête : $tables tables.\n";
