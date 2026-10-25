<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typographie française de la spec (3.3) : espaces fines insécables, « 1,2 km », « 5 000 FCFA »,
 * « +225 07 07 12 34 56 », dates en toutes lettres.
 */
final class Format
{
    public const FINE = "\u{202F}";      // espace fine insécable (avant ? ! : ; et dans les milliers)
    public const INSECABLE = "\u{00A0}";

    private const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** 1 359 m → « 1,4 » ; 820 m → « 0,8 ». L'unité KM est posée à part (Borne). */
    public static function km(int|float $metres): string
    {
        $km = $metres / 1000;
        return $km < 10 ? number_format($km, 1, ',', '') : (string) round($km);
    }

    public static function distance(int|float $metres): string
    {
        return self::km($metres) . self::INSECABLE . 'km';
    }

    public static function nombre(int|float $n, int $decimales = 0): string
    {
        return number_format($n, $decimales, ',', self::FINE);
    }

    public static function fcfa(int $montant): string
    {
        return self::nombre($montant) . self::INSECABLE . 'FCFA';
    }

    public static function note(?float $note): string
    {
        return $note === null ? '—' : number_format($note, 1, ',', '');
    }

    /** « +2250707123456 » ou « 07 07 12 34 56 » → « +225 07 07 12 34 56 » */
    public static function telephone(?string $tel): string
    {
        if ($tel === null || $tel === '') {
            return '';
        }
        $c = preg_replace('/\D/', '', $tel) ?? '';
        if (str_starts_with($c, '225') && strlen($c) === 13) {
            $c = substr($c, 3);
        }
        return '+225' . self::INSECABLE . implode(self::INSECABLE, str_split($c, 2));
    }

    /** « +2250707123456 » → « 07 07 12 34 56 » : ce qu'on remet dans un champ téléphone (le préfixe +225 est déjà à l'écran). */
    public static function telephoneLocal(?string $tel): string
    {
        if ($tel === null || $tel === '') {
            return '';
        }
        $c = preg_replace('/\D/', '', $tel) ?? '';
        if (str_starts_with($c, '225') && strlen($c) === 13) {
            $c = substr($c, 3);
        }
        return implode(' ', str_split($c, 2));
    }

    public static function telephoneE164(string $tel): string
    {
        $c = preg_replace('/\D/', '', $tel) ?? '';
        if (str_starts_with($c, '225') && strlen($c) === 13) {
            $c = substr($c, 3);
        }
        return '+225' . $c;
    }

    /** « 12 mars 2026 » */
    public static function date(\DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $d = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
        $jour = (int) $d->format('j');
        return ($jour === 1 ? '1er' : (string) $jour) . ' ' . self::MOIS[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    /** « mercredi 21 octobre 2026 » (l'admin le passe en capitales avec .t-etiquette) */
    public static function dateLongue(\DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $d = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
        return self::JOURS[(int) $d->format('w')] . ' ' . self::date($d);
    }

    /** « 10:14 » (Mono dans les vues) */
    public static function heure(\DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $d = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
        return $d->format('H:i');
    }

    /** « il y a 3 jours », « il y a 2 h », « à l'instant » */
    public static function depuis(\DateTimeInterface|string $date, ?\DateTimeInterface $maintenant = null): string
    {
        $d = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
        $s = ($maintenant ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp() - $d->getTimestamp();
        return match (true) {
            $s < 60 => 'à l’instant',
            $s < 3600 => 'il y a ' . intdiv($s, 60) . self::INSECABLE . 'min',
            $s < 86400 => 'il y a ' . intdiv($s, 3600) . self::INSECABLE . 'h',
            $s < 86400 * 14 => 'il y a ' . intdiv($s, 86400) . ' jour' . (intdiv($s, 86400) > 1 ? 's' : ''),
            $s < 86400 * 60 => 'il y a ' . intdiv($s, 604800) . ' semaines',
            default => 'le ' . self::date($d),
        };
    }

    /** Ajoute l'espace fine insécable avant ? ! : ; dans un texte saisi par l'équipe. */
    public static function typo(string $texte): string
    {
        // ? ! ; après un mot ; « : » seulement s'il est suivi d'une espace (pas les heures 10:14 ni les URL)
        $texte = preg_replace('/(?<=[\p{L}\p{N}»)])\s*([?!;])/u', self::FINE . '$1', $texte) ?? $texte;
        return preg_replace('/(?<=[\p{L}\p{N}»)])\s*:(?=\s)/u', self::FINE . ':', $texte) ?? $texte;
    }
}
