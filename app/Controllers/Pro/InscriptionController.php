<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Format;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\GoogleAuthService;
use App\Services\InscriptionService;
use App\Services\Parametres;
use App\Services\PieceDossierService;
use App\Services\PositionService;

/**
 * L'inscription du mécanicien (RG-DO1 à RG-DO3) : M1a compte, M1b métier, M1c pièces, M1d relire et envoyer, M1e dossier envoyé.
 * Le compte se crée à la première étape et sert de brouillon : on peut reprendre depuis un autre appareil en se connectant.
 */
final class InscriptionController
{
    private const CSS = ['css/pages/pro.css', 'css/pages/pro-inscription.css'];

    // ---------- M1a · Compte (Figma 95:154) ----------

    /** GET /pro/inscription/compte */
    public function compte(Request $requete): Response
    {
        $mecano = $this->mecano();
        if ($mecano !== null && $mecano['statut'] !== 'brouillon') {
            return Response::redirection('/pro');
        }
        return $this->pageCompte($mecano, $mecano !== null ? ['prenom' => $mecano['prenom'], 'nom' => $mecano['nom'], 'telephone' => Format::telephoneLocal($mecano['telephone']), 'email' => $mecano['email']] : $this->saisieGoogle(), [], (string) $requete->query('retour', ''));
    }

    /** POST /pro/inscription/compte : crée le compte (brouillon) et l'ouvre ; ou corrige les coordonnées d'un compte déjà créé. */
    public function creer(Request $requete): Response
    {
        $service = InscriptionService::depuisBase();
        $mecano = $this->mecano();
        $saisie = $requete->tout();
        $retour = (string) $requete->input('retour', '');
        if ($mecano !== null) {
            if ($mecano['statut'] !== 'brouillon') {
                return Response::redirection('/pro');
            }
            $r = $service->modifierCompte($mecano['id'], $saisie);
            return $r['ok'] ? Response::redirection($retour === 'envoi' ? '/pro/inscription/envoi' : '/pro/inscription/metier') : $this->pageCompte($mecano, $saisie, $r['erreurs'], $retour);
        }
        $google = Session::get('google_profil');
        $google = is_array($google) && isset($google['sub'], $google['email']) ? ['sub' => (string) $google['sub'], 'email' => (string) $google['email']] : null;
        $r = $service->creerCompte($saisie, $google);
        if (!$r['ok']) {
            return $this->pageCompte(null, $saisie, $r['erreurs'], $retour);
        }
        Session::regenerer();
        Session::oublier('admin_id');
        Session::oublier('google_profil');
        Session::set('mecano_id', $r['id']);
        return Response::redirection('/pro/inscription/metier');
    }

    // ---------- M1b · Métier (Figma 97:218) ----------

