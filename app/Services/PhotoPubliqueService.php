<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Photos publiques d'un mécano validé (RG-DO9) : trois copies WebP (160, 360 et 720 px, sans EXIF), la couleur dominante et le point de cadrage.
 * La pièce d'origine ne quitte jamais `storage/pieces` ; seules ces copies sont dans `public/medias/m/<identifiant>/`.
 */
final class PhotoPubliqueService
{
    public const LARGEURS = [160, 360, 720];

    public function __construct(private readonly ?string $racine = null)
    {
    }

    /**
     * @return ?array{dossier: string, largeurs: string, ratio: float, couleur_dominante: string, focal_x: float, focal_y: float}
     *         dossier : chemin relatif dans public/medias, sans la largeur (« m/01J…/portrait-12 ») ; nul si la pièce n'est pas une image lisible
     */
    public function generer(string $sourceAbsolue, string $mecanoPublicId, int $pieceId, string $type): ?array
    {
        $info = @getimagesize($sourceAbsolue);
        if ($info === false) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourceAbsolue),
            IMAGETYPE_PNG => @imagecreatefrompng($sourceAbsolue),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourceAbsolue),
            default => false,
        };
        if ($img === false || !function_exists('imagewebp')) {
            return null;
        }
        $l = imagesx($img);
        $h = imagesy($img);
        $base = $this->racine ?? dirname(__DIR__, 2) . '/public/medias';
        $dossier = "m/$mecanoPublicId/$type-$pieceId";
        $cible = $base . '/' . dirname($dossier);
        if (!is_dir($cible)) {
            mkdir($cible, 0775, true);
        }
        $largeurs = [];
        foreach (self::LARGEURS as $w) {
            $w = min($w, $l);
            $copie = imagescale($img, $w, (int) round($h * $w / $l), IMG_BICUBIC);
            if ($copie === false) {
                continue;
            }
            imagewebp($copie, $base . '/' . $dossier . "-$w.webp", 82); // la recompression ne garde aucune donnée EXIF
            $largeurs[] = $w;
        }
        if ($largeurs === []) {
            return null;
        }
        $un = imagecreatetruecolor(1, 1); // la moyenne de toute l'image, en un pixel
        imagecopyresampled($un, $img, 0, 0, 0, 0, 1, 1, $l, $h);
        $rgb = imagecolorat($un, 0, 0);
        return ['dossier' => $dossier, 'largeurs' => implode(',', array_unique($largeurs)), 'ratio' => round($l / $h, 3), 'couleur_dominante' => sprintf('#%06x', $rgb & 0xFFFFFF),
            'focal_x' => 0.5, 'focal_y' => $type === 'portrait' ? 0.3 : 0.5];
    }
}
