<?php

declare(strict_types=1);

/**
 * Toutes les routes, une par écran Figma. Le code de l'écran est en commentaire.
 * Les routes commentées « à faire » sont réservées : chaque chantier les active dans sa branche.
 *
 * @var App\Core\Router $routeur
 */

use App\Controllers\Api\ClientController;
use App\Controllers\Client\AccueilController;
use App\Controllers\Client\ContactController;
use App\Controllers\Client\FicheController;
use App\Controllers\Client\PanneController;
use App\Controllers\Client\PositionController;
use App\Controllers\Client\RechercheController;
use App\Controllers\Communs\SystemeController;
use App\Middleware\IdentifierClient;
use App\Middleware\VerifierCsrf;

$routeur->groupe('', [VerifierCsrf::class], function ($r) {

    // ----- Client (chantier A) -----
    $r->get('/', [AccueilController::class, 'index'], 'accueil');                 // C1 · D0
    $r->groupe('', [IdentifierClient::class], function ($r) {                     // RG-C7 : client anonyme
        $r->get('/position', [PositionController::class, 'gps'], 'position');                   // C2a
        $r->post('/position', [PositionController::class, 'enregistrerGps']);
        $r->get('/position/confirmer', [PositionController::class, 'confirmer']);              // C2a · confirmation
        $r->post('/position/confirmer', [PositionController::class, 'enregistrerConfirmation']);
        $r->get('/position/quartier', [PositionController::class, 'quartier']);                // C2b
        $r->post('/position/quartier', [PositionController::class, 'enregistrerQuartier']);
        $r->get('/panne', [PanneController::class, 'formulaire'], 'panne');                       // C3
        $r->post('/panne', [PanneController::class, 'enregistrer']);
        $r->post('/panne/effacer', [PanneController::class, 'effacerContact']);
        $r->get('/recherche', [RechercheController::class, 'chargement'], 'recherche');           // C4b
        $r->post('/recherche', [RechercheController::class, 'lancerFormulaire']);
        $r->post('/recherche/rayon', [RechercheController::class, 'rayon']);                      // « Chercher jusqu'à 20 km »
        $r->post('/recherche/modifier', [RechercheController::class, 'modifier']);                 // D1 · résumé modifiable
        $r->get('/resultats/{id}', [RechercheController::class, 'resultats'], 'resultats');       // C4 · C4c · D1
        $r->get('/mecano/{slug}', [FicheController::class, 'page'], 'fiche');                     // C5 · ?r=<recherche>
        $r->get('/api/mecano/{slug}/fiche', [FicheController::class, 'panneau']);                 // D1 · panneau de 420 px
        $r->get('/api/mecano/{slug}/avis', [FicheController::class, 'avis']);                     // C5 · « Lire les 37 avis »
        $r->post('/contacter', [ContactController::class, 'creerFormulaire']);                    // RG-A1 : le contact d'abord, puis tel: ou WhatsApp
        $r->get('/contact/{id}', [ContactController::class, 'page'], 'contact');                  // C6 · « Avez-vous pu le joindre ? »
        $r->post('/contact/{id}/issue', [ContactController::class, 'issue']);
    });
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
$routeur->groupe('/api', [VerifierCsrf::class, IdentifierClient::class], function ($r) {
    $r->post('/recherches', [RechercheController::class, 'lancerApi']);                          // C4b → C4
    $r->post('/contacts', [ContactController::class, 'creerApi']);                               // RG-A1 · D1 : numéro révélé
    $r->get('/contacts/{id}/retour', [ContactController::class, 'retourApi']);                   // C6 · feuille de retour d'appel
    $r->post('/contacts/{id}/absence', [ContactController::class, 'absence']);                   // RG-A12
    $r->post('/contacts/{id}/issue', [ContactController::class, 'issue']);                       // RG-I4
});
$routeur->groupe('/api', [VerifierCsrf::class], function ($r) {
    $r->post('/client/restaurer', [ClientController::class, 'restaurer']);                    // RG-C7
    $r->post('/client/effacer', [ClientController::class, 'effacer']);                        // C3 · « Effacer »
});