    /** GET /pro/inscription/metier */
    public function metier(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            return Response::redirection('/pro');
        }
        $positions = PositionService::depuisBase();
        $service = InscriptionService::depuisBase();
        return Response::html(View::rendre('pro/inscription-metier', [
            'titre' => 'Votre métier', 'sansPied' => true, 'sansBarre' => true, 'css' => [...self::CSS, 'css/composants/carte.css', 'vendor/leaflet-1.9.4/leaflet.css'],
            'js' => ['vendor/leaflet-1.9.4/leaflet.js', 'vendor/protomaps-leaflet-5.1.0/protomaps-leaflet.js', 'js/pages/pro-inscription.js'],
            'b' => $b, 'specialites' => $service->specialites(), 'quartiers' => $positions->quartiers(), 'rayons' => InscriptionService::RAYONS,
            'etats' => $service->etatPieces($id)['etats'], 'erreurs' => [], 'portrait' => $this->portraitUrl($id), 'centre' => $this->centre($b),
        ], 'layouts/client'));
    }

    /** POST /pro/inscription/metier : sans JavaScript, tout le formulaire d'un coup ; avec JavaScript, tout est déjà enregistré. */
    public function enregistrerMetier(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            return Response::redirection('/pro');
        }
        $champs = $requete->tout();
        $champs['specialites'] = (array) ($champs['specialites'] ?? []);
        $champs['vehicules'] = (string) ($champs['vehicules'] ?? '');
        $champs['deplacement_inclus'] = isset($champs['deplacement_inclus']);
        $r = InscriptionService::depuisBase()->enregistrerMetier($id, $champs);
        if ($r['erreurs'] !== []) {
            Session::flash('message', (string) reset($r['erreurs']));
            return Response::redirection('/pro/inscription/metier');
        }
        return Response::redirection('/pro/inscription/pieces');
    }

    /** POST /pro/api/brouillon : un champ qui change s'enregistre tout de suite (RG-DO1). */
    public function brouillon(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            throw new HttpException(409, 'Ce dossier n’est plus un brouillon.');
        }
        $champs = $requete->json() ?: $requete->tout();
        $r = InscriptionService::depuisBase()->enregistrerMetier($id, array_intersect_key($champs, array_flip(['specialites', 'vehicules', 'annees', 'quartier', 'rayon', 'tarif_diagnostic', 'tarif_deplacement', 'deplacement_inclus', 'presentation'])));
        return Response::json(['erreurs' => (object) $r['erreurs'], 'enregistre_a' => $r['enregistre_a']]);
    }

    // ---------- M1c · Pièces (Figma 102:434) ----------

    /** GET /pro/inscription/pieces */
    public function pieces(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            return Response::redirection('/pro');
        }
        $etat = InscriptionService::depuisBase()->etatPieces($id);
        return Response::html(View::rendre('pro/inscription-pieces', [
            'titre' => 'Vos pièces', 'sansPied' => true, 'sansBarre' => true, 'css' => self::CSS, 'js' => ['js/pages/pro-inscription.js'],
            'b' => $b, 'etat' => $etat,
        ], 'layouts/client'));
    }

    /** POST /pro/inscription/pieces/{type} : une pièce. JSON pour le JavaScript, redirection sans lui. */
    public function deposerPiece(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            throw new HttpException(409, 'Ce dossier n’est plus un brouillon.');
        }
        $fichier = null;
        foreach (['fichier', 'fichier_photo', 'fichier_fichier', 'fichier_remplacer'] as $cle) {
            $fichier ??= $requete->fichier($cle);
        }
        $r = $fichier === null ? ['ok' => false, 'erreur' => 'Choisissez une photo ou un fichier.'] : PieceDossierService::depuisBase()->deposer($id, (string) $requete->param('type'), $fichier);
        $etat = InscriptionService::depuisBase()->etatPieces($id);
        if ($requete->veutJson()) {
            return Response::json([
                'ok' => $r['ok'], 'erreur' => $r['erreur'] ?? null, 'piece' => $r['piece'] ?? null, 'etats' => $etat['etats'], 'envoyees' => $etat['obligatoires_envoyees'],
                'manque' => $etat['manquantes'] === [] ? null : 'Il manque ' . InscriptionService::phraseManque($etat['manquantes']),
                'enregistre_a' => Format::heure(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
            ], $r['ok'] ? 200 : 422);
        }
        Session::flash('message', $r['ok'] ? 'Pièce envoyée.' : $r['erreur']);
        return Response::redirection('/pro/inscription/pieces');
    }

    /** GET /pro/mes-pieces/portrait : la photo du mécano, à lui seul (l'aperçu de sa ligne) ; jamais publique, jamais celle d'un autre. */
    public function maPiece(Request $requete): Response
    {
        $id = (int) Session::get('mecano_id');
        $type = (string) $requete->param('type');
        $chemin = $type === 'portrait' ? PieceDossierService::depuisBase()->cheminDe($id, $type) : null;
        $octets = $chemin !== null ? file_get_contents($chemin) : false;
        if ($octets === false) {
            throw HttpException::introuvable();
        }
        return (new Response($octets, 200))->avecEntete('Content-Type', 'image/jpeg')->avecEntete('Cache-Control', 'private, no-store')->avecEntete('X-Content-Type-Options', 'nosniff');
    }

    // ---------- M1d · Relire et envoyer (Figma 104:607) ----------

    /** GET /pro/inscription/envoi */
    public function envoi(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            return Response::redirection('/pro');
        }
        $service = InscriptionService::depuisBase();
        $etat = $service->etatPieces($id);
        if ($etat['manquantes'] !== []) {
            return Response::redirection('/pro/inscription/pieces');
        }
        return Response::html(View::rendre('pro/inscription-envoi', [
            'titre' => 'Relire et envoyer', 'sansPied' => true, 'sansBarre' => true, 'css' => self::CSS, 'js' => ['js/pages/pro-inscription.js'],
            'b' => $b, 'etat' => $etat, 'specialites' => array_column($service->specialites(), 'libelle', 'code'), 'manques' => $service->manques($id), 'portrait' => $this->portraitUrl($id),
        ], 'layouts/client'));
    }

    /** POST /pro/inscription/envoi */
    public function envoyer(Request $requete): Response
    {
        [$id, $b] = $this->brouillonOuSortie();
        if ($b === null) {
            return Response::redirection('/pro');
        }
        $r = InscriptionService::depuisBase()->envoyer($id, ['cgu' => $requete->input('accord_cgu'), 'verification' => $requete->input('accord_verification'), 'visibilite' => $requete->input('accord_visibilite')]);
        if (!$r['ok']) {
            Session::flash('message', $r['erreur']);
            return Response::redirection('/pro/inscription/envoi');
        }
        return Response::redirection('/pro/inscription/envoye');
    }

    // ---------- M1e · Dossier envoyé (Figma 105:656) ----------

    /** GET /pro/inscription/envoye */
    public function envoye(Request $requete): Response
    {
        $m = $this->mecano();
        if ($m === null || $m['envoye_le'] === null) {
            return Response::redirection('/pro');
        }
        $etat = InscriptionService::depuisBase()->etatPieces($m['id']);
        return Response::html(View::rendre('pro/inscription-envoye', [
            'titre' => 'Dossier envoyé', 'sansPied' => true, 'sansBarre' => true, 'css' => self::CSS,
            'numero' => InscriptionService::numeroAffiche($m['id']), 'envoye_le' => $m['envoye_le'], 'etats' => $etat['etats'], 'delai' => Parametres::int('dossier.delai_annonce_h', 48),
        ], 'layouts/client'));
    }

    // ---------- Aides ----------

    /** @return ?array<string, mixed> */
    private function mecano(): ?array
    {
        $id = Session::get('mecano_id');
        return is_int($id) ? InscriptionService::depuisBase()->brouillon($id) : null;
    }

    /** @return array{0: int, 1: ?array<string, mixed>} le mécano connecté et son brouillon (nul si le dossier n'est plus un brouillon) */
    private function brouillonOuSortie(): array
    {
        $id = (int) Session::get('mecano_id');
        $b = InscriptionService::depuisBase()->brouillon($id);
        return [$id, $b !== null && $b['statut'] === 'brouillon' ? $b : null];
    }

    /** @return array<string, string> ce que Google a déjà dit (e-mail), pour préremplir M1a */
    private function saisieGoogle(): array
    {
        $g = Session::get('google_profil');
        return is_array($g) ? ['email' => (string) ($g['email'] ?? '')] : [];
    }

    private function portraitUrl(int $id): ?string
    {
        return PieceDossierService::depuisBase()->cheminDe($id, 'portrait') !== null ? '/pro/mes-pieces/portrait' : null;
    }

    /** Le centre de la mini-carte : le quartier de l'atelier, sinon Abidjan. @param array<string, mixed> $b @return array{lat: float, lon: float} */
    private function centre(array $b): array
    {
        return $b['quartier'] !== null ? ['lat' => $b['quartier']['lat'], 'lon' => $b['quartier']['lon']] : ['lat' => 5.3364, 'lon' => -4.0267];
    }

    /** @param ?array<string, mixed> $mecano @param array<string, mixed> $saisie @param array<string, string> $erreurs */
    private function pageCompte(?array $mecano, array $saisie, array $erreurs, string $retour): Response
    {
        $google = Session::get('google_profil');
        return Response::html(View::rendre('pro/inscription-compte', [
            'titre' => 'Créons votre compte', 'sansPied' => true, 'sansBarre' => true, 'css' => self::CSS, 'js' => ['js/pages/pro-inscription.js'],
            'saisie' => $saisie, 'erreurs' => $erreurs, 'modification' => $mecano !== null, 'google' => GoogleAuthService::actif() && $mecano === null && !is_array($google),
            'viaGoogle' => $mecano !== null ? $mecano['google'] : is_array($google), 'retour' => $retour === 'envoi' ? 'envoi' : '',
        ], 'layouts/client'));
    }
}
