<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Connexion OpenID Connect côté serveur pour l'inscription mécanicien.
 * Les identifiants restent dans .env et le jeton d'identité est vérifié avec les certificats publics Google.
 */
final class GoogleOAuthService
{
    private const AUTORISATION = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const JETONS = 'https://oauth2.googleapis.com/token';
    private const CERTIFICATS = 'https://www.googleapis.com/oauth2/v3/certs';

    /** @var ?array<string, array<string, mixed>> */
    private static ?array $cles = null;

    public function configure(): bool
    {
        return (string) Env::get('GOOGLE_CLIENT_ID', '') !== ''
            && (string) Env::get('GOOGLE_CLIENT_SECRET', '') !== '';
    }

    public function identifiantClient(): string
    {
        return (string) Env::get('GOOGLE_CLIENT_ID', '');
    }

    public function uriRetour(): string
    {
        $base = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
        return $base . '/pro/inscription/google/retour';
    }

    public function urlAutorisation(string $etat, string $nonce): string
    {
        if (!$this->configure()) {
            throw new \RuntimeException('La connexion Google n’est pas configurée.');
        }

        return self::AUTORISATION . '?' . http_build_query([
            'client_id' => $this->identifiantClient(),
            'redirect_uri' => $this->uriRetour(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $etat,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Échange le code et retourne uniquement une identité Google validée.
     *
     * @return array{sub: string, email: string, prenom: string, nom: string}
     */
    public function profilDepuisCode(string $code, string $nonceAttendu): array
    {
        if (!$this->configure()) {
            throw new \RuntimeException('La connexion Google n’est pas configurée.');
        }

        $jetons = $this->requeteJson(self::JETONS, 'POST', [
            'code' => $code,
            'client_id' => $this->identifiantClient(),
            'client_secret' => (string) Env::get('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => $this->uriRetour(),
            'grant_type' => 'authorization_code',
        ]);
        $jwt = is_string($jetons['id_token'] ?? null) ? $jetons['id_token'] : '';
        if ($jwt === '') {
            throw new \RuntimeException('Google n’a pas retourné de jeton d’identité.');
        }

        $parties = explode('.', $jwt);
        if (count($parties) !== 3) {
            throw new \RuntimeException('Jeton Google mal formé.');
        }
        [$entete64, $charge64, $signature64] = $parties;
        $entete = $this->decoderObjet($entete64);
        $charge = $this->decoderObjet($charge64);
        if (($entete['alg'] ?? null) !== 'RS256' || !is_string($entete['kid'] ?? null)) {
            throw new \RuntimeException('Algorithme de jeton Google non accepté.');
        }

        $cle = $this->clePublique((string) $entete['kid']);
        $signature = $this->decoderBase64Url($signature64);
        $valide = openssl_verify($entete64 . '.' . $charge64, $signature, $cle, OPENSSL_ALGO_SHA256);
        if ($valide !== 1) {
            throw new \RuntimeException('Signature du jeton Google invalide.');
        }

        $audience = $charge['aud'] ?? null;
        $audienceValide = is_string($audience)
            ? hash_equals($this->identifiantClient(), $audience)
            : (is_array($audience) && in_array($this->identifiantClient(), $audience, true));
        $autorise = is_array($audience) && count($audience) > 1
            ? is_string($charge['azp'] ?? null) && hash_equals($this->identifiantClient(), $charge['azp'])
            : true;
        $emetteurValide = in_array($charge['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true);
        $expire = filter_var($charge['exp'] ?? null, FILTER_VALIDATE_INT);
        $emis = filter_var($charge['iat'] ?? null, FILTER_VALIDATE_INT);
        $nonce = is_string($charge['nonce'] ?? null) ? $charge['nonce'] : '';
        $courrielVerifie = ($charge['email_verified'] ?? false) === true || ($charge['email_verified'] ?? null) === 'true';
        $maintenant = time();

        if (
            !$audienceValide
            || !$autorise
            || !$emetteurValide
            || $expire === false
            || $expire <= $maintenant
            || $emis === false
            || $emis > $maintenant + 60
            || $emis < $maintenant - 86400
            || $nonceAttendu === ''
            || !hash_equals($nonceAttendu, $nonce)
            || !$courrielVerifie
        ) {
            throw new \RuntimeException('Les informations d’identité Google n’ont pas pu être validées.');
        }

        $sub = is_string($charge['sub'] ?? null) ? trim($charge['sub']) : '';
        $email = strtolower(trim((string) ($charge['email'] ?? '')));
        if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Le profil Google ne contient pas d’adresse e-mail vérifiée.');
        }

        $nomComplet = trim((string) ($charge['name'] ?? ''));
        $prenom = trim((string) ($charge['given_name'] ?? ''));
        $nom = trim((string) ($charge['family_name'] ?? ''));
        if ($prenom === '' && $nomComplet !== '') {
            $morceaux = preg_split('/\s+/u', $nomComplet) ?: [];
            $prenom = (string) array_shift($morceaux);
            if ($nom === '') {
                $nom = implode(' ', $morceaux);
            }
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'prenom' => mb_substr($prenom, 0, 60),
            'nom' => mb_substr($nom, 0, 60),
        ];
    }

    /** @return array<string, mixed> */
    private function decoderObjet(string $partie): array
    {
        $json = json_decode($this->decoderBase64Url($partie), true);
        if (!is_array($json)) {
            throw new \RuntimeException('Réponse d’identité Google invalide.');
        }
        return $json;
    }

    private function decoderBase64Url(string $valeur): string
    {
        $base64 = strtr($valeur, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decode = base64_decode($base64, true);
        if ($decode === false) {
            throw new \RuntimeException('Encodage du jeton Google invalide.');
        }
        return $decode;
    }

    private function clePublique(string $kid): \OpenSSLAsymmetricKey
    {
        if (self::$cles === null) {
            $reponse = $this->requeteJson(self::CERTIFICATS);
            self::$cles = [];
            foreach (($reponse['keys'] ?? []) as $jwk) {
                if (!is_array($jwk) || !is_string($jwk['kid'] ?? null)) {
                    continue;
                }
                self::$cles[$jwk['kid']] = $jwk;
            }
        }
        $jwk = self::$cles[$kid] ?? null;
        if (
            !is_array($jwk)
            || ($jwk['kty'] ?? null) !== 'RSA'
            || !in_array($jwk['alg'] ?? 'RS256', ['RS256'], true)
            || (($jwk['use'] ?? 'sig') !== 'sig')
        ) {
            throw new \RuntimeException('Clé publique Google introuvable pour ce jeton.');
        }

        // Le JWKS Google expose n/e (RSA) plutôt que systématiquement x5c.
        // On accepte x5c si présent, sinon on construit un SubjectPublicKeyInfo PEM à partir de n/e.
        if (is_array($jwk['x5c'] ?? null) && is_string($jwk['x5c'][0] ?? null)) {
            $pem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($jwk['x5c'][0], 64, "\n")
                . "-----END CERTIFICATE-----\n";
        } else {
            $modulus = $this->decoderBase64Url((string) ($jwk['n'] ?? ''));
            $exposant = $this->decoderBase64Url((string) ($jwk['e'] ?? ''));
            if ($modulus === '' || $exposant === '') {
                throw new \RuntimeException('Composants de la clé Google incomplets.');
            }

            if ((ord($modulus[0]) & 0x80) !== 0) {
                $modulus = "\x00" . $modulus;
            }
            if ((ord($exposant[0]) & 0x80) !== 0) {
                $exposant = "\x00" . $exposant;
            }

            $rsa = $this->derSequence(
                $this->derElement(0x02, $modulus) . $this->derElement(0x02, $exposant)
            );
            $algorithme = hex2bin('300d06092a864886f70d0101010500');
            if ($algorithme === false) {
                throw new \RuntimeException('Impossible de construire la clé RSA Google.');
            }
            $spki = $this->derSequence($algorithme . $this->derElement(0x03, "\x00" . $rsa));
            $pem = "-----BEGIN PUBLIC KEY-----\n"
                . chunk_split(base64_encode($spki), 64, "\n")
                . "-----END PUBLIC KEY-----\n";
        }

        $cle = openssl_pkey_get_public($pem);
        if ($cle === false) {
            throw new \RuntimeException('Clé publique Google illisible.');
        }
        return $cle;
    }

    private function derSequence(string $contenu): string
    {
        return $this->derElement(0x30, $contenu);
    }

    private function derElement(int $tag, string $contenu): string
    {
        $longueur = strlen($contenu);
        if ($longueur < 0x80) {
            $prefixe = chr($longueur);
        } else {
            $octets = '';
            for ($n = $longueur; $n > 0; $n >>= 8) {
                $octets = chr($n & 0xff) . $octets;
            }
            $prefixe = chr(0x80 | strlen($octets)) . $octets;
        }
        return chr($tag) . $prefixe . $contenu;
    }

    /**
     * @param ?array<string, string> $donnees
     * @return array<string, mixed>
     */
    private function requeteJson(string $url, string $methode = 'GET', ?array $donnees = null): array
    {
        $entetes = "Accept: application/json\r\n";
        $optionsHttp = [
            'method' => $methode,
            'header' => $entetes,
            'timeout' => 12,
            'ignore_errors' => true,
        ];
        if ($donnees !== null) {
            $optionsHttp['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $optionsHttp['content'] = http_build_query($donnees, '', '&', PHP_QUERY_RFC3986);
        }
        $contexte = stream_context_create([
            'http' => $optionsHttp,
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $corps = @file_get_contents($url, false, $contexte);
        $reponseEntetes = $http_response_header ?? [];
        $statut = 0;
        if (isset($reponseEntetes[0]) && preg_match('/\s(\d{3})\s/', $reponseEntetes[0], $m)) {
            $statut = (int) $m[1];
        }
        if ($corps === false || $statut < 200 || $statut >= 300) {
            throw new \RuntimeException('Le service Google est momentanément inaccessible.');
        }
        $json = json_decode($corps, true);
        if (!is_array($json)) {
            throw new \RuntimeException('Réponse du service Google invalide.');
        }
        return $json;
    }
}
