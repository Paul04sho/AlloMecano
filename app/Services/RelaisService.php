<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Session;

/**
 * Serveurs ICE de l'appel intégré : relais Cloudflare Realtime TURN (identifiants éphémères, 1 h) si les clés sont dans .env,
 * sinon STUN public seul (l'audio passe en direct tant que les deux réseaux le permettent).
 */
final class RelaisService
{
    private const STUN = 'stun:stun.cloudflare.com:3478';
    private const TTL_S = 3600;

    /** @param ?callable(string, string, string): ?string $http remplace l'appel réseau (tests) : URL, jeton, corps → réponse JSON */
    public function __construct(private $http = null)
    {
    }

    /** @return list<array<string, mixed>> au format RTCConfiguration.iceServers */
    public function serveurs(): array
    {
        $stun = ['urls' => [self::STUN]];
        $id = Env::get('TURN_CLE_ID');
        $secret = Env::get('TURN_CLE_SECRET');
        if ($id === null || $secret === null) {
            return [$stun];
        }
        $cache = Session::get('turn');
        if (is_array($cache) && ($cache['expire'] ?? 0) > time() + 300) {
            return $cache['serveurs'];
        }
        $reponse = $this->appeler("https://rtc.live.cloudflare.com/v1/turn/keys/$id/credentials/generate-ice-servers", $secret, json_encode(['ttl' => self::TTL_S]) ?: '{}');
        $json = $reponse !== null ? json_decode($reponse, true) : null;
        $serveurs = is_array($json) ? ($json['iceServers'] ?? null) : null;
        if (!is_array($serveurs) || $serveurs === []) {
            return [$stun]; // relais injoignable : on tente quand même en direct
        }
        if (isset($serveurs['urls'])) {
            $serveurs = [$serveurs]; // l'API renvoie parfois un objet seul
        }
        Session::set('turn', ['expire' => time() + self::TTL_S, 'serveurs' => array_values($serveurs)]);
        return array_values($serveurs);
    }

    private function appeler(string $url, string $jeton, string $corps): ?string
    {
        if ($this->http !== null) {
            return ($this->http)($url, $jeton, $corps);
        }
        $contexte = stream_context_create(['http' => [
            'method' => 'POST', 'header' => "Authorization: Bearer $jeton\r\nContent-Type: application/json\r\n",
            'content' => $corps, 'timeout' => 4, 'ignore_errors' => true,
        ]]);
        $r = @file_get_contents($url, false, $contexte);
        return $r === false ? null : $r;
    }
}
