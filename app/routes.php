<?php

declare(strict_types=1);

/**
 * Toutes les routes, une par écran Figma. Le code de l'écran est en commentaire.
 * Les routes commentées « à faire » sont réservées : chaque chantier les active dans sa branche.
 *
 * @var App\Core\Router $routeur
 */

use App\Controllers\Client\PositionController;
use App\Controllers\Communs\PageController;
use App\Controllers\Communs\SystemeController;
use App\Middleware\VerifierCsrf;

$routeur->groupe('', [VerifierCsrf::class], function ($r) {

    // ----- Client (chantier A) -----
    $r->get('/', [PageController::class, 'accueil'], 'accueil');                 // C1 · D0
    $r->get('/position', [PositionController::class, 'index'], 'position'); // C2a · Localisation
    // $r->get('/position/quartier', …)   C2b · Commune, quartier  (à faire)
    // $r->get('/panne', …)               C3 · Ma panne             (à faire)
    // $r->get('/resultats/{id}', …)      C4 · C4b · C4c · D1       (à faire)
    // $r->get('/mecano/{slug}', …)       C5                        (à faire)
    // $r->get('/contact/{id}', …)        C5c · C6                  (à faire)
    // $r->get('/avis/{id}', …)           C6b                       (à faire)
    // $r->get('/signaler/{slug}', …)     C7                        (à faire)

    // ----- Mécano (chantier B) -----
    // $r->get('/pro/devenir', …)         M0                        (à faire)
    // $r->get('/pro/inscription/{etape}', …)  M1a → M1d · M1f     (à faire)
    // $r->get('/pro', …)                 M2 · M2a · M2b            (à faire)
    // $r->get('/pro/demandes', …)        M3                        (à faire)
    // $r->get('/pro/avis', …)            M3 · Avis                 (à faire)
    // $r->get('/pro/profil', …)          M4                        (à faire)

    // ----- Admin (chantier B) -----
    // $r->get('/admin', …)               A0 … A5                   (à faire)

    // ----- Communs (chantier C) -----
    // $r->get('/connexion', …)           X1                        (à faire)
    // $r->get('/mot-de-passe/…', …)      X2                        (à faire)
    // $r->get('/a-propos', …)            X3                        (à faire)
    // $r->get('/contact', …)             X4                        (à faire)
    // $r->get('/cgu', …) /confidentialite X5                       (à faire)
    // $r->get('/hors-ligne', …)          X7                        (à faire)

    // ----- Design system (dev uniquement) -----
    $r->get('/systeme', [SystemeController::class, 'index'], 'systeme');
});

// ----- API JSON (appel intégré, recherche, push) : routes ajoutées par les chantiers -----
