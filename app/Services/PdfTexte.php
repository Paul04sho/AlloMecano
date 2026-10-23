<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Un PDF de texte (CGU et confidentialité, « Télécharger en PDF » de X5) sans aucune bibliothèque : pages A4, Helvetica, retours à la ligne par estimation de largeur.
 * Les blocs : « titre » (gras 18), « rubrique » (gras 11), « sous-titre » (gras 13), « texte » (10,5), « filet » (espace).
 */
final class PdfTexte
{
    private const LARGEUR = 595;
    private const HAUTEUR = 842;
    private const MARGE = 56;

    /** @param list<array{0: string, 1: string}> $blocs paires [type, texte] */
    public static function generer(string $titre, array $blocs): string
    {
        $pages = [[]];
        $y = self::HAUTEUR - self::MARGE;
        $tailles = ['titre' => [18, 'F2', 24, 6], 'sous-titre' => [13, 'F2', 18, 4], 'rubrique' => [9, 'F2', 14, 2], 'texte' => [10.5, 'F1', 15, 6], 'filet' => [0, 'F1', 0, 10]];
        foreach ($blocs as [$type, $texte]) {
            [$taille, $police, $interligne, $apres] = $tailles[$type];
            $lignes = $taille > 0 ? self::couper(self::latin($texte), (int) floor((self::LARGEUR - 2 * self::MARGE) / ($taille * ($police === 'F2' ? 0.56 : 0.5)))) : [];
            foreach ($lignes as $ligne) {
                if ($y - $interligne < self::MARGE) {
                    $pages[] = [];
                    $y = self::HAUTEUR - self::MARGE;
                }
                $y -= $interligne;
                $pages[count($pages) - 1][] = sprintf("BT /%s %s Tf %d %s Td (%s) Tj ET\n", $police, rtrim(rtrim(number_format($taille, 1, '.', ''), '0'), '.'), self::MARGE, number_format($y, 2, '.', ''), self::echapper($ligne));
            }
            $y -= $apres;
        }
        // objets : 1 catalogue, 2 pages, 3 Helvetica, 4 Helvetica-Bold, 5 info, puis (page, contenu) par page
        $objets = [1 => '', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            5 => '<< /Title (' . self::echapper(self::latin($titre)) . ') /Producer (allomecano) >>'];
        $enfants = [];
        foreach ($pages as $i => $lignes) {
            $page = 6 + 2 * $i;
            $flux = implode('', $lignes);
            $objets[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::LARGEUR . ' ' . self::HAUTEUR . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($page + 1) . ' 0 R >>';
            $objets[$page + 1] = '<< /Length ' . strlen($flux) . " >>\nstream\n" . $flux . 'endstream';
            $enfants[] = $page . ' 0 R';
        }
        $objets[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objets[2] = '<< /Type /Pages /Kids [' . implode(' ', $enfants) . '] /Count ' . count($enfants) . ' >>';
        ksort($objets);
        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $n => $corps) {
            $positions[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n$corps\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objets) + 1) . "\n0000000000 65535 f \n";
        foreach ($positions as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }
        return $pdf . 'trailer << /Size ' . (count($objets) + 1) . ' /Root 1 0 R /Info 5 0 R >>' . "\nstartxref\n$xref\n%%EOF\n";
    }

    /** UTF-8 → Windows-1252 (accents, guillemets, apostrophe courbe) ; l'espace fine devient une espace. */
    private static function latin(string $texte): string
    {
        $texte = str_replace(["\u{202F}", "\u{00A0}", "\u{2009}"], ' ', $texte);
        return mb_convert_encoding($texte, 'Windows-1252', 'UTF-8');
    }

    private static function echapper(string $texte): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texte);
    }

    /** @return list<string> */
    private static function couper(string $texte, int $max): array
    {
        $lignes = [];
        $ligne = '';
        foreach (explode(' ', $texte) as $mot) {
            if ($ligne !== '' && strlen($ligne) + 1 + strlen($mot) > $max) {
                $lignes[] = $ligne;
                $ligne = $mot;
            } else {
                $ligne = $ligne === '' ? $mot : $ligne . ' ' . $mot;
            }
        }
        return $ligne !== '' ? [...$lignes, $ligne] : $lignes;
    }
}
