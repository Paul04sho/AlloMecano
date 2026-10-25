<?php

declare(strict_types=1);

use App\Services\AdminComptesService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$email = (string) ($argv[1] ?? 'esmel@rilay.fr');
$prenom = (string) ($argv[2] ?? 'Esmel');
$nom = (string) ($argv[3] ?? 'Rilay');
$resultat = AdminComptesService::depuisBase()->initialiserPrincipal($prenom, $nom, $email);

if (($resultat['deja_initialise'] ?? false) === true) {
    if (!$resultat['ok']) {
        fwrite(STDERR, "Un autre compte principal existe déjà. Aucun compte n’a été modifié.\n");
        exit(1);
    }
    echo "Le compte principal est déjà initialisé ; son mot de passe n’a pas été remplacé.\n";
    exit(0);
}

if (!$resultat['ok']) {
    fwrite(STDERR, "Initialisation impossible : vérifiez l’adresse e-mail et relancez la commande après la migration.\n");
    exit(1);
}

echo "Compte principal initialisé pour $prenom $nom ($email).\n";
echo "Mot de passe temporaire à transmettre une seule fois : " . $resultat['mot_de_passe_temporaire'] . "\n";
echo "Il devra être changé dès la première connexion.\n";
