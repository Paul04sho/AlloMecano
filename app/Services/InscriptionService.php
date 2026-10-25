<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use App\Core\Ulid;
use App\Core\Validator;

/**
 * L'inscription du mécanicien en quatre étapes (M1a à M1d) : compte, métier, pièces, envoi.
 * Le brouillon est le compte lui-même : chaque champ s'enregistre dès qu'il change et se reprend sur un autre appareil (RG-DO1).
 * L'envoi exige les cinq pièces obligatoires et trois accords jamais pré-cochés (RG-DO3) ; le dossier passe alors « en attente », l'équipe est prévenue.
 */
final class InscriptionService
{
    public const RAYONS = [2, 5, 10, 20];
    public const PRESENTATION_MAX = 140;
    public const ANNEES_MAX = 60;
    public const TARIF_MAX = 1_000_000;
    public const CGU_VERSION = '1.0';
    /** Le choix « Voiture · Moto · Les deux » de M1b et les types de véhicule qu'il couvre (filtre strict RG-R2). */
    public const VEHICULES = ['voiture' => ['voiture'], 'moto' => ['moto'], 'les_deux' => ['voiture', 'moto']];
    public const ACCORDS = ['cgu', 'verification', 'visibilite'];
    /** Les noms des pièces dans la phrase « Il manque … » de M1c. */
    public const NOMS_PIECES = ['cni_recto' => 'la CNI recto', 'cni_verso' => 'la CNI verso', 'permis' => 'le permis', 'diplome' => 'le diplôme', 'portrait' => 'la photo de vous'];

