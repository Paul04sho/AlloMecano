<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\DemandesMecanoService;
use App\Services\InscriptionService;
use App\Services\MecanoService;
use App\Services\PositionService;
use App\Services\ProfilMecanoService;

/**
 * M4 · Mon profil (Figma 120:1169) : la fiche telle que les clients la voient, une modification par section (le crayon), le mot de passe, le thème, la déconnexion
 * et la suppression du compte (RG-C6). Changer de nom ou de portrait renvoie le dossier en vérification (RG-DO7). Réservé aux dossiers validés.
 */
final class ProfilController
{
    private const CSS = ['css/pages/pro.css', 'css/pages/pro-tableau.css', 'css/pages/pro-profil.css'];
    private const SECTIONS = [
        'identite' => 'Photo et identité', 'specialites' => 'Spécialités', 'zone' => 'Zone et rayon', 'tarifs' => 'Tarifs indicatifs', 'presentation' => 'Présentation',
        'atelier' => 'Photos de l’atelier', 'contacts' => 'Téléphone et WhatsApp', 'mot-de-passe' => 'Mot de passe',
    ];

    /** GET /pro/profil */
    public function page(Request $requete): Response
    {
        $id = $this->mecano();
        if ($id === null) {
            return Response::redirection('/pro');
        }
        $f = ProfilMecanoService::depuisBase()->fiche($id);
        return Response::html(View::rendre('pro/profil', [
            'titre' => 'Mon profil', 'onglet' => 'profil', 'demandes' => DemandesMecanoService::depuisBase()->aRappeler($id), 'sonnerie' => true,
            'css' => [...self::CSS, 'css/composants/carte.css', 'vendor/leaflet-1.9.4/leaflet.css'],
            'js' => ['vendor/leaflet-1.9.4/leaflet.js', 'vendor/protomaps-leaflet-5.1.0/protomaps-leaflet.js', 'js/pages/pro-profil.js'],
            'f' => $f, 'max' => ProfilMecanoService::PHOTOS_ATELIER_MAX,
        ], 'layouts/pro'));
    }

    /** GET /pro/profil/{section} */
    public function modifier(Request $requete): Response
    {
        $id = $this->mecano();
        $section = (string) $requete->param('section');
        if ($id === null) {
            return Response::redirection('/pro');
        }
        if (!isset(self::SECTIONS[$section])) {
            throw HttpException::introuvable();
        }
        return $this->formulaire($id, $section, [], []);
    }

