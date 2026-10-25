<?php

declare(strict_types=1);

/**
 * Toutes les routes, une par écran Figma. Le code de l'écran est en commentaire.
 * Les routes commentées « à faire » sont réservées : chaque chantier les active dans sa branche.
 *
 * @var App\Core\Router $routeur
 */

use App\Controllers\Admin\CompteController;
use App\Controllers\Admin\AdminComptesController;
use App\Controllers\Admin\DossierController;
use App\Controllers\Admin\SignalementAdminController;
use App\Controllers\Admin\StatistiquesController;
use App\Controllers\Admin\TableauController;
use App\Controllers\Api\ClientController;
use App\Controllers\Pro\ActiviteController;
use App\Controllers\Pro\AppelMecanoController;
use App\Controllers\Pro\DevenirController;
use App\Controllers\Pro\EspaceController;
use App\Controllers\Pro\InscriptionController;
use App\Controllers\Pro\ProfilController;
use App\Controllers\Client\AccueilController;
use App\Controllers\Client\AppelController;
use App\Controllers\Client\AvisController;
use App\Controllers\Client\SignalementController;
use App\Controllers\Client\ContactController;
use App\Controllers\Client\FicheController;
use App\Controllers\Client\PanneController;
use App\Controllers\Client\PositionController;
use App\Controllers\Client\RechercheController;
use App\Controllers\Communs\ConnexionController;
use App\Controllers\Communs\DevAppelController;
use App\Controllers\Communs\MotDePasseController;
use App\Controllers\Communs\PagesController;
use App\Controllers\Communs\SystemeController;
use App\Middleware\ExigerAdmin;
use App\Middleware\ExigerMecano;
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
        $r->get('/appel/{id}', [AppelController::class, 'page'], 'appel');                        // C5c · appel intégré
        $r->get('/avis/{contact}', [AvisController::class, 'formulaire'], 'avis');                 // C6b · RG-AV1
        $r->post('/avis/{contact}', [AvisController::class, 'publier']);
        $r->get('/avis/{contact}/merci', [AvisController::class, 'merci']);
        $r->get('/signaler/{slug}', [SignalementController::class, 'formulaire'], 'signaler');     // C7 · RG-S1
        $r->post('/signaler/{slug}', [SignalementController::class, 'envoyer']);
        $r->get('/signalement/{id}', [SignalementController::class, 'envoye']);                    // C7 · envoyé
    });

    // ----- Mécano (chantier B) -----
    $r->get('/pro/devenir', [DevenirController::class, 'page'], 'devenir');                    // M0 · Devenir mécano vérifié
    $r->get('/pro/inscription/compte', [InscriptionController::class, 'compte'], 'inscription');   // M1a · compte (public ; connecté : coordonnées)
    $r->post('/pro/inscription/compte', [InscriptionController::class, 'creer']);
    $r->groupe('/pro', [ExigerMecano::class], function ($r) {                                    // RG-C1 : mécanos connectés seulement
        $r->get('/inscription/metier', [InscriptionController::class, 'metier']);                 // M1b
        $r->post('/inscription/metier', [InscriptionController::class, 'enregistrerMetier']);
        $r->post('/api/brouillon', [InscriptionController::class, 'brouillon']);                  // RG-DO1 : chaque champ s'enregistre
        $r->get('/inscription/pieces', [InscriptionController::class, 'pieces']);                 // M1c
        $r->post('/inscription/pieces/{type}', [InscriptionController::class, 'deposerPiece']);   // RG-DO2
        $r->get('/mes-pieces/{type}', [InscriptionController::class, 'maPiece']);                 // sa propre photo, à lui seul
        $r->get('/inscription/envoi', [InscriptionController::class, 'envoi']);                   // M1d
        $r->post('/inscription/envoi', [InscriptionController::class, 'envoyer']);                // RG-DO3
        $r->get('/inscription/envoye', [InscriptionController::class, 'envoye']);                 // M1e
    });
    $r->groupe('/pro', [ExigerMecano::class], function ($r) {
        $r->get('', [EspaceController::class, 'page'], 'pro');                                  // M2 · M2a · M2b
        $r->post('/api/disponibilite', [EspaceController::class, 'disponibilite']);              // RG-D1, RG-D7
        $r->post('/api/position', [EspaceController::class, 'position']);                        // RG-D2
        $r->get('/apercu', [EspaceController::class, 'apercu']);                                 // « Voir ma fiche comme un client »
        $r->get('/appels/activer', [AppelMecanoController::class, 'activer'], 'pro-activer');      // M1f · Activer les appels
        $r->post('/api/push', [AppelMecanoController::class, 'abonner']);                          // RG-A2 : ce téléphone devient joignable
        $r->post('/api/push/etat', [AppelMecanoController::class, 'abonne']);
        $r->post('/api/push/essai', [AppelMecanoController::class, 'essai']);                      // essai de sonnerie
        $r->post('/api/appels/pret', [AppelMecanoController::class, 'pret']);
        $r->get('/api/appels/entrants', [AppelMecanoController::class, 'entrants']);               // RG-A2, RG-A5 : ce qui sonne chez moi
        $r->get('/api/appels/ice', [AppelMecanoController::class, 'ice']);                         // RG-A9
        $r->get('/api/appels/{id}/signaux', [AppelMecanoController::class, 'signaux']);            // RG-A8
        $r->post('/api/appels/{id}/signal', [AppelMecanoController::class, 'signal']);
        $r->post('/api/appels/{id}/issue', [AppelMecanoController::class, 'issue']);               // RG-I2, RG-D4
        $r->get('/appel/{id}', [AppelMecanoController::class, 'page'], 'pro-appel');               // M5 · appel entrant · en appel · Vous y allez ?
        $r->get('/profil', [ProfilController::class, 'page'], 'pro-profil');                       // M4 · Mon profil
        $r->get('/profil/supprimer', [ProfilController::class, 'supprimer']);                      // RG-C6 : on tape son nom
        $r->post('/profil/supprimer', [ProfilController::class, 'supprimer']);
        $r->get('/profil/{section}', [ProfilController::class, 'modifier']);                       // le crayon d'une section
        $r->post('/profil/{section}', [ProfilController::class, 'enregistrer']);                   // RG-DO7 : nom ou portrait changé
        $r->get('/demandes', [ActiviteController::class, 'demandes'], 'pro-demandes');             // M3 · Demandes
        $r->get('/avis', [ActiviteController::class, 'avis'], 'pro-avis');                         // M3 · Avis
        $r->post('/avis/{id}/repondre', [ActiviteController::class, 'repondre']);                  // RG-AV8
        $r->post('/avis/{id}/signaler', [ActiviteController::class, 'signaler']);                  // RG-AV4
    });

    // ----- Admin (chantier B) -----
    $r->groupe('/admin', [ExigerAdmin::class], function ($r) {                                  // RG-C5 : admins connectés seulement
        $r->get('', [TableauController::class, 'tableau'], 'admin');                              // A0
        $r->get('/dossiers', [TableauController::class, 'dossiers'], 'admin-dossiers');           // A1
        $r->get('/dossiers/{id}/portrait', [TableauController::class, 'portrait']);               // vignette de la file
        $r->get('/dossiers/{id}', [DossierController::class, 'voir'], 'admin-dossier');           // A2
        $r->get('/dossiers/{id}/pieces/{type}', [DossierController::class, 'piece']);              // RG-P6 : pièce filigranée, consultation journalisée
        $r->get('/dossiers/{id}/apercu', [DossierController::class, 'apercu']);                    // aperçu exact du message
        $r->post('/dossiers/{id}/decision', [DossierController::class, 'decider']);                // RG-DO4 à RG-DO6
        $r->get('/comptes', [CompteController::class, 'liste'], 'admin-comptes');                  // A3
        $r->get('/comptes/{id}/apercu', [CompteController::class, 'apercu']);                      // aperçu du message de suspension
        $r->post('/comptes/{id}/suspendre', [CompteController::class, 'suspendre']);               // RG-D6
        $r->post('/comptes/{id}/lever', [CompteController::class, 'lever']);
        $r->post('/comptes/{id}/supprimer', [CompteController::class, 'supprimer']);               // RG-P5
        $r->post('/clients/{ref}/numero', [CompteController::class, 'numero']);                    // RG-C5 : l'affichage d'un numéro de client est journalisé
        $r->get('/signalements', [SignalementAdminController::class, 'liste'], 'admin-signalements');   // A4
        $r->get('/signalements/{id}/photo', [SignalementAdminController::class, 'photo']);              // RG-S3 : photo jointe, consultation journalisée
        $r->post('/signalements/{id}/classer', [SignalementAdminController::class, 'classer']);         // RG-S2 : sans suite, note obligatoire
        $r->get('/statistiques', [StatistiquesController::class, 'voir'], 'admin-statistiques');       // A5
        $r->get('/statistiques/export', [StatistiquesController::class, 'exporter']);                  // A5 · CSV, journalisé
        $r->get('/administrateurs', [AdminComptesController::class, 'liste'], 'admin-administrateurs');
        $r->post('/administrateurs/creer', [AdminComptesController::class, 'creer']);
        $r->post('/administrateurs/modifier', [AdminComptesController::class, 'modifier']);
        $r->post('/administrateurs/actif', [AdminComptesController::class, 'changerActif']);
        $r->post('/administrateurs/mot-de-passe', [AdminComptesController::class, 'reinitialiserMotDePasse']);
        $r->get('/mon-compte', [AdminComptesController::class, 'monCompte'], 'admin-mon-compte');
        $r->post('/mon-compte', [AdminComptesController::class, 'enregistrerMonCompte']);
        $r->get('/mon-compte/mot-de-passe', [AdminComptesController::class, 'motDePasse'], 'admin-mot-de-passe');
        $r->post('/mon-compte/mot-de-passe', [AdminComptesController::class, 'enregistrerMotDePasse']);
    });

    // ----- Communs (chantier C) -----
    $r->get('/connexion', [ConnexionController::class, 'formulaire'], 'connexion');           // X1
    $r->post('/connexion', [ConnexionController::class, 'connecter']);
    $r->post('/deconnexion', [ConnexionController::class, 'deconnecter']);
    $r->get('/connexion/google', [ConnexionController::class, 'google']);
    $r->get('/connexion/google/retour', [ConnexionController::class, 'googleRetour']);
    $r->get('/mot-de-passe', [MotDePasseController::class, 'email'], 'mot-de-passe');         // X2 · 1
    $r->post('/mot-de-passe', [MotDePasseController::class, 'envoyer']);
    $r->get('/mot-de-passe/envoye', [MotDePasseController::class, 'envoye']);                  // X2 · 2
    $r->post('/mot-de-passe/renvoyer', [MotDePasseController::class, 'renvoyer']);
    $r->get('/mot-de-passe/nouveau/{jeton}', [MotDePasseController::class, 'nouveau']);        // X2 · 3 ou « Lien expiré »
    $r->post('/mot-de-passe/nouveau/{jeton}', [MotDePasseController::class, 'enregistrer']);
    $r->post('/mot-de-passe/nouveau/{jeton}/renvoyer', [MotDePasseController::class, 'renvoyerDepuisLien']);
    $r->get('/mot-de-passe/termine', [MotDePasseController::class, 'termine']);                // X2 · 4
    $r->get('/a-propos', [PagesController::class, 'aPropos'], 'a-propos');                    // X3
    $r->get('/contact', [PagesController::class, 'contact'], 'contact-page');                 // X4 · RG-CT1
    $r->post('/contact', [PagesController::class, 'envoyerContact']);
    $r->get('/cgu', [PagesController::class, 'cgu'], 'cgu');                                  // X5
    $r->get('/cgu.pdf', [PagesController::class, 'cguPdf']);
    $r->get('/confidentialite', [PagesController::class, 'confidentialite'], 'confidentialite');
    $r->get('/confidentialite.pdf', [PagesController::class, 'confidentialitePdf']);
    $r->get('/hors-ligne', [PagesController::class, 'horsLigne'], 'hors-ligne');              // X7

    // ----- Design system (dev uniquement) -----
    $r->get('/systeme', [SystemeController::class, 'index'], 'systeme');
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
    $r->get('/ping', [PagesController::class, 'ping']);                                       // X7 : le réseau est-il revenu ?
    $r->get('/dev/ice', [DevAppelController::class, 'ice']);
    $r->get('/dev/mecano/{id}/entrants', [DevAppelController::class, 'entrants']);
    $r->get('/dev/appel/{id}/signaux', [DevAppelController::class, 'signaux']);
    $r->post('/dev/appel/{id}/signal', [DevAppelController::class, 'signal']);
    $r->post('/dev/appel/{id}/issue', [DevAppelController::class, 'issue']);
    $r->post('/client/restaurer', [ClientController::class, 'restaurer']);                    // RG-C7
    $r->post('/client/effacer', [ClientController::class, 'effacer']);                        // C3 · « Effacer »
});