    public function __construct(private readonly Database $db, private readonly ?MailService $mail = null, private readonly ?PieceDossierService $pieces = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    private function pieces(): PieceDossierService
    {
        return $this->pieces ?? new PieceDossierService($this->db);
    }

    // ---------- M1a · le compte ----------

    /**
     * Crée le compte en brouillon (RG-C1) : téléphone +225 et mot de passe de 8 caractères dont 1 chiffre, ou Google (prénom, nom et téléphone restent à dire).
     *
     * @param array<string, mixed> $saisie prenom, nom, telephone, email, mot_de_passe
     * @param ?array{sub: string, email: string} $google
     * @return array{ok: true, id: int}|array{ok: false, erreurs: array<string, string>}
     */
    public function creerCompte(array $saisie, ?array $google = null): array
    {
        $erreurs = $this->validerCompte($saisie, $google !== null, null);
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $email = $google['email'] ?? ($this->texte($saisie, 'email') !== '' ? mb_strtolower($this->texte($saisie, 'email')) : null);
        $id = $this->db->inserer('mecaniciens', [
            'public_id' => Ulid::generer(), 'prenom' => $this->texte($saisie, 'prenom'), 'nom' => $this->texte($saisie, 'nom'), 'telephone' => Format::telephoneE164($this->texte($saisie, 'telephone')),
            'email' => $email, 'mot_de_passe_hash' => $google === null ? password_hash((string) ($saisie['mot_de_passe'] ?? ''), PASSWORD_DEFAULT) : null,
            'google_sub' => $google['sub'] ?? null, 'metier' => '', 'statut_dossier' => 'brouillon',
        ]);
        $this->db->inserer('mecanicien_vehicules', ['mecanicien_id' => $id, 'type_vehicule' => 'voiture']); // « Voiture » est choisi d'office (M1b), le mécano change s'il répare autre chose
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Les coordonnées d'un compte déjà créé se corrigent depuis « Modifier » (M1d). Le mot de passe ne change pas ici.
     *
     * @param array<string, mixed> $saisie
     * @return array{ok: true}|array{ok: false, erreurs: array<string, string>}
     */
    public function modifierCompte(int $id, array $saisie): array
    {
        $erreurs = $this->validerCompte($saisie, true, $id);
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $email = $this->texte($saisie, 'email') !== '' ? mb_strtolower($this->texte($saisie, 'email')) : null;
        $this->db->modifier('mecaniciens', ['prenom' => $this->texte($saisie, 'prenom'), 'nom' => $this->texte($saisie, 'nom'),
            'telephone' => Format::telephoneE164($this->texte($saisie, 'telephone')), 'email' => $email], ['id' => $id]);
        return ['ok' => true];
    }

    /** @param array<string, mixed> $saisie @return array<string, string> */
    private function validerCompte(array $saisie, bool $sansMotDePasse, ?int $moi): array
    {
        $v = Validator::verifier([
            'prenom' => $this->texte($saisie, 'prenom'), 'nom' => $this->texte($saisie, 'nom'), 'telephone' => $this->texte($saisie, 'telephone'),
            'email' => $this->texte($saisie, 'email'), 'mot_de_passe' => (string) ($saisie['mot_de_passe'] ?? ''),
        ], [
            'prenom' => 'requis|max:60', 'nom' => 'requis|max:60', 'telephone' => 'requis|telephone_ci', 'email' => 'email|max:190',
            'mot_de_passe' => $sansMotDePasse ? 'max:200' : 'requis|mot_de_passe',
        ]);
        $e = $v->erreurs();
        foreach (['prenom' => 'Indiquez votre prénom.', 'nom' => 'Indiquez votre nom.', 'telephone' => null, 'mot_de_passe' => 'Choisissez un mot de passe : 8 caractères au minimum, dont 1 chiffre.'] as $champ => $message) {
            if (isset($e[$champ]) && $message !== null && $e[$champ] === 'Ce champ est obligatoire.') {
                $e[$champ] = $message;
            }
        }
        if (isset($e['telephone']) && $e['telephone'] === 'Ce champ est obligatoire.') {
            $e['telephone'] = 'Indiquez le numéro sur lequel les clients vous appelleront.';
        }
        if (!isset($e['telephone']) && $this->db->valeur('SELECT id FROM mecaniciens WHERE telephone = :t AND id <> :m', ['t' => Format::telephoneE164($this->texte($saisie, 'telephone')), 'm' => $moi ?? 0]) !== null) {
            $e['telephone'] = 'Ce numéro a déjà un compte. Connectez-vous plutôt.';
        }
        if (!isset($e['email']) && $this->texte($saisie, 'email') !== '' && $this->db->valeur('SELECT id FROM mecaniciens WHERE email = :e AND id <> :m', ['e' => mb_strtolower($this->texte($saisie, 'email')), 'm' => $moi ?? 0]) !== null) {
            $e['email'] = 'Cette adresse est déjà utilisée par un compte.';
        }
        return $e;
    }

    /** @param array<string, mixed> $saisie */
    private function texte(array $saisie, string $cle): string
    {
        return trim((string) ($saisie[$cle] ?? ''));
    }

    // ---------- M1b · le métier (brouillon enregistré champ par champ, RG-DO1) ----------

    /** @return list<array{code: string, libelle: string}> */
    public function specialites(): array
    {
        return array_map(static fn (array $s): array => ['code' => (string) $s['code'], 'libelle' => (string) $s['libelle']], $this->db->tous('SELECT code, libelle FROM specialites WHERE actif = 1 ORDER BY ordre'));
    }

    /** « Marcory, Zone 4 » : la commune puis le quartier, comme dans le champ de M1b. */
    public static function libelleQuartier(array $q): string
    {
        return $q['commune_nom'] . ', ' . $q['nom'];
    }

    /** Retrouve un quartier d'après ce que le mécano a choisi dans la liste (« Marcory, Zone 4 »). @return ?array<string, mixed> */
    public function quartierParLibelle(string $libelle): ?array
    {
        $cherche = PositionService::normaliser($libelle);
        foreach (PositionService::depuisBase()->quartiers() as $q) {
            if (PositionService::normaliser(self::libelleQuartier($q)) === $cherche) {
                return $q;
            }
        }
        return null;
    }

    /**
     * Tout ce que M1b sait du mécano : de quoi remplir le formulaire et l'aperçu de sa ligne.
     *
     * @return ?array<string, mixed>
     */
    public function brouillon(int $id): ?array
    {
        $m = $this->db->un(
            'SELECT id, public_id, prenom, nom, telephone, email, google_sub, statut_dossier, annees_experience, presentation, atelier_quartier_id, rayon_km,
                    tarif_diagnostic_fcfa, tarif_deplacement_fcfa, modifie_le, dossier_envoye_le, cree_le
               FROM mecaniciens WHERE id = :i AND supprime_le IS NULL',
            ['i' => $id]
        );
        if ($m === null) {
            return null;
        }
        $specs = array_column($this->db->tous('SELECT s.code FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id WHERE ms.mecanicien_id = :m ORDER BY s.ordre', ['m' => $id]), 'code');
        $types = array_column($this->db->tous('SELECT type_vehicule FROM mecanicien_vehicules WHERE mecanicien_id = :m', ['m' => $id]), 'type_vehicule');
        $vehicules = null;
        foreach (self::VEHICULES as $cle => $liste) {
            if (count($liste) === count($types) && array_diff($liste, $types) === []) {
                $vehicules = $cle;
            }
        }
        $quartier = null;
        if ($m['atelier_quartier_id'] !== null) {
            $slug = $this->db->valeur('SELECT slug FROM quartiers WHERE id = :i', ['i' => $m['atelier_quartier_id']]);
            $quartier = $slug !== null ? PositionService::depuisBase()->quartier((string) $slug) : null;
        }
        $utc = new \DateTimeZone('UTC');
        return [
            'id' => (int) $m['id'], 'public_id' => (string) $m['public_id'], 'prenom' => (string) $m['prenom'], 'nom' => (string) $m['nom'], 'telephone' => (string) $m['telephone'],
            'email' => (string) ($m['email'] ?? ''), 'google' => $m['google_sub'] !== null, 'statut' => (string) $m['statut_dossier'],
            'specialites' => array_map('strval', $specs), 'vehicules' => $vehicules, 'annees' => (int) $m['annees_experience'],
            'quartier' => $quartier, 'quartier_libelle' => $quartier !== null ? self::libelleQuartier($quartier) : '', 'rayon' => (int) $m['rayon_km'],
            'tarif_diagnostic' => $m['tarif_diagnostic_fcfa'] !== null ? (int) $m['tarif_diagnostic_fcfa'] : null,
            'tarif_deplacement' => $m['tarif_deplacement_fcfa'] !== null ? (int) $m['tarif_deplacement_fcfa'] : null,
            'deplacement_inclus' => $m['tarif_deplacement_fcfa'] !== null && (int) $m['tarif_deplacement_fcfa'] === 0,
            'presentation' => (string) ($m['presentation'] ?? ''),
            'modifie_le' => new \DateTimeImmutable((string) $m['modifie_le'], $utc),
            'envoye_le' => $m['dossier_envoye_le'] !== null ? new \DateTimeImmutable((string) $m['dossier_envoye_le'], $utc) : null,
        ];
    }

    /**
     * Enregistre les champs reçus, et seulement eux (autosave) : un champ invalide est renvoyé en erreur sans bloquer les autres.
     *
     * @param array<string, mixed> $champs specialites, vehicules, annees, quartier, rayon, tarif_diagnostic, tarif_deplacement, deplacement_inclus, presentation
     * @return array{erreurs: array<string, string>, enregistre_a: string}
     */
    public function enregistrerMetier(int $id, array $champs, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $erreurs = [];
        $maj = [];
        $this->db->transaction(function () use ($id, $champs, &$erreurs, &$maj): void {
            if (array_key_exists('specialites', $champs)) {
                $codes = array_values(array_unique(array_map('strval', (array) $champs['specialites'])));
                $connues = array_column($this->specialites(), 'libelle', 'code');
                if (array_diff($codes, array_keys($connues)) !== []) {
                    $erreurs['specialites'] = 'Choisissez parmi les spécialités proposées.';
                } else {
                    $this->db->executer('DELETE FROM mecanicien_specialites WHERE mecanicien_id = :m', ['m' => $id]);
                    foreach ($codes as $code) {
                        $this->db->executer('INSERT INTO mecanicien_specialites (mecanicien_id, specialite_id) SELECT :m, id FROM specialites WHERE code = :c', ['m' => $id, 'c' => $code]);
                    }
                    $premiere = null;
                    foreach ($this->specialites() as $s) { // le métier affiché suit la première spécialité, dans l'ordre de la liste
                        if (in_array($s['code'], $codes, true)) {
                            $premiere ??= $s['libelle'];
                        }
                    }
                    $maj['metier'] = $premiere ?? '';
                }
            }
            if (array_key_exists('vehicules', $champs)) {
                $v = (string) $champs['vehicules'];
                if ($v !== '' && !isset(self::VEHICULES[$v])) {
                    $erreurs['vehicules'] = 'Choisissez voiture, moto ou les deux.';
                } else {
                    $this->db->executer('DELETE FROM mecanicien_vehicules WHERE mecanicien_id = :m', ['m' => $id]);
                    foreach (self::VEHICULES[$v] ?? [] as $type) {
                        $this->db->executer('INSERT INTO mecanicien_vehicules (mecanicien_id, type_vehicule) VALUES (:m, :t)', ['m' => $id, 't' => $type]);
                    }
                }
            }
            if (array_key_exists('annees', $champs)) {
                $n = filter_var($champs['annees'], FILTER_VALIDATE_INT);
                if ($n === false || $n < 0 || $n > self::ANNEES_MAX) {
                    $erreurs['annees'] = 'Entre 0 et ' . self::ANNEES_MAX . ' ans.';
                } else {
                    $maj['annees_experience'] = $n;
                }
            }
            if (array_key_exists('quartier', $champs)) {
                $libelle = trim((string) $champs['quartier']);
                $q = $libelle === '' ? null : $this->quartierParLibelle($libelle);
                if ($libelle !== '' && $q === null) {
                    $erreurs['quartier'] = 'Choisissez un quartier dans la liste.';
                } else {
                    $maj['atelier_quartier_id'] = $q !== null ? $this->db->valeur('SELECT id FROM quartiers WHERE slug = :s', ['s' => $q['slug']]) : null;
                    $maj['atelier_lat'] = $q['lat'] ?? null; // le centre du quartier : on n'affiche jamais l'adresse exacte
                    $maj['atelier_lon'] = $q['lon'] ?? null;
                }
            }
            if (array_key_exists('rayon', $champs)) {
                if (!in_array((int) $champs['rayon'], self::RAYONS, true)) {
                    $erreurs['rayon'] = 'Choisissez 2, 5, 10 ou 20 km.';
                } else {
                    $maj['rayon_km'] = (int) $champs['rayon'];
                }
            }
            foreach (['tarif_diagnostic' => 'tarif_diagnostic_fcfa', 'tarif_deplacement' => 'tarif_deplacement_fcfa'] as $cle => $colonne) {
                if (!array_key_exists($cle, $champs)) {
                    continue;
                }
                $brut = preg_replace('/[\s\x{202F}\x{00A0}]/u', '', (string) $champs[$cle]) ?? '';
                if ($brut === '') {
                    $maj[$colonne] = null;
                } elseif (!ctype_digit($brut) || (int) $brut > self::TARIF_MAX) {
                    $erreurs[$cle] = 'Un montant en FCFA, en chiffres.';
                } else {
                    $maj[$colonne] = (int) $brut;
                }
            }
            if (array_key_exists('deplacement_inclus', $champs) && filter_var($champs['deplacement_inclus'], FILTER_VALIDATE_BOOLEAN)) {
                $maj['tarif_deplacement_fcfa'] = 0; // « Déplacement inclus dans le diagnostic »
            } elseif (array_key_exists('deplacement_inclus', $champs) && !array_key_exists('tarif_deplacement', $champs)) {
                $maj['tarif_deplacement_fcfa'] = null;
            }
            if (array_key_exists('presentation', $champs)) {
                $t = trim((string) $champs['presentation']);
                if (mb_strlen($t) > self::PRESENTATION_MAX) {
                    $erreurs['presentation'] = self::PRESENTATION_MAX . ' caractères au plus.';
                } else {
                    $maj['presentation'] = $t === '' ? null : $t;
                }
            }
            $maj['modifie_le'] = gmdate('Y-m-d H:i:s');
            $this->db->modifier('mecaniciens', $maj, ['id' => $id]);
        });
        return ['erreurs' => $erreurs, 'enregistre_a' => Format::heure($maintenant)];
    }

    // ---------- M1c · les pièces ----------

    /**
     * L'état des pièces pour M1c et pour l'écrou : par type, « envoyee » ou « manquante » (« facultative » pour l'atelier absent).
     *
     * @return array{etats: array<string, string>, courantes: array<string, array<string, mixed>>, obligatoires_envoyees: int, obligatoires: int, manquantes: list<string>}
     */
    public function etatPieces(int $id): array
    {
        $courantes = $this->pieces()->courantes($id);
        $etats = [];
        $manquantes = [];
        $envoyees = 0;
        foreach (PieceDossierService::TYPES as $type) {
            $c = $courantes[$type] ?? null;
            if ($c !== null) {
                $etats[$type] = $c['statut'] === 'a_reprendre' ? 'a_reprendre' : 'envoyee';
                if ($type !== 'atelier') {
                    $envoyees++;
                }
            } else {
                $etats[$type] = $type === 'atelier' ? 'facultative' : 'manquante';
                if ($type !== 'atelier') {
                    $manquantes[] = $type;
                }
            }
        }
        return ['etats' => $etats, 'courantes' => $courantes, 'obligatoires_envoyees' => $envoyees, 'obligatoires' => 5, 'manquantes' => $manquantes];
    }

    /** « Il manque le diplôme et la photo de vous. » Les pièces nommées avec leur article, jointes par « et ». */
    public static function phraseManque(array $manquantes): string
    {
        $noms = array_map(static fn (string $t): string => self::NOMS_PIECES[$t], $manquantes);
        $dernier = array_pop($noms);
        return $noms === [] ? (string) $dernier : implode(', ', $noms) . ' et ' . $dernier;
    }

    // ---------- M1d · relire et envoyer ----------

    /** Ce qui empêche encore l'envoi, en phrases pour l'écran ; vide quand tout est prêt. @return list<string> */
    public function manques(int $id): array
    {
        $b = $this->brouillon($id);
        $m = [];
        if ($b === null) {
            return ['Compte introuvable.'];
        }
        if ($b['specialites'] === []) {
            $m[] = 'Choisissez au moins une spécialité.';
        }
        if ($b['vehicules'] === null) {
            $m[] = 'Dites quels véhicules vous réparez.';
        }
        if ($b['quartier'] === null) {
            $m[] = 'Indiquez le quartier de votre atelier.';
        }
        $pieces = $this->etatPieces($id);
        if ($pieces['manquantes'] !== []) {
            $m[] = 'Il manque ' . self::phraseManque($pieces['manquantes']) . '.';
        }
        return $m;
    }

    /**
     * Envoie le dossier à l'équipe (RG-DO3) : toutes les pièces obligatoires, les trois accords cochés (jamais pré-cochés).
     * Le dossier passe « en attente », les admins sont prévenus, le mécano reçoit l'accusé.
     *
     * @param array<string, mixed> $accords cgu, verification, visibilite
     * @return array{ok: true, numero: string}|array{ok: false, erreur: string, manques?: list<string>}
     */
    public function envoyer(int $id, array $accords, ?\DateTimeImmutable $maintenant = null): array
    {
        $b = $this->brouillon($id);
        if ($b === null || $b['statut'] !== 'brouillon') {
            return ['ok' => false, 'erreur' => 'Ce dossier a déjà été envoyé.'];
        }
        foreach (self::ACCORDS as $cle) {
            if (!filter_var($accords[$cle] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return ['ok' => false, 'erreur' => 'Cochez les 3 cases pour envoyer votre dossier.'];
            }
        }
        $manques = $this->manques($id);
        if ($manques !== []) {
            return ['ok' => false, 'erreur' => $manques[0], 'manques' => $manques];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $quand = $maintenant->format('Y-m-d H:i:s');
        $this->db->transaction(function () use ($id, $b, $quand): void {
            $this->db->modifier('mecaniciens', ['statut_dossier' => 'en_attente', 'dossier_envoye_le' => $quand, 'cgu_version' => self::CGU_VERSION, 'cgu_acceptees_le' => $quand], ['id' => $id]);
            $this->db->inserer('historique_dossier', ['mecanicien_id' => $id, 'de_statut' => 'brouillon', 'vers_statut' => 'en_attente', 'admin_id' => null, 'cree_le' => $quand]);
            $numero = DossierService::numero($id, $quand);
            $this->notifier(null, $id, $b['email'], 'dossier_recu', 'Votre dossier est bien reçu', 'Bonjour ' . $b['prenom'] . ', votre dossier ' . $numero . ' est bien reçu. Réponse sous ' . Parametres::int('dossier.delai_annonce_h', 48) . ' h.');
            foreach ($this->db->tous('SELECT id, email FROM admins WHERE actif = 1') as $a) {
                $this->notifier((int) $a['id'], null, $a['email'], 'nouveau_dossier', 'Nouveau dossier à examiner', $b['prenom'] . ' ' . $b['nom'] . ' vient d’envoyer son dossier ' . $numero . '.');
            }
        });
        return ['ok' => true, 'numero' => self::numeroAffiche($id)];
    }

    /** « 0412 » : le numéro tel qu'il s'écrit sur M1e. */
    public static function numeroAffiche(int $id): string
    {
        return sprintf('%04d', $id);
    }

    /** Un message par e-mail s'il y a une adresse ; sinon il attend dans l'espace du destinataire. */
    private function notifier(?int $admin, ?int $mecano, mixed $email, string $type, string $titre, string $texte): void
    {
        $envoye = is_string($email) && $email !== '' && ($this->mail ?? MailService::depuisEnv())->envoyer($email, $titre, $texte . "\n\nallomécano");
        $this->db->inserer('notifications', ['mecanicien_id' => $mecano, 'admin_id' => $admin, 'type' => $type, 'canal' => is_string($email) && $email !== '' ? 'email' : 'push', 'statut' => $envoye ? 'envoyee' : 'en_attente',
            'charge' => json_encode(['titre' => $titre, 'texte' => $texte, 'lien' => $admin !== null ? '/admin/dossiers' : '/pro'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'cree_le' => gmdate('Y-m-d H:i:s'), 'envoyee_le' => $envoye ? gmdate('Y-m-d H:i:s') : null]);
    }
}
