<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;
use App\Core\Validator;

/**
 * « Mon profil » du mécano (M4 · 120:1169) : sa fiche telle que les clients la voient, ce qu'il peut y changer, son compte.
 * Changer de nom ou de portrait renvoie le dossier en vérification (RG-DO7) ; tout le reste se modifie librement.
 * Supprimer le compte se confirme en tapant son nom (RG-C6).
 */
final class ProfilMecanoService
{
    public const PHOTOS_ATELIER_MAX = 3;
    public const TAILLE_MAX_O = 5 * 1024 * 1024;

    public function __construct(private readonly Database $db, private readonly ?string $racinePublique = null, private readonly ?string $racinePieces = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Tout ce que M4 affiche. Nul si le compte n'existe plus.
     *
     * @return ?array<string, mixed>
     */
    public function fiche(int $id): ?array
    {
        $m = $this->db->un(
            'SELECT m.id, m.public_id, m.slug, m.prenom, m.nom, m.metier, m.annees_experience, m.presentation, m.statut_dossier, m.etat, m.identite_verifiee_le, m.rayon_km, m.atelier_lat, m.atelier_lon,
                    m.tarif_diagnostic_fcfa, m.tarif_deplacement_fcfa, m.telephone, m.whatsapp, m.email, m.mot_de_passe_hash, m.google_sub, COALESCE(m.mot_de_passe_modifie_le, m.cree_le) AS mdp_le,
                    qa.nom AS quartier, qa.slug AS quartier_slug, co.nom AS commune
               FROM mecaniciens m LEFT JOIN quartiers qa ON qa.id = m.atelier_quartier_id LEFT JOIN communes co ON co.id = qa.commune_id
              WHERE m.id = :i AND m.supprime_le IS NULL',
            ['i' => $id]
        );
        if ($m === null) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        $date = static fn (?string $d): ?\DateTimeImmutable => $d !== null ? new \DateTimeImmutable($d, $utc) : null;
        $longue = static fn (?\DateTimeImmutable $d): ?string => $d !== null ? TableauMecanoService::jourMois($d) . ' ' . $d->format('Y') : null;
        $photos = $this->db->tous('SELECT type, dossier, largeurs, ratio, couleur_dominante, focal_x, focal_y FROM photos WHERE mecanicien_id = :m AND active = 1 ORDER BY id', ['m' => $id]);
        $portrait = null;
        $atelier = [];
        foreach ($photos as $p) {
            if ($p['type'] === 'portrait') {
                $portrait = PhotoService::portrait($p);
            } else {
                $atelier[] = ['reference' => basename((string) $p['dossier'])] + (PhotoService::portrait($p) ?? []);
            }
        }
        $specs = array_column($this->db->tous('SELECT s.code, s.libelle FROM mecanicien_specialites ms JOIN specialites s ON s.id = ms.specialite_id WHERE ms.mecanicien_id = :m ORDER BY s.ordre', ['m' => $id]), 'libelle', 'code');
        $verif = $date($m['identite_verifiee_le']);
        return [
            'id' => (int) $m['id'], 'public_id' => (string) $m['public_id'], 'slug' => $m['slug'], 'prenom' => (string) $m['prenom'], 'nom' => (string) $m['nom'], 'nom_complet' => $m['prenom'] . ' ' . $m['nom'],
            'statut' => (string) $m['statut_dossier'], 'etat' => (string) $m['etat'], 'annees' => (int) $m['annees_experience'], 'presentation' => $m['presentation'],
            'metier' => (string) $m['metier'] . ((int) $m['annees_experience'] > 0 ? ' · ' . $m['annees_experience'] . ' an' . ((int) $m['annees_experience'] > 1 ? 's' : '') . ' de métier' : ''),
            'verifie_le' => $longue($verif), 'portrait' => $portrait, 'portrait_piece' => PieceDossierService::depuisBase()->cheminDe($id, 'portrait') !== null,
            'specialites' => array_values($specs), 'codes_specialites' => array_keys($specs),
            'rayon' => (int) $m['rayon_km'], 'quartier' => $m['quartier'], 'commune' => $m['commune'], 'lat' => $m['atelier_lat'] !== null ? (float) $m['atelier_lat'] : null, 'lon' => $m['atelier_lon'] !== null ? (float) $m['atelier_lon'] : null,
            'zone' => $this->phraseZone($m['atelier_lat'] !== null ? (float) $m['atelier_lat'] : null, $m['atelier_lon'] !== null ? (float) $m['atelier_lon'] : null, (int) $m['rayon_km'], $m['quartier']),
            'diagnostic' => $m['tarif_diagnostic_fcfa'] !== null ? (int) $m['tarif_diagnostic_fcfa'] : null, 'deplacement' => $m['tarif_deplacement_fcfa'] !== null ? (int) $m['tarif_deplacement_fcfa'] : null,
            'atelier' => $atelier, 'telephone' => (string) $m['telephone'], 'whatsapp' => $m['whatsapp'] !== null ? (string) $m['whatsapp'] : null, 'email' => $m['email'],
            'mot_de_passe' => $m['mot_de_passe_hash'] !== null, 'mdp_le' => $longue($date($m['mdp_le'])),
        ];
    }

    /** « Cocody et Bingerville, autour de votre atelier à Riviera 3. » : les communes dont des quartiers sont dans le rayon, les plus fournies d'abord. */
    private function phraseZone(?float $lat, ?float $lon, int $rayonKm, ?string $quartier): string
    {
        if ($lat === null || $lon === null || $quartier === null) {
            return 'Indiquez le quartier de votre atelier : les clients autour de lui vous trouveront.';
        }
        $communes = [];
        foreach (PositionService::depuisBase()->quartiers() as $q) {
            if (GeoService::distanceM($lat, $lon, $q['lat'], $q['lon']) <= $rayonKm * 1000) {
                $communes[$q['commune_nom']] = ($communes[$q['commune_nom']] ?? 0) + 1;
            }
        }
        arsort($communes);
        $noms = array_slice(array_keys($communes), 0, 3);
        $dernier = array_pop($noms);
        $liste = $noms === [] ? (string) $dernier : implode(', ', $noms) . ' et ' . $dernier;
        return ($liste !== '' ? $liste . ', autour' : 'Autour') . ' de votre atelier à ' . $quartier . '.';
    }

    // ---------- Photo et identité (RG-DO7) ----------

    /**
     * Changer de nom ou de portrait renvoie le dossier en vérification : le poinçon disparaît et la fiche sort des résultats jusqu'à la réponse de l'équipe (RG-DO7, RG-DO8).
     *
     * @param ?array{tmp_name?: string, size?: int, error?: int} $portrait
     * @return array{ok: true, renvoye: bool}|array{ok: false, erreurs: array<string, string>}
     */
    public function modifierIdentite(int $id, string $prenom, string $nom, ?array $portrait, ?\DateTimeImmutable $maintenant = null): array
    {
        $prenom = trim($prenom);
        $nom = trim($nom);
        $v = Validator::verifier(['prenom' => $prenom, 'nom' => $nom], ['prenom' => 'requis|max:60', 'nom' => 'requis|max:60']);
        $erreurs = [];
        foreach ($v->erreurs() as $champ => $message) {
            $erreurs[$champ] = $champ === 'prenom' ? 'Indiquez votre prénom.' : 'Indiquez votre nom.';
        }
        $m = $this->db->un('SELECT prenom, nom, statut_dossier FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        if ($m === null) {
            return ['ok' => false, 'erreurs' => ['prenom' => 'Compte introuvable.']];
        }
        $nouvellePhoto = $portrait !== null && (int) ($portrait['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $change = $prenom !== $m['prenom'] || $nom !== $m['nom'] || $nouvellePhoto;
        if (!$change) {
            return ['ok' => true, 'renvoye' => false];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($nouvellePhoto) {
            $r = ($this->racinePieces !== null ? new PieceDossierService($this->db, $this->racinePieces) : PieceDossierService::depuisBase())->deposer($id, 'portrait', $portrait, $maintenant);
            if (!$r['ok']) {
                return ['ok' => false, 'erreurs' => ['portrait' => $r['erreur']]];
            }
        }
        $quand = $maintenant->format('Y-m-d H:i:s');
        $renvoye = $m['statut_dossier'] === 'valide';
        $this->db->transaction(function () use ($id, $prenom, $nom, $quand, $renvoye): void {
            $maj = ['prenom' => $prenom, 'nom' => $nom];
            if ($renvoye) { // RG-DO7 : nom ou portrait changé après validation
                $maj += ['statut_dossier' => 'en_attente', 'dossier_envoye_le' => $quand, 'etat' => 'indisponible', 'etat_depuis' => $quand, 'identite_verifiee_le' => null];
                $this->db->executer('UPDATE historique_disponibilite SET fin = :f WHERE mecanicien_id = :m AND fin IS NULL', ['f' => $quand, 'm' => $id]);
                $this->db->inserer('historique_disponibilite', ['mecanicien_id' => $id, 'etat' => 'indisponible', 'raison' => 'manuel', 'debut' => $quand]);
                $this->db->inserer('historique_dossier', ['mecanicien_id' => $id, 'de_statut' => 'valide', 'vers_statut' => 'en_attente', 'admin_id' => null, 'cree_le' => $quand]);
                foreach ($this->db->tous('SELECT id, email FROM admins WHERE actif = 1') as $a) {
                    $this->notifierAdmin((int) $a['id'], $a['email'], 'Identité modifiée, dossier à revoir', $prenom . ' ' . $nom . ' a changé son nom ou son portrait : le dossier repasse en vérification.');
                }
            }
            $this->db->modifier('mecaniciens', $maj, ['id' => $id]);
        });
        return ['ok' => true, 'renvoye' => $renvoye];
    }

    private function notifierAdmin(int $admin, mixed $email, string $titre, string $texte): void
    {
        $this->db->inserer('notifications', ['admin_id' => $admin, 'type' => 'nouveau_dossier', 'canal' => 'push', 'statut' => 'en_attente',
            'charge' => json_encode(['titre' => $titre, 'texte' => $texte, 'lien' => '/admin/dossiers'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'cree_le' => gmdate('Y-m-d H:i:s')]);
    }

    // ---------- Téléphone et WhatsApp ----------

    /**
     * Le numéro des appels et, s'il est différent, celui de WhatsApp. Le téléphone sert aussi à se connecter (RG-C1) : il reste unique.
     *
     * @return array{ok: true}|array{ok: false, erreurs: array<string, string>}
     */
    public function modifierContacts(int $id, string $telephone, string $whatsapp): array
    {
        $telephone = trim($telephone);
        $whatsapp = trim($whatsapp);
        $v = Validator::verifier(['telephone' => $telephone, 'whatsapp' => $whatsapp], ['telephone' => 'requis|telephone_ci', 'whatsapp' => 'telephone_ci']);
        $erreurs = $v->erreurs();
        if (isset($erreurs['telephone'])) {
            $erreurs['telephone'] = $telephone === '' ? 'Indiquez le numéro sur lequel les clients vous appelleront.' : $erreurs['telephone'];
        }
        if (!isset($erreurs['telephone']) && $this->db->valeur('SELECT id FROM mecaniciens WHERE telephone = :t AND id <> :m', ['t' => Format::telephoneE164($telephone), 'm' => $id]) !== null) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé par un autre compte.';
        }
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $e164 = Format::telephoneE164($telephone);
        $wa = $whatsapp !== '' && Format::telephoneE164($whatsapp) !== $e164 ? Format::telephoneE164($whatsapp) : null;
        $this->db->modifier('mecaniciens', ['telephone' => $e164, 'whatsapp' => $wa], ['id' => $id]);
        return ['ok' => true];
    }

    // ---------- Mot de passe (RG-C1, RG-C4) ----------

    /** @return array{ok: true}|array{ok: false, erreurs: array<string, string>} */
    public function changerMotDePasse(int $id, string $actuel, string $nouveau, ?\DateTimeImmutable $maintenant = null): array
    {
        $hash = $this->db->valeur('SELECT mot_de_passe_hash FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        $erreurs = [];
        if (!is_string($hash) || !password_verify($actuel, $hash)) {
            $erreurs['actuel'] = 'Ce n’est pas votre mot de passe actuel.';
        }
        $v = Validator::verifier(['nouveau' => $nouveau], ['nouveau' => 'requis|mot_de_passe']);
        if ($v->erreurs() !== []) {
            $erreurs['nouveau'] = 'Choisissez un mot de passe : 8 caractères au minimum, dont 1 chiffre.';
        } elseif ($actuel === $nouveau) {
            $erreurs['nouveau'] = 'Choisissez un mot de passe différent de l’actuel.';
        }
        if ($erreurs !== []) {
            return ['ok' => false, 'erreurs' => $erreurs];
        }
        $this->db->modifier('mecaniciens', ['mot_de_passe_hash' => password_hash($nouveau, PASSWORD_DEFAULT), 'mot_de_passe_modifie_le' => ($maintenant ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')], ['id' => $id]);
        return ['ok' => true];
    }

    // ---------- Photos de l'atelier (jusqu'à 3, visibles sur la fiche) ----------

    /**
     * Une photo de plus pour la fiche : recompressée en WebP (160, 360, 720 px), sans EXIF, la couleur dominante gardée (RG-DO9).
     *
     * @param array{tmp_name?: string, size?: int, error?: int} $fichier
     * @return array{ok: true, reference: string}|array{ok: false, erreur: string}
     */
    public function ajouterPhotoAtelier(int $id, array $fichier): array
    {
        if ((int) $this->db->valeur('SELECT COUNT(*) FROM photos WHERE mecanicien_id = :m AND type = \'atelier\' AND active = 1', ['m' => $id]) >= self::PHOTOS_ATELIER_MAX) {
            return ['ok' => false, 'erreur' => 'Vous avez déjà ' . self::PHOTOS_ATELIER_MAX . ' photos. Retirez-en une pour en ajouter une autre.'];
        }
        $erreur = (int) ($fichier['error'] ?? UPLOAD_ERR_NO_FILE);
        $source = (string) ($fichier['tmp_name'] ?? '');
        if ($erreur === UPLOAD_ERR_INI_SIZE || $erreur === UPLOAD_ERR_FORM_SIZE || (int) ($fichier['size'] ?? 0) > self::TAILLE_MAX_O) {
            return ['ok' => false, 'erreur' => 'Ce fichier est trop lourd : 5 Mo au plus. Reprenez la photo en plus petit.'];
        }
        if ($erreur !== UPLOAD_ERR_OK || $source === '' || !is_file($source)) {
            return ['ok' => false, 'erreur' => 'Le fichier n’est pas arrivé. Réessayez.'];
        }
        $info = @getimagesize($source);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return ['ok' => false, 'erreur' => 'Envoyez une photo (JPEG, PNG ou WebP).'];
        }
        $public = (string) $this->db->valeur('SELECT public_id FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        $copie = (new PhotoPubliqueService($this->racinePublique))->generer($source, $public, random_int(100000, 99999999), 'atelier');
        if ($copie === null) {
            return ['ok' => false, 'erreur' => 'Cette photo n’a pas pu être lue. Reprenez-la.'];
        }
        $this->db->inserer('photos', ['mecanicien_id' => $id, 'type' => 'atelier', 'piece_id' => null, 'dossier' => $copie['dossier'], 'largeurs' => $copie['largeurs'], 'ratio' => $copie['ratio'],
            'couleur_dominante' => $copie['couleur_dominante'], 'focal_x' => $copie['focal_x'], 'focal_y' => $copie['focal_y'], 'active' => 1]);
        return ['ok' => true, 'reference' => basename($copie['dossier'])];
    }

    /** Retire une photo de l'atelier de la fiche (la référence est le nom du fichier, jamais un numéro interne, RG-C8). */
    public function retirerPhotoAtelier(int $id, string $reference): bool
    {
        $p = $this->db->un('SELECT id, dossier FROM photos WHERE mecanicien_id = :m AND type = \'atelier\' AND active = 1 AND dossier LIKE :r', ['m' => $id, 'r' => '%/' . str_replace(['%', '_'], ['\\%', '\\_'], $reference)]);
        if ($p === null || basename((string) $p['dossier']) !== $reference) {
            return false;
        }
        $this->db->modifier('photos', ['active' => 0], ['id' => (int) $p['id']]);
        $base = realpath($this->racinePublique ?? dirname(__DIR__, 2) . '/public/medias');
        foreach (PhotoPubliqueService::LARGEURS as $w) { // les copies publiques ne servent plus
            $chemin = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $p['dossier']) . "-$w.webp");
            if ($chemin !== false && str_starts_with($chemin, $base . DIRECTORY_SEPARATOR)) {
                @unlink($chemin);
            }
        }
        return true;
    }

    // ---------- Supprimer mon compte (RG-C6) ----------

    /**
     * Le mécano confirme en tapant son nom : ses données personnelles sont anonymisées, ses pièces effacées, sa fiche retirée. Les avis restent, sans nom.
     *
     * @return array{ok: true}|array{ok: false, erreur: string}
     */
    public function supprimer(int $id, string $nomTape): array
    {
        $m = $this->db->un('SELECT prenom, nom FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        if ($m === null) {
            return ['ok' => false, 'erreur' => 'Ce compte est introuvable.'];
        }
        $norm = static fn (string $s): string => PositionService::normaliser(preg_replace('/\s+/u', ' ', trim($s)) ?? '');
        if ($norm($nomTape) === '' || ($norm($nomTape) !== $norm($m['prenom'] . ' ' . $m['nom']))) {
            return ['ok' => false, 'erreur' => 'Tapez votre prénom et votre nom, comme sur votre fiche.'];
        }
        $fichiers = array_column($this->db->tous('SELECT fichier FROM pieces_dossier WHERE mecanicien_id = :m AND fichier IS NOT NULL AND fichier_supprime_le IS NULL', ['m' => $id]), 'fichier');
        $this->db->transaction(function () use ($id): void {
            $maintenant = gmdate('Y-m-d H:i:s');
            $this->db->modifier('mecaniciens', [
                'prenom' => 'Compte', 'nom' => 'supprimé', 'telephone' => '+000' . str_pad((string) $id, 10, '0', STR_PAD_LEFT), 'whatsapp' => null, 'email' => null, 'google_sub' => null,
                'mot_de_passe_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'presentation' => null, 'slug' => null, 'etat' => 'indisponible', 'statut_dossier' => 'refuse',
                'dispo_lat' => null, 'dispo_lon' => null, 'supprime_le' => $maintenant, 'date_naissance' => null,
            ], ['id' => $id]);
            $this->db->executer('UPDATE pieces_dossier SET fichier = NULL, fichier_supprime_le = :n WHERE mecanicien_id = :m', ['n' => $maintenant, 'm' => $id]);
            $this->db->executer('UPDATE photos SET active = 0 WHERE mecanicien_id = :m', ['m' => $id]);
            $this->db->executer('DELETE FROM abonnements_push WHERE mecanicien_id = :m', ['m' => $id]);
        });
        $racine = realpath($this->racinePieces ?? dirname(__DIR__, 2) . '/storage/pieces');
        foreach ($fichiers as $f) { // ce qui ne peut pas être effacé le sera par la purge
            $chemin = $racine === false ? false : realpath($racine . DIRECTORY_SEPARATOR . $f);
            if ($chemin !== false && str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR)) {
                @unlink($chemin);
            }
        }
        return ['ok' => true];
    }
}
