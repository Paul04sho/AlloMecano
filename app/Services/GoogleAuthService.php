<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * « Continuer avec Google » (RG-C1, RG-C5) : OAuth 2 par code. Sans GOOGLE_CLIENT_ID et GOOGLE_CLIENT_SECRET, le bouton n'est pas affiché.
 * Le jeton d'identité arrive directement de Google sur une connexion TLS que nous ouvrons : pas besoin d'en vérifier la signature.
 */
final class GoogleAuthService
{
    public static function actif(): bool
    {
        return Env::get('GOOGLE_CLIENT_ID') !== null && Env::get('GOOGLE_CLIENT_SECRET') !== null;
    }

    public static function adresseRetour(): string
    {
        return rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/') . '/connexion/google/retour';
    }

    public static function adresseAutorisation(string $etat): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => Env::get('GOOGLE_CLIENT_ID'), 'redirect_uri' => self::adresseRetour(), 'response_type' => 'code',
            'scope' => 'openid email', 'state' => $etat, 'prompt' => 'select_account',
        ]);
    }

    /** @return ?array{sub: string, email: string} */
    public static function profilDepuisCode(string $code): ?array
    {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => http_build_query([
                'code' => $code, 'client_id' => Env::get('GOOGLE_CLIENT_ID'), 'client_secret' => Env::get('GOOGLE_CLIENT_SECRET'),
                'redirect_uri' => self::adresseRetour(), 'grant_type' => 'authorization_code',
            ]),
        ]);
        $corps = curl_exec($ch);
        curl_close($ch);
        $jeton = is_string($corps) ? (json_decode($corps, true)['id_token'] ?? null) : null;
        $morceaux = is_string($jeton) ? explode('.', $jeton) : [];
        if (count($morceaux) !== 3) {
            return null;
        }
        $charge = json_decode((string) base64_decode(strtr($morceaux[1], '-_', '+/')), true);
        if (!is_array($charge) || empty($charge['sub']) || (($charge['email_verified'] ?? false) !== true && ($charge['email_verified'] ?? '') !== 'true')) {
            return null;
        }
        return ['sub' => (string) $charge['sub'], 'email' => (string) ($charge['email'] ?? '')];
    }
}
