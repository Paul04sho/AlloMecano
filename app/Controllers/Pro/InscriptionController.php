<?php

declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Database;
use App\Core\Format;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Ulid;
use App\Core\Validator;
use App\Core\View;
use App\Services\GoogleOAuthService;

/** M1a · Création du compte mécanicien, brouillon et authentification Google. */
final class InscriptionController
{
    private const CLE_BROUILLON = 'm1a_brouillon';
    private const CLE_COMPTE = 'm1_compte';
    private const CLE_GOOGLE = 'm1_google';

    public function compte(Request $requete): Response
    {
        $brouillon = Session::get(self::CLE_BROUILLON);
        if (!is_array($brouillon)) {
            $brouillon = [
                'prenom' => '', 'nom' => '', 'telephone' => '', 'email' => '',
                'heure' => gmdate('H:i'), 'mis_a_jour' => time(),
            ];
            Session::set(self::CLE_BROUILLON, $brouillon);
        }

        $google = Session::get(self::CLE_GOOGLE);
        if (is_array($google)) {
            $brouillon['prenom'] = $brouillon['prenom'] ?: (string) ($google['prenom'] ?? '');
            $brouillon['nom'] = $brouillon['nom'] ?: (string) ($google['nom'] ?? '');
            $brouillon['email'] = (string) ($google['email'] ?? $brouillon['email'] ?? '');
        }

        $compte = Session::get(self::CLE_COMPTE);
        if (is_array($compte)) {
            foreach (['prenom', 'nom', 'telephone', 'email'] as $champ) {
                if (empty($brouillon[$champ]) && !empty($compte[$champ])) {
                    $brouillon[$champ] = $compte[$champ];
                }
            }
        }

        $erreurs = Session::get('m1a_erreurs', []);
        Session::oublier('m1a_erreurs');
        $message = Session::flash('m1a_message');
        $googleConfigure = (new GoogleOAuthService())->configure();

        return Response::html(View::rendre('pro/inscription-compte', [
            'titre' => 'Créons votre compte',
            'description' => 'Créez votre compte mécanicien allomécano en quelques minutes.',
            'classeCorps' => 'page-pro-inscription',
            'sansBarre' => true,
            'sansPied' => true,
            'brouillon' => $brouillon,
            'erreurs' => is_array($erreurs) ? $erreurs : [],
            'message' => $message,
            'google' => is_array($google) ? $google : null,
            'googleConfigure' => $googleConfigure,
            'css' => ['css/pages/pro-inscription.css'],
            'js' => ['js/pages/pro-inscription.js'],
        ], 'layouts/client'));
    }

