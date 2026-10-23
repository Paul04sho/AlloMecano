<?php

declare(strict_types=1);

namespace App\Services;

/**
 * QR code sans dépendance (D1 · « Scannez le QR code pour appeler avec votre téléphone »).
 * Mode octets, correction d'erreur M, versions 1 à 5 (jusqu'à 84 octets : un « tel:+2250707123456 » en fait 18).
 * Sortie : matrice de booléens ou SVG (un seul tracé, aucune image).
 */
final class QrCode
{
    /** version => [mots de code totaux, mots de données, mots de correction par bloc, nombre de blocs, positions d'alignement] */
    private const VERSIONS = [
        1 => [26, 16, 10, 1, []],
        2 => [44, 28, 16, 1, [6, 18]],
        3 => [70, 44, 26, 1, [6, 22]],
        4 => [100, 64, 18, 2, [6, 26]],
        5 => [134, 86, 24, 2, [6, 30]],
    ];

    /** @var list<int> */
    private static array $exp = [];
    /** @var list<int> */
    private static array $log = [];

    /** @return list<list<bool>> matrice carrée, true = module sombre */
    public static function matrice(string $texte, ?int $masque = null): array
    {
        $octets = array_values(unpack('C*', $texte) ?: []);
        $version = 0;
        foreach (self::VERSIONS as $v => [, $donnees]) {
            if (count($octets) <= $donnees - 2) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new \InvalidArgumentException('Texte trop long pour le QR code (84 octets au plus).');
        }
        [$total, $donnees, $correction, $blocs, $alignement] = self::VERSIONS[$version];
        $mots = self::motsDeCode($octets, $donnees, $correction, $blocs, $total);
        $taille = 17 + 4 * $version;

        // Modules fonctionnels (réservés) et données
        $m = array_fill(0, $taille, array_fill(0, $taille, false));
        $reserve = array_fill(0, $taille, array_fill(0, $taille, false));
        self::motifs($m, $reserve, $taille, $alignement);
        self::placer($m, $reserve, $taille, $mots);

        // Masque : celui demandé, sinon celui qui a la plus petite pénalité
        $meilleur = null;
        $score = PHP_INT_MAX;
        foreach ($masque !== null ? [$masque] : range(0, 7) as $k) {
            $c = self::masquer($m, $reserve, $taille, $k);
            self::format($c, $taille, $k);
            $p = self::penalite($c, $taille);
            if ($p < $score) {
                $score = $p;
                $meilleur = $c;
            }
        }
        return $meilleur;
    }

    /** SVG d'un seul tracé, zone de silence de 4 modules, couleur par `currentColor`. */
    public static function svg(string $texte, int $taillePx = 112): string
    {
        $m = self::matrice($texte);
        $n = count($m);
        $b = 4;
        $d = '';
        foreach ($m as $y => $ligne) {
            $x = 0;
            while ($x < $n) {
                if (!$ligne[$x]) {
                    $x++;
                    continue;
                }
                $debut = $x;
                while ($x < $n && $ligne[$x]) {
                    $x++;
                }
                $d .= 'M' . ($debut + $b) . ' ' . ($y + $b) . 'h' . ($x - $debut) . 'v1h-' . ($x - $debut) . 'z';
            }
        }
        $v = $n + 2 * $b;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $v . ' ' . $v . '" width="' . $taillePx . '" height="' . $taillePx
            . '" shape-rendering="crispEdges" role="img"><path fill="currentColor" d="' . $d . '"/></svg>';
    }

    // ---------- Mots de code ----------

