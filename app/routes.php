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
use App\Controllers\Client\AppelController;
use App\Controllers\Client\AvisController;
use App\Controllers\Client\SignalementController;
use App\Controllers\Client\ContactController;
use App\Controllers\Client\FicheController;
use App\Controllers\Client\PanneController;
use App\Controllers\Client\PositionController;
use App\Controllers\Client\RechercheController;
use App\Controllers\Pro\DevenirController;
use App\Controllers\Pro\InscriptionController;
use App\Controllers\Communs\DevAppelController;
use App\Controllers\Communs\MediasDemoController;
use App\Controllers\Communs\SystemeController;
use App\Middleware\IdentifierClient;
use App\Middleware\VerifierCsrf;

$routeur->groupe('', [VerifierCsrf::class], function ($r) {

    // ----- Client (chantier A) -----
    $r->get('/', [AccueilController::class, 'index'], 'accueil');                 // C1 · D0
    $r->get('/pro/devenir', [DevenirController::class, 'index'], 'pro-devenir');     // M0 · Accueil mécano
    // ----- Inscription mécanicien (M1) -----
    $r->get('/pro/inscription/1', [InscriptionController::class, 'compte'], 'pro-inscription-compte');
    $r->post('/pro/inscription/1', [InscriptionController::class, 'continuer']);
    $r->post('/pro/inscription/brouillon', [InscriptionController::class, 'brouillon']);
    $r->get('/pro/inscription/google', [InscriptionController::class, 'google'], 'pro-inscription-google');
    $r->get('/pro/inscription/google/retour', [InscriptionController::class, 'retourGoogle'], 'pro-inscription-google-retour');
    $r->get('/pro/inscription/2', [InscriptionController::class, 'etapeMetier'], 'pro-inscription-metier');
    $r->post('/pro/inscription/2', [InscriptionController::class, 'enregistrerMetier']);
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
        $r->get('/appel/{id}', [AppelController::class, 'page'], 'appel');                        // C5c · appel intégré
        $r->get('/avis/{contact}', [AvisController::class, 'formulaire'], 'avis');                 // C6b · RG-AV1
        $r->post('/avis/{contact}', [AvisController::class, 'publier']);
        $r->get('/avis/{contact}/merci', [AvisController::class, 'merci']);
        $r->get('/signaler/{slug}', [SignalementController::class, 'formulaire'], 'signaler');     // C7 · RG-S1
        $r->post('/signaler/{slug}', [SignalementController::class, 'envoyer']);
        $r->get('/signalement/{id}', [SignalementController::class, 'envoye']);                    // C7 · envoyé
    });

    // ----- Mécano (chantier B) -----
    // M1a (compte/Google) et M1b (métier, raccord provisoire) sont activés ci-dessus ; M1c/M1d/M1f restent à faire.
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
    $r->get('/medias/demo/{fichier}', [MediasDemoController::class, 'servir']);                // portraits de démo si public/medias/demo n'est pas garni (dev)
    $r->get('/systeme/appel', [DevAppelController::class, 'page'], 'systeme-appel');          // mécano de test pour l'appel intégré
});

// ----- API JSON (appel intégré, recherche, push) : routes ajoutées par les chantiers -----
$routeur->groupe('/api', [VerifierCsrf::class, IdentifierClient::class], function ($r) {
    $r->post('/recherches', [RechercheController::class, 'lancerApi']);                          // C4b → C4
    $r->post('/contacts', [ContactController::class, 'creerApi']);                               // RG-A1 · D1 : numéro révélé
    $r->get('/contacts/{id}/retour', [ContactController::class, 'retourApi']);                   // C6 · feuille de retour d'appel
    $r->post('/contacts/{id}/absence', [ContactController::class, 'absence']);                   // RG-A12
    $r->post('/contacts/{id}/issue', [ContactController::class, 'issue']);                       // RG-I4
    $r->get('/appels/ice', [AppelController::class, 'ice']);                                     // serveurs STUN et relais TURN
    $r->get('/appels/{id}/signaux', [AppelController::class, 'signaux']);                        // RG-A8 : état + messages de l'autre côté
    $r->post('/appels/{id}/signal', [AppelController::class, 'signal']);
    $r->post('/appels/{id}/raccrocher', [AppelController::class, 'raccrocher']);
    $r->post('/appels/{id}/echec', [AppelController::class, 'echec']);                           // RG-A7
});
$routeur->groupe('/api', [VerifierCsrf::class], function ($r) {
    // Outil de développement (absent en production) : joue le mécano qui reçoit l'appel, en attendant M5
    $r->get('/dev/ice', [DevAppelController::class, 'ice']);
    $r->get('/dev/mecano/{id}/entrants', [DevAppelController::class, 'entrants']);
    $r->get('/dev/appel/{id}/signaux', [DevAppelController::class, 'signaux']);
    $r->post('/dev/appel/{id}/signal', [DevAppelController::class, 'signal']);
    $r->post('/dev/appel/{id}/issue', [DevAppelController::class, 'issue']);
    $r->post('/client/restaurer', [ClientController::class, 'restaurer']);                    // RG-C7
    $r->post('/client/effacer', [ClientController::class, 'effacer']);                        // C3 · « Effacer »
});
