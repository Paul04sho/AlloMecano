<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Une pièce du dossier telle que l'admin la voit (RG-P6) : un filigrane à son nom, en travers, répété sur toute l'image (« ALLOMÉCANO · VÉRIFICATION · A. BAMBA · 21/10 · 17:14 »).
 * Il est incrusté dans l'image envoyée : on ne peut pas l'enlever en retouchant la page. Ni téléchargement ni cache (en-têtes posés par le contrôleur).
 */
final class FiligranePiece
{
    private const LARGEUR_MAX = 1400;

    /** @return ?string le JPEG filigrané, nul si le fichier n'est pas une image lisible */
    public static function rendre(string $fichierAbsolu, string $texte, float $opacite = 0.4): ?string
    {
        $info = @getimagesize($fichierAbsolu);
        if ($info === false) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fichierAbsolu),
            IMAGETYPE_PNG => @imagecreatefrompng($fichierAbsolu),
            IMAGETYPE_WEBP => @imagecreatefromwebp($fichierAbsolu),
            default => false,
        };
        if ($img === false) {
            return null;
        }
        if (imagesx($img) > self::LARGEUR_MAX) {
            $img = imagescale($img, self::LARGEUR_MAX, -1, IMG_BICUBIC) ?: $img;
        }
        $l = imagesx($img);
        $h = imagesy($img);
        $sortie = imagecreatetruecolor($l, $h);
        imagecopy($sortie, $img, 0, 0, 0, 0, $l, $h);
        imagealphablending($sortie, true);

        // Le texte : blanc semi-transparent, agrandi, puis penché de 30° comme dans la maquette
        $police = 4;
        $tl = strlen($texte) * imagefontwidth($police) + 8;
        $th = imagefontheight($police) + 6;
        $ligne = imagecreatetruecolor($tl, $th);
        imagealphablending($ligne, false);
        imagesavealpha($ligne, true);
        imagefill($ligne, 0, 0, (int) imagecolorallocatealpha($ligne, 0, 0, 0, 127));
        $alpha = (int) round(127 * (1 - $opacite));
        imagestring($ligne, $police, 4, 3, $texte, (int) imagecolorallocatealpha($ligne, 244, 245, 242, $alpha));
        // Une ombre fine pour qu'il se lise aussi sur les fonds clairs
        imagestring($ligne, $police, 5, 4, $texte, (int) imagecolorallocatealpha($ligne, 21, 23, 28, min(127, $alpha + 20)));
        imagestring($ligne, $police, 4, 3, $texte, (int) imagecolorallocatealpha($ligne, 244, 245, 242, $alpha));
        $penche = imagerotate($ligne, 30, (int) imagecolorallocatealpha($ligne, 0, 0, 0, 127));
        if ($penche !== false) {
            imagealphablending($penche, false);
            imagesavealpha($penche, true);
            $pl = imagesx($penche);
            $ph = imagesy($penche);
            $pasY = max(1, (int) ($ph * 0.9));
            $pasX = max(1, (int) ($pl * 0.95));
            for ($rang = 0, $y = -$ph; $y < $h + $ph; $rang++, $y += $pasY) {
                for ($x = -intdiv($pl, 2) + ($rang % 2 === 1 ? intdiv($pl, 2) : 0); $x < $l; $x += $pasX) {
                    imagecopy($sortie, $penche, $x, $y, 0, 0, $pl, $ph);
                }
            }
        }
        ob_start();
        imagejpeg($sortie, null, 85);
        return (string) ob_get_clean();
    }
}
