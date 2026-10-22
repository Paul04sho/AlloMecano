<?php
/**
 * Génère la paire de clés VAPID des notifications push (une seule fois par environnement).
 * Copier les deux lignes affichées dans .env. Changer les clés invalide tous les abonnements push existants.
 * Usage : php scripts/generer-cles-vapid.php
 */
declare(strict_types=1);

use Minishlink\WebPush\VAPID;

require dirname(__DIR__) . '/vendor/autoload.php';

$cles = VAPID::createVapidKeys();
echo 'VAPID_PUBLIQUE=' . $cles['publicKey'] . "\n";
echo 'VAPID_PRIVEE=' . $cles['privateKey'] . "\n";