    /**
     * @param list<int> $octets
     * @return list<int>
     */
    private static function motsDeCode(array $octets, int $donnees, int $correction, int $blocs, int $total): array
    {
        $bits = '0100' . str_pad(decbin(count($octets)), 8, '0', STR_PAD_LEFT);
        foreach ($octets as $o) {
            $bits .= str_pad(decbin($o), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $donnees * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $mots = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($mots) < $donnees; $pad++) {
            $mots[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        $parBloc = intdiv($donnees, $blocs);
        $paquets = array_chunk($mots, $parBloc);
        $corrections = array_map(static fn (array $p): array => self::reedSolomon($p, $correction), $paquets);
        $final = [];
        for ($i = 0; $i < $parBloc; $i++) {
            foreach ($paquets as $p) {
                $final[] = $p[$i];
            }
        }
        for ($i = 0; $i < $correction; $i++) {
            foreach ($corrections as $c) {
                $final[] = $c[$i];
            }
        }
        return $final;
    }

    private static function initialiserCorps(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    /**
     * @param list<int> $donnees
     * @return list<int>
     */
    private static function reedSolomon(array $donnees, int $n): array
    {
        self::initialiserCorps();
        $generateur = [1];
        for ($i = 0; $i < $n; $i++) {
            $suivant = array_fill(0, count($generateur) + 1, 0);
            foreach ($generateur as $j => $coef) {
                $suivant[$j] ^= $coef;
                $suivant[$j + 1] ^= $coef === 0 ? 0 : self::$exp[(self::$log[$coef] + $i) % 255];
            }
            $generateur = $suivant;
        }
        $reste = array_merge($donnees, array_fill(0, $n, 0));
        foreach ($donnees as $i => $_) {
            $facteur = $reste[$i];
            if ($facteur === 0) {
                continue;
            }
            foreach ($generateur as $j => $coef) {
                $reste[$i + $j] ^= $coef === 0 ? 0 : self::$exp[(self::$log[$coef] + self::$log[$facteur]) % 255];
            }
        }
        return array_slice($reste, count($donnees));
    }

    // ---------- Matrice ----------

    /**
     * @param list<list<bool>> $m
     * @param list<list<bool>> $reserve
     * @param list<int> $alignement
     */
    private static function motifs(array &$m, array &$reserve, int $n, array $alignement): void
    {
        // Repères de position + séparateurs
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$x0, $y0]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $x = $x0 + $dx;
                    $y = $y0 + $dy;
                    if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
                        continue;
                    }
                    $dedans = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                    $m[$y][$x] = $dedans && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                    $reserve[$y][$x] = true;
                }
            }
        }
        // Motif d'alignement (versions 2 à 5 : un seul, en bas à droite)
        if ($alignement !== []) {
            $c = $alignement[1];
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $m[$c + $dy][$c + $dx] = max(abs($dx), abs($dy)) !== 1;
                    $reserve[$c + $dy][$c + $dx] = true;
                }
            }
        }
        // Lignes de synchronisation
        for ($i = 8; $i < $n - 8; $i++) {
            $m[6][$i] = $i % 2 === 0;
            $m[$i][6] = $i % 2 === 0;
            $reserve[6][$i] = true;
            $reserve[$i][6] = true;
        }
        // Zones du format + module sombre
        for ($i = 0; $i < 9; $i++) {
            $reserve[8][$i] = true;
            $reserve[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $reserve[8][$n - 1 - $i] = true;
            $reserve[$n - 1 - $i][8] = true;
        }
        $m[$n - 8][8] = true;
        $reserve[$n - 8][8] = true;
    }

    /**
     * @param list<list<bool>> $m
     * @param list<list<bool>> $reserve
     * @param list<int> $mots
     */
    private static function placer(array &$m, array $reserve, int $n, array $mots): void
    {
        $bits = '';
        foreach ($mots as $mot) {
            $bits .= str_pad(decbin($mot), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', 7); // bits de reste (versions 2 à 5)
        $i = 0;
        $monte = true;
        for ($x = $n - 1; $x > 0; $x -= 2) {
            if ($x === 6) {
                $x--;
            }
            for ($k = 0; $k < $n; $k++) {
                $y = $monte ? $n - 1 - $k : $k;
                foreach ([$x, $x - 1] as $col) {
                    if (!$reserve[$y][$col]) {
                        $m[$y][$col] = ($bits[$i] ?? '0') === '1';
                        $i++;
                    }
                }
            }
            $monte = !$monte;
        }
    }

    /**
     * @param list<list<bool>> $m
     * @param list<list<bool>> $reserve
     * @return list<list<bool>>
     */
    private static function masquer(array $m, array $reserve, int $n, int $k): array
    {
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($reserve[$y][$x]) {
                    continue;
                }
                $inverse = match ($k) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($inverse) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
        return $m;
    }

    /** @param list<list<bool>> $m */
    private static function format(array &$m, int $n, int $masque): void
    {
        $donnees = (0b00 << 3) | $masque; // niveau M = 00
        $reste = $donnees << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($reste >> $i) & 1) {
                $reste ^= 0x537 << ($i - 10);
            }
        }
        $format = ((($donnees << 10) | $reste) ^ 0x5412);
        for ($i = 0; $i < 15; $i++) {
            $bit = (($format >> $i) & 1) === 1;
            // Autour du repère haut-gauche
            if ($i < 6) {
                $m[$i][8] = $bit;
            } elseif ($i < 8) {
                $m[$i + 1][8] = $bit;
            } elseif ($i === 8) {
                $m[8][7] = $bit;
            } else {
                $m[8][14 - $i] = $bit;
            }
            // Deuxième exemplaire : bas-gauche et haut-droite
            if ($i < 8) {
                $m[8][$n - 1 - $i] = $bit;
            } else {
                $m[$n - 15 + $i][8] = $bit;
            }
        }
        $m[$n - 8][8] = true;
    }

    /** @param list<list<bool>> $m */
    private static function penalite(array $m, int $n): int
    {
        $p = 0;
        // N1 : séries de 5 modules ou plus, en lignes puis en colonnes
        foreach ([false, true] as $colonnes) {
            for ($a = 0; $a < $n; $a++) {
                $serie = 1;
                for ($b = 1; $b < $n; $b++) {
                    $cur = $colonnes ? $m[$b][$a] : $m[$a][$b];
                    $prec = $colonnes ? $m[$b - 1][$a] : $m[$a][$b - 1];
                    if ($cur === $prec) {
                        $serie++;
                        if ($serie === 5) {
                            $p += 3;
                        } elseif ($serie > 5) {
                            $p++;
                        }
                    } else {
                        $serie = 1;
                    }
                }
            }
        }
        // N2 : blocs 2 × 2
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $p += 3;
                }
            }
        }
        // N3 : motif 1:1:3:1:1 entouré de 4 modules clairs
        $motif = [true, false, true, true, true, false, true];
        foreach ([false, true] as $colonnes) {
            for ($a = 0; $a < $n; $a++) {
                for ($b = 0; $b <= $n - 7; $b++) {
                    $ok = true;
                    for ($k = 0; $k < 7 && $ok; $k++) {
                        $ok = ($colonnes ? $m[$b + $k][$a] : $m[$a][$b + $k]) === $motif[$k];
                    }
                    if (!$ok) {
                        continue;
                    }
                    $clair = static function (int $i) use ($m, $a, $n, $colonnes): bool {
                        return $i < 0 || $i >= $n || !($colonnes ? $m[$i][$a] : $m[$a][$i]);
                    };
                    $avant = $clair($b - 1) && $clair($b - 2) && $clair($b - 3) && $clair($b - 4);
                    $apres = $clair($b + 7) && $clair($b + 8) && $clair($b + 9) && $clair($b + 10);
                    if ($avant || $apres) {
                        $p += 40;
                    }
                }
            }
        }
        // N4 : proportion de modules sombres
        $sombres = 0;
        foreach ($m as $ligne) {
            $sombres += count(array_filter($ligne));
        }
        $ecart = abs((int) round($sombres * 100 / ($n * $n)) - 50);
        return $p + intdiv($ecart, 5) * 10;
    }
}
