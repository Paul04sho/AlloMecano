<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validation des formulaires. Les messages disent quoi corriger (spec 7.9).
 *
 *   $v = Validator::verifier($donnees, ['telephone' => 'requis|telephone_ci', 'nom' => 'requis|max:60']);
 *   if ($v->echoue()) { ... $v->erreurs() ... }
 */
final class Validator
{
    /** @var array<string, string> */
    private array $erreurs = [];
    /** @var array<string, mixed> */
    private array $propres = [];

    /** @param array<string, mixed> $donnees @param array<string, string> $regles */
    public static function verifier(array $donnees, array $regles): self
    {
        $v = new self();
        foreach ($regles as $champ => $chaine) {
            $valeur = $donnees[$champ] ?? null;
            $valeur = is_string($valeur) ? trim($valeur) : $valeur;
            foreach (explode('|', $chaine) as $regle) {
                [$nom, $arg] = array_pad(explode(':', $regle, 2), 2, null);
                $message = $v->appliquer($nom, $valeur, $arg);
                if ($message !== null) {
                    $v->erreurs[$champ] = $message;
                    break;
                }
                if ($nom === 'telephone_ci' && is_string($valeur) && $valeur !== '') {
                    $valeur = Format::telephoneE164($valeur);
                }
            }
            $v->propres[$champ] = $valeur;
        }
        return $v;
    }

    private function appliquer(string $regle, mixed $valeur, ?string $arg): ?string
    {
        $vide = $valeur === null || $valeur === '' || $valeur === [];
        if ($regle === 'requis') {
            return $vide ? 'Ce champ est obligatoire.' : null;
        }
        if ($vide) {
            return null; // les autres règles ne s'appliquent qu'à une valeur présente
        }
        return match ($regle) {
            'max' => mb_strlen((string) $valeur) > (int) $arg ? "$arg caractères au maximum." : null,
            'min' => mb_strlen((string) $valeur) < (int) $arg ? "$arg caractères au minimum." : null,
            'email' => filter_var($valeur, FILTER_VALIDATE_EMAIL) ? null : 'Cette adresse e-mail ne semble pas complète.',
            'entier' => filter_var($valeur, FILTER_VALIDATE_INT) !== false ? null : 'Un nombre entier est attendu.',
            'dans' => in_array((string) $valeur, explode(',', (string) $arg), true) ? null : 'Ce choix n’est pas proposé.',
            'telephone_ci' => self::telephoneCi((string) $valeur),
            'mot_de_passe' => (mb_strlen((string) $valeur) >= 8 && preg_match('/\d/', (string) $valeur)) ? null : '8 caractères au minimum, dont 1 chiffre.',
            default => throw new \InvalidArgumentException("Règle inconnue : $regle"),
        };
    }

    private static function telephoneCi(string $valeur): ?string
    {
        $chiffres = preg_replace('/\D/', '', $valeur) ?? '';
        if (str_starts_with($chiffres, '225') && strlen($chiffres) === 13) {
            $chiffres = substr($chiffres, 3);
        }
        $n = strlen($chiffres);
        if ($n === 10) {
            return null;
        }
        return $n < 10 ? 'Il manque ' . (10 - $n) . ' chiffre' . (10 - $n > 1 ? 's' : '') . ' : 10 chiffres après +225.' : 'Trop de chiffres : 10 chiffres après +225.';
    }

    public function echoue(): bool
    {
        return $this->erreurs !== [];
    }

    /** @return array<string, string> */
    public function erreurs(): array
    {
        return $this->erreurs;
    }

    /** @return array<string, mixed> */
    public function donnees(): array
    {
        return $this->propres;
    }
}
