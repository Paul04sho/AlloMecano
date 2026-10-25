<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;

require dirname(__DIR__) . '/app/bootstrap.php';

$db = Database::instance();
$schema = (string) Env::get('DB_NOM', 'allomecano');
$colonnes = array_column($db->tous(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table',
    ['schema' => $schema, 'table' => 'admins']
), 'COLUMN_NAME');

if ($colonnes === []) {
    fwrite(STDERR, "La table admins est introuvable. Lancez d’abord l’installation initiale de la base.\n");
    exit(1);
}

$ajouts = [
    'est_principal' => "ALTER TABLE admins ADD COLUMN est_principal TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Peut gérer les autres comptes admin ; un seul compte principal'",
    'mot_de_passe_a_changer' => "ALTER TABLE admins ADD COLUMN mot_de_passe_a_changer TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Force le remplacement du mot de passe temporaire à la prochaine connexion'",
];

foreach ($ajouts as $colonne => $sql) {
    if (!in_array($colonne, $colonnes, true)) {
        $db->pdo()->exec($sql);
        echo "Colonne ajoutée : $colonne\n";
    }
}

$index = (int) $db->valeur(
    'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND INDEX_NAME = :index',
    ['schema' => $schema, 'table' => 'admins', 'index' => 'idx_admins_principal']
);
if ($index === 0) {
    $db->pdo()->exec('ALTER TABLE admins ADD INDEX idx_admins_principal (est_principal, actif)');
    echo "Index ajouté : idx_admins_principal\n";
}

echo "Migration admin terminée. Aucune ligne de compte n’a été supprimée.\n";