    /** POST /pro/profil/{section} */
    public function enregistrer(Request $requete): Response
    {
        $id = $this->mecano();
        $section = (string) $requete->param('section');
        if ($id === null) {
            return Response::redirection('/pro');
        }
        if (!isset(self::SECTIONS[$section])) {
            throw HttpException::introuvable();
        }
        $service = ProfilMecanoService::depuisBase();
        $saisie = $requete->tout();
        $erreurs = [];
        $message = 'Votre fiche est à jour.';
        switch ($section) {
            case 'identite':
                $r = $service->modifierIdentite($id, (string) ($saisie['prenom'] ?? ''), (string) ($saisie['nom'] ?? ''), $requete->fichier('portrait'));
                if ($r['ok']) {
                    Session::flash('message', $r['renvoye'] ? 'Votre dossier repasse en vérification. Réponse sous 48 h.' : 'Votre fiche est à jour.');
                    return Response::redirection($r['renvoye'] ? '/pro' : '/pro/profil');
                }
                $erreurs = $r['erreurs'];
                break;
            case 'specialites':
                $erreurs = InscriptionService::depuisBase()->enregistrerMetier($id, ['specialites' => (array) ($saisie['specialites'] ?? []), 'vehicules' => (string) ($saisie['vehicules'] ?? ''), 'annees' => $saisie['annees'] ?? 0])['erreurs'];
                if ($erreurs === [] && (array) ($saisie['specialites'] ?? []) === []) {
                    $erreurs['specialites'] = 'Choisissez au moins une spécialité.';
                }
                break;
            case 'zone':
                $erreurs = InscriptionService::depuisBase()->enregistrerMetier($id, ['quartier' => (string) ($saisie['quartier'] ?? ''), 'rayon' => $saisie['rayon'] ?? 0])['erreurs'];
                if ($erreurs === [] && trim((string) ($saisie['quartier'] ?? '')) === '') {
                    $erreurs['quartier'] = 'Indiquez le quartier de votre atelier.';
                }
                break;
            case 'tarifs':
                $champs = ['tarif_diagnostic' => $saisie['tarif_diagnostic'] ?? '', 'deplacement_inclus' => isset($saisie['deplacement_inclus'])];
                if (!isset($saisie['deplacement_inclus'])) {
                    $champs['tarif_deplacement'] = $saisie['tarif_deplacement'] ?? '';
                }
                $erreurs = InscriptionService::depuisBase()->enregistrerMetier($id, $champs)['erreurs'];
                break;
            case 'presentation':
                $erreurs = InscriptionService::depuisBase()->enregistrerMetier($id, ['presentation' => (string) ($saisie['presentation'] ?? '')])['erreurs'];
                break;
            case 'atelier':
                if ($requete->fichier('photo') !== null) {
                    $r = $service->ajouterPhotoAtelier($id, $requete->fichier('photo'));
                    if (!$r['ok']) {
                        $erreurs['photo'] = $r['erreur'];
                    } else {
                        Session::flash('message', 'Photo ajoutée à votre fiche.');
                        return Response::redirection('/pro/profil/atelier');
                    }
                } elseif (isset($saisie['retirer'])) {
                    $service->retirerPhotoAtelier($id, (string) $saisie['retirer']);
                    Session::flash('message', 'Photo retirée de votre fiche.');
                    return Response::redirection('/pro/profil/atelier');
                } else {
                    $erreurs['photo'] = 'Choisissez une photo.';
                }
                break;
            case 'contacts':
                $erreurs = $service->modifierContacts($id, (string) ($saisie['telephone'] ?? ''), (string) ($saisie['whatsapp'] ?? ''))['erreurs'] ?? [];
                break;
            case 'mot-de-passe':
                $erreurs = $service->changerMotDePasse($id, (string) ($saisie['actuel'] ?? ''), (string) ($saisie['nouveau'] ?? ''))['erreurs'] ?? [];
                $message = 'Votre mot de passe est changé.';
                break;
        }
        if ($erreurs === []) {
            Session::flash('message', $message);
            return Response::redirection('/pro/profil');
        }
        return $this->formulaire($id, $section, $saisie, $erreurs);
    }

    /** GET /pro/profil/supprimer · POST : « Il faudra taper votre nom pour confirmer » (RG-C6). */
    public function supprimer(Request $requete): Response
    {
        $id = $this->mecano();
        if ($id === null) {
            return Response::redirection('/pro');
        }
        $erreur = null;
        if ($requete->methode === 'POST') {
            $r = ProfilMecanoService::depuisBase()->supprimer($id, (string) $requete->input('nom', ''));
            if ($r['ok']) {
                Session::detruire();
                Session::demarrer();
                Session::flash('message', 'Votre compte est supprimé.');
                return Response::redirection('/');
            }
            $erreur = $r['erreur'];
        }
        $f = ProfilMecanoService::depuisBase()->fiche($id);
        return Response::html(View::rendre('pro/profil-supprimer', [
            'titre' => 'Supprimer mon compte', 'sansBarre' => true, 'sansOnglets' => true, 'css' => self::CSS, 'f' => $f, 'erreur' => $erreur, 'saisie' => (string) $requete->input('nom', ''),
        ], 'layouts/pro'));
    }

    // ---------- Aides ----------

    /** @param array<string, mixed> $saisie @param array<string, string> $erreurs */
    private function formulaire(int $id, string $section, array $saisie, array $erreurs): Response
    {
        $inscription = InscriptionService::depuisBase();
        $f = ProfilMecanoService::depuisBase()->fiche($id);
        $donnees = ['titre' => self::SECTIONS[$section], 'sansBarre' => true, 'sansOnglets' => true, 'css' => self::CSS, 'section' => $section, 'libelle' => self::SECTIONS[$section],
            'f' => $f, 'b' => $inscription->brouillon($id), 'saisie' => $saisie, 'erreurs' => $erreurs];
        if ($section === 'specialites') {
            $donnees['specialites'] = $inscription->specialites();
        }
        if ($section === 'zone') {
            $donnees['quartiers'] = PositionService::depuisBase()->quartiers();
            $donnees['rayons'] = InscriptionService::RAYONS;
        }
        return Response::html(View::rendre('pro/profil-modifier', $donnees, 'layouts/pro'));
    }

    /** Le mécano connecté, si son dossier est validé. */
    private function mecano(): ?int
    {
        $id = (int) Session::get('mecano_id');
        return MecanoService::depuisBase()->compte($id)['statut_dossier'] === 'valide' ? $id : null;
    }
}
