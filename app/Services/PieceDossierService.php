<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Ulid;

/**
 * Le dépôt d'une pièce du dossier (RG-DO2) : une photo (ou un fichier image) de 5 Mo au plus, recompressée en JPEG, EXIF supprimé
 * (l'orientation est appliquée d'abord), rangée hors du dossier public. Un nouvel envoi du même type remplace l'ancien sans l'effacer (historique).
 */
final class PieceDossierService
{
    public const TAILLE_MAX_O = 5 * 1024 * 1024;
    /** Plus grand côté après recompression : assez pour lire un numéro de permis, assez léger pour la 3G. */
    public const COTE_MAX = 2200;
    public const TYPES = ['cni_recto', 'cni_verso', 'permis', 'diplome', 'portrait', 'atelier'];

    public function __construct(private readonly Database $db, private readonly ?string $racine = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    private function racine(): string
    {
        return $this->racine ?? dirname(__DIR__, 2) . '/storage/pieces';
    }

    /**
     * @param array{tmp_name?: string, size?: int, error?: int} $fichier une entrée de $_FILES
     * @return array{ok: true, piece: array<string, mixed>}|array{ok: false, erreur: string}
     */
    public function deposer(int $mecano, string $type, array $fichier, ?\DateTimeImmutable $maintenant = null): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'erreur' => 'Cette pièce n’existe pas.'];
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
            return ['ok' => false, 'erreur' => 'Envoyez une photo (JPEG, PNG ou WebP). Les autres formats ne sont pas pris en charge.'];
        }
        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
        };
        if ($image === false) {
            return ['ok' => false, 'erreur' => 'Cette photo n’a pas pu être lue. Reprenez-la.'];
        }
        $image = $this->redresser($image, $source, $info[2]);
        $sortie = $this->recompresser($image);
        $m = $this->db->un('SELECT id, public_id FROM mecaniciens WHERE id = :i', ['i' => $mecano]);
        if ($m === null) {
            return ['ok' => false, 'erreur' => 'Compte introuvable.'];
        }
        $dossier = $this->racine() . '/' . $m['public_id'];
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return ['ok' => false, 'erreur' => 'La pièce n’a pas pu être enregistrée. Réessayez.'];
        }
        $nom = $type . '-' . strtolower(Ulid::generer()) . '.jpg';
        if (file_put_contents($dossier . '/' . $nom, $sortie['octets']) === false) {
            return ['ok' => false, 'erreur' => 'La pièce n’a pas pu être enregistrée. Réessayez.'];
        }
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $id = $this->db->transaction(function () use ($mecano, $type, $m, $nom, $sortie, $maintenant): int {
            $this->db->executer('UPDATE pieces_dossier SET statut = \'remplacee\' WHERE mecanicien_id = :m AND type_piece = :t AND statut <> \'remplacee\'', ['m' => $mecano, 't' => $type]);
            return $this->db->inserer('pieces_dossier', [
                'mecanicien_id' => $mecano, 'type_piece' => $type, 'fichier' => $m['public_id'] . '/' . $nom, 'mime' => 'image/jpeg', 'taille_octets' => strlen($sortie['octets']),
                'empreinte_sha256' => hash('sha256', $sortie['octets']), 'largeur' => $sortie['largeur'], 'hauteur' => $sortie['hauteur'], 'statut' => 'envoyee', 'cree_le' => $maintenant->format('Y-m-d H:i:s'),
            ]);
        });
        return ['ok' => true, 'piece' => ['id' => $id, 'type' => $type, 'taille_octets' => strlen($sortie['octets']), 'statut' => 'envoyee']];
    }

    /** La photo telle que l'appareil l'a prise : on tourne selon l'orientation EXIF, puis plus rien de l'EXIF ne survivra à la recompression. */
    private function redresser(\GdImage $image, string $source, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($source);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $tournee = imagerotate($image, $angle, 0);
        return $tournee !== false ? $tournee : $image;
    }

    /** @return array{octets: string, largeur: int, hauteur: int} */
    private function recompresser(\GdImage $image): array
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $plus = max($w, $h);
        if ($plus > self::COTE_MAX) {
            $k = self::COTE_MAX / $plus;
            $nw = max(1, (int) round($w * $k));
            $nh = max(1, (int) round($h * $k));
            $reduite = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($reduite, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $image = $reduite;
            [$w, $h] = [$nw, $nh];
        } else {
            $fond = imagecreatetruecolor($w, $h);
            imagefill($fond, 0, 0, (int) imagecolorallocate($fond, 255, 255, 255)); // les PNG transparents passent sur fond blanc
            imagecopy($fond, $image, 0, 0, 0, 0, $w, $h);
            $image = $fond;
        }
        ob_start();
        imagejpeg($image, null, 82);
        return ['octets' => (string) ob_get_clean(), 'largeur' => $w, 'hauteur' => $h];
    }

    /**
     * La pièce courante de chaque type, la plus récente non remplacée.
     *
     * @return array<string, array{id: int, statut: string, taille_octets: int, motif: ?string, fichier: ?string}>
     */
    public function courantes(int $mecano): array
    {
        $r = [];
        foreach ($this->db->tous('SELECT id, type_piece, statut, taille_octets, motif, fichier FROM pieces_dossier WHERE mecanicien_id = :m AND statut <> \'remplacee\' ORDER BY id', ['m' => $mecano]) as $p) {
            $r[(string) $p['type_piece']] = ['id' => (int) $p['id'], 'statut' => (string) $p['statut'], 'taille_octets' => (int) $p['taille_octets'], 'motif' => $p['motif'], 'fichier' => $p['fichier']];
        }
        return $r;
    }

    /** Le fichier d'une pièce du mécano lui-même (sa propre photo, par exemple pour l'aperçu de sa ligne) : jamais celui d'un autre. @return ?string chemin absolu */
    public function cheminDe(int $mecano, string $type): ?string
    {
        $p = $this->courantes($mecano)[$type] ?? null;
        if ($p === null || $p['fichier'] === null) {
            return null;
        }
        $racine = realpath($this->racine());
        $chemin = $racine !== false ? realpath($racine . DIRECTORY_SEPARATOR . $p['fichier']) : false;
        return $chemin !== false && str_starts_with($chemin, $racine . DIRECTORY_SEPARATOR) ? $chemin : null;
    }
}