    /** POST M1a : sauvegarde partielle, sans jamais garder le mot de passe en clair. */
    public function brouillon(Request $requete): Response
    {
        $existant = Session::get(self::CLE_BROUILLON, []);
        if (!is_array($existant)) {
            $existant = [];
        }

        $champs = [];
        foreach (['prenom' => 60, 'nom' => 60, 'telephone' => 24, 'email' => 190] as $champ => $max) {
            $valeur = $requete->input($champ, $existant[$champ] ?? '');
            $valeur = is_string($valeur) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $valeur) ?? '') : '';
            $champs[$champ] = mb_substr($valeur, 0, $max);
        }

        $brouillon = array_merge($existant, $champs, [
            'heure' => gmdate('H:i'),
            'mis_a_jour' => time(),
        ]);
        Session::set(self::CLE_BROUILLON, $brouillon);

        return Response::json(['ok' => true, 'heure' => $brouillon['heure']]);
    }

    /** POST M1a : valide et crée ou met à jour le compte en brouillon. */
    public function continuer(Request $requete): Response
    {
        $google = Session::get(self::CLE_GOOGLE);
        $google = is_array($google) ? $google : null;
        $telephoneBrut = (string) $requete->input('telephone', '');
        $emailBrut = mb_strtolower(trim((string) $requete->input('email', '')));
        $motDePasse = (string) $requete->input('mot_de_passe', '');

        $regles = [
            'prenom' => 'requis|max:60',
            'nom' => 'requis|max:60',
            'telephone' => 'requis|telephone_ci',
            'email' => 'requis|email|max:190',
        ];
        if ($google === null || $motDePasse !== '') {
            $regles['mot_de_passe'] = 'requis|mot_de_passe';
        }

        $validation = Validator::verifier([
            'prenom' => $requete->input('prenom'),
            'nom' => $requete->input('nom'),
            'telephone' => $telephoneBrut,
            'email' => $emailBrut,
            'mot_de_passe' => $motDePasse,
        ], $regles);
        $erreurs = $validation->erreurs();
        $propres = $validation->donnees();

        if ($motDePasse !== '' && strlen($motDePasse) > 72) {
            $erreurs['mot_de_passe'] = '72 octets au maximum, pour protéger votre mot de passe.';
        }
        if ($google !== null && $emailBrut !== mb_strtolower((string) $google['email'])) {
            $erreurs['email'] = 'Utilisez l’adresse e-mail vérifiée avec Google, ou déconnectez cette identité.';
        }

        $telephone = is_string($propres['telephone'] ?? null)
            ? (string) $propres['telephone']
            : Format::telephoneE164($telephoneBrut);
        $compteExistant = Session::get(self::CLE_COMPTE);
        $idExistant = is_array($compteExistant) && is_numeric($compteExistant['mecanicien_id'] ?? null)
            ? (int) $compteExistant['mecanicien_id']
            : null;
        $ligneExistante = null;
        $db = null;

        if ($erreurs === []) {
            try {
                $db = Database::instance();
                if ($idExistant !== null) {
                    $ligneExistante = $db->un(
                        'SELECT id, public_id, telephone, email, mot_de_passe_hash, google_sub, email_verifie_le, metier, annees_experience
                           FROM mecaniciens
                          WHERE id = :id AND statut_dossier = \'brouillon\' AND supprime_le IS NULL',
                        ['id' => $idExistant]
                    );
                    if ($ligneExistante === null) {
                        $idExistant = null;
                    }
                }

                $paramsTelephone = ['telephone' => $telephone];
                $paramsEmail = ['email' => $emailBrut];
                $sqlTelephone = 'SELECT id FROM mecaniciens WHERE telephone = :telephone';
                $sqlEmail = 'SELECT id FROM mecaniciens WHERE email = :email';
                if ($idExistant !== null) {
                    $sqlTelephone .= ' AND id <> :id_exclu';
                    $sqlEmail .= ' AND id <> :id_exclu';
                    $paramsTelephone['id_exclu'] = $idExistant;
                    $paramsEmail['id_exclu'] = $idExistant;
                }
                if ($db->valeur($sqlTelephone . ' LIMIT 1', $paramsTelephone) !== null) {
                    $erreurs['telephone'] = 'Ce numéro est déjà associé à un compte mécanicien.';
                }
                if ($db->valeur($sqlEmail . ' LIMIT 1', $paramsEmail) !== null) {
                    $erreurs['email'] = 'Cette adresse e-mail est déjà associée à un compte mécanicien.';
                }
                if ($google !== null) {
                    $sqlGoogle = 'SELECT id FROM mecaniciens WHERE google_sub = :sub AND supprime_le IS NULL';
                    $paramsGoogle = ['sub' => $google['sub']];
                    if ($idExistant !== null) {
                        $sqlGoogle .= ' AND id <> :id_exclu';
                        $paramsGoogle['id_exclu'] = $idExistant;
                    }
                    if ($db->valeur($sqlGoogle . ' LIMIT 1', $paramsGoogle) !== null) {
                        $erreurs['email'] = 'Ce compte Google est déjà associé à un dossier mécanicien.';
                    }
                }
            } catch (\Throwable $e) {
                error_log((string) $e);
                Session::flash('m1a_message', 'Impossible de vérifier votre compte pour le moment. Réessayez dans un instant.');
                Session::set(self::CLE_BROUILLON, $this->champsBrouillon($requete));
                return Response::redirection('/pro/inscription/1');
            }
        }

        if ($erreurs !== []) {
            Session::set('m1a_erreurs', $erreurs);
            Session::set(self::CLE_BROUILLON, $this->champsBrouillon($requete));
            return Response::redirection('/pro/inscription/1');
        }

        try {
            if (!$db instanceof Database) {
                $db = Database::instance();
            }
            $sous = $google['sub'] ?? ($ligneExistante['google_sub'] ?? null);
            $hashExistant = $ligneExistante['mot_de_passe_hash'] ?? null;
            $hash = $motDePasse !== '' ? password_hash($motDePasse, PASSWORD_DEFAULT) : $hashExistant;
            if ($hash === null && $sous === null) {
                throw new \RuntimeException('Aucune méthode de connexion valide.');
            }
            $emailVerifie = $google !== null
                ? gmdate('Y-m-d H:i:s')
                : ($ligneExistante['email_verifie_le'] ?? null);

            $donneesCompte = [
                'prenom' => (string) $propres['prenom'],
                'nom' => (string) $propres['nom'],
                'telephone' => $telephone,
                'email' => $emailBrut,
                'google_sub' => $sous,
                'email_verifie_le' => $emailVerifie,
                'mot_de_passe_hash' => $hash,
            ];

            if ($idExistant !== null && $ligneExistante !== null) {
                $db->modifier('mecaniciens', $donneesCompte, [
                    'id' => $idExistant,
                    'statut_dossier' => 'brouillon',
                ]);
                $idMecanicien = $idExistant;
                $publicId = (string) $ligneExistante['public_id'];
                $metier = (string) $ligneExistante['metier'];
                $experience = (int) $ligneExistante['annees_experience'];
            } else {
                $publicId = Ulid::generer();
                $idMecanicien = $db->inserer('mecaniciens', $donneesCompte + [
                    'public_id' => $publicId,
                    // Champ requis par le modèle, remplacé par le choix réel à l'étape M1b.
                    'metier' => 'À préciser',
                    'annees_experience' => 0,
                    'statut_dossier' => 'brouillon',
                    'etat' => 'indisponible',
                ]);
                $metier = 'À préciser';
                $experience = 0;
            }

            $compte = $donneesCompte;
            unset($compte['mot_de_passe_hash']);
            $compte['mecanicien_id'] = $idMecanicien;
            $compte['public_id'] = $publicId;
            $compte['metier'] = $metier;
            $compte['annees_experience'] = $experience;
            $compte['etape'] = 1;
            Session::set(self::CLE_COMPTE, $compte);
            Session::set(self::CLE_BROUILLON, array_merge($this->champsBrouillon($requete), [
                'heure' => gmdate('H:i'), 'mis_a_jour' => time(),
            ]));
        } catch (\Throwable $e) {
            error_log((string) $e);
            Session::flash('m1a_message', 'Votre compte n’a pas pu être enregistré. Vérifiez que votre téléphone et votre e-mail ne sont pas déjà utilisés, puis réessayez.');
            Session::set(self::CLE_BROUILLON, $this->champsBrouillon($requete));
            return Response::redirection('/pro/inscription/1');
        }

        return Response::redirection('/pro/inscription/2');
    }

    /** Démarre le flux Authorization Code + state/nonce. */
    public function google(Request $requete): Response
    {
        $service = new GoogleOAuthService();
        if (!$service->configure()) {
            Session::flash('m1a_message', 'La connexion Google doit être configurée dans .env avec GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET.');
            return Response::redirection('/pro/inscription/1');
        }

        $etat = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        Session::set('m1_google_oauth', [
            'etat' => $etat, 'nonce' => $nonce, 'demarre' => time(),
        ]);

        return Response::redirection($service->urlAutorisation($etat, $nonce), 302);
    }

    /** Callback Google : rejette les retours sans state, nonce ou e-mail vérifié. */
    public function retourGoogle(Request $requete): Response
    {
        $oauth = Session::get('m1_google_oauth');
        Session::oublier('m1_google_oauth');
        $etatRetour = (string) $requete->query('state', '');
        $etatValide = is_array($oauth)
            && is_string($oauth['etat'] ?? null)
            && hash_equals($oauth['etat'], $etatRetour)
            && (int) ($oauth['demarre'] ?? 0) >= time() - 600;
        if (!$etatValide) {
            Session::flash('m1a_message', 'La connexion Google a expiré. Réessayez.');
            return Response::redirection('/pro/inscription/1');
        }
        if ($requete->query('error') !== null || !is_string($requete->query('code'))) {
            Session::flash('m1a_message', 'La connexion Google a été annulée ou refusée.');
            return Response::redirection('/pro/inscription/1');
        }

        try {
            $profil = (new GoogleOAuthService())->profilDepuisCode((string) $requete->query('code'), (string) $oauth['nonce']);
            $db = Database::instance();
            if ($db->valeur('SELECT id FROM mecaniciens WHERE google_sub = :sub LIMIT 1', ['sub' => $profil['sub']]) !== null) {
                Session::flash('m1a_message', 'Ce compte Google est déjà associé à un compte mécanicien. La connexion à un compte existant sera disponible dans l’espace pro.');
                return Response::redirection('/pro/inscription/1');
            }
            if ($db->valeur('SELECT id FROM mecaniciens WHERE email = :email LIMIT 1', ['email' => $profil['email']]) !== null) {
                Session::flash('m1a_message', 'Cette adresse e-mail possède déjà un compte mécanicien. Connectez-vous plutôt que de créer un doublon.');
                return Response::redirection('/pro/inscription/1');
            }
            Session::set(self::CLE_GOOGLE, $profil);
            $brouillon = Session::get(self::CLE_BROUILLON, []);
            if (!is_array($brouillon)) {
                $brouillon = [];
            }
            Session::set(self::CLE_BROUILLON, array_merge($brouillon, [
                'prenom' => $profil['prenom'], 'nom' => $profil['nom'], 'email' => $profil['email'],
                'heure' => gmdate('H:i'), 'mis_a_jour' => time(),
            ]));
            Session::flash('m1a_message', 'Compte Google vérifié. Complétez votre numéro de téléphone pour continuer.');
        } catch (\Throwable $e) {
            error_log((string) $e);
            Session::flash('m1a_message', 'Google n’a pas pu confirmer votre identité. Réessayez ou remplissez le formulaire.');
        }

        return Response::redirection('/pro/inscription/1');
    }

    /** M1b : l'écran suivant sera détaillé avec sa maquette dédiée. */
    public function etapeMetier(Request $requete): Response
    {
        $compte = Session::get(self::CLE_COMPTE);
        if (!is_array($compte)) {
            return Response::redirection('/pro/inscription/1');
        }
        $message = Session::flash('m1b_message');
        return Response::html(View::rendre('pro/inscription-metier', [
            'titre' => 'Votre métier',
            'description' => 'Poursuivez la création de votre dossier mécanicien.',
            'classeCorps' => 'page-pro-inscription',
            'sansBarre' => true,
            'sansPied' => true,
            'compte' => $compte,
            'metier' => (string) ($compte['metier'] ?? ''),
            'experience' => (string) ($compte['annees_experience'] ?? ''),
            'erreur' => Session::flash('m1b_erreur'),
            'message' => $message,
            'css' => ['css/pages/pro-inscription.css'],
            'js' => [],
        ], 'layouts/client'));
    }

    /** M1b : ajoute les informations métier au brouillon serveur. */
    public function enregistrerMetier(Request $requete): Response
    {
        $compte = Session::get(self::CLE_COMPTE);
        if (!is_array($compte)) {
            return Response::redirection('/pro/inscription/1');
        }

        $metier = trim((string) $requete->input('metier', ''));
        $experienceBrute = trim((string) $requete->input('annees_experience', ''));
        $metiers = [
            'Mécanique générale', 'Électricien auto', 'Mécanicien moto',
            'Vulcanisateur', 'Moteur et refroidissement', 'Dépannage et remorquage',
            'Carrosserie', 'Mécanicien poids lourds',
        ];
        if (!in_array($metier, $metiers, true)) {
            Session::flash('m1b_erreur', 'Choisissez un métier dans la liste.');
            Session::set(self::CLE_COMPTE, array_merge($compte, ['metier' => $metier, 'annees_experience' => $experienceBrute]));
            return Response::redirection('/pro/inscription/2');
        }
        if (!preg_match('/^\\d{1,2}$/', $experienceBrute) || (int) $experienceBrute > 60) {
            Session::flash('m1b_message', 'Indiquez un nombre d’années compris entre 0 et 60.');
            Session::set(self::CLE_COMPTE, array_merge($compte, ['metier' => $metier, 'annees_experience' => $experienceBrute]));
            return Response::redirection('/pro/inscription/2');
        }

        $idMecanicien = (int) ($compte['mecanicien_id'] ?? 0);
        if ($idMecanicien < 1) {
            Session::flash('m1b_message', 'Votre brouillon de compte a expiré. Reprenez l’inscription depuis la première étape.');
            return Response::redirection('/pro/inscription/1');
        }

        try {
            $db = Database::instance();
            $existe = $db->valeur(
                'SELECT id FROM mecaniciens WHERE id = :id AND statut_dossier = \'brouillon\' AND supprime_le IS NULL',
                ['id' => $idMecanicien]
            );
            if ($existe === null) {
                Session::flash('m1b_message', 'Votre brouillon a expiré. Reprenez l’inscription depuis la première étape.');
                Session::oublier(self::CLE_COMPTE);
                return Response::redirection('/pro/inscription/1');
            }

            $db->modifier('mecaniciens', [
                'metier' => $metier,
                'annees_experience' => (int) $experienceBrute,
            ], [
                'id' => $idMecanicien,
                'statut_dossier' => 'brouillon',
            ]);
        } catch (\Throwable $e) {
            error_log((string) $e);
            Session::flash('m1b_message', 'Le métier n’a pas pu être enregistré. Réessayez.');
            Session::set(self::CLE_COMPTE, array_merge($compte, ['metier' => $metier, 'annees_experience' => $experienceBrute]));
            return Response::redirection('/pro/inscription/2');
        }

        Session::set(self::CLE_COMPTE, array_merge($compte, [
            'metier' => $metier,
            'annees_experience' => (int) $experienceBrute,
            'etape' => 2,
        ]));
        Session::flash('m1b_message', 'Votre compte est enregistré en brouillon. Vous pourrez reprendre votre dossier plus tard.');
        return Response::redirection('/pro/inscription/2');
    }

    /**
     * @return array{prenom:string,nom:string,telephone:string,email:string,heure:string,mis_a_jour:int}
     */
    private function champsBrouillon(Request $requete): array
    {
        $v = static function (Request $r, string $k, int $max): string {
            $value = $r->input($k, '');
            $value = is_string($value) ? trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '') : '';
            return mb_substr($value, 0, $max);
        };
        return [
            'prenom' => $v($requete, 'prenom', 60),
            'nom' => $v($requete, 'nom', 60),
            'telephone' => $v($requete, 'telephone', 24),
            'email' => $v($requete, 'email', 190),
            'heure' => gmdate('H:i'),
            'mis_a_jour' => time(),
        ];
    }
}
