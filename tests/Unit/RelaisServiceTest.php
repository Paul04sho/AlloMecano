<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use App\Services\RelaisService;
use PHPUnit\Framework\TestCase;

/** Serveurs ICE : STUN seul sans clés, identifiants Cloudflare TURN avec clés (gardés en session le temps de leur validité). */
final class RelaisServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        putenv('TURN_CLE_ID');
        putenv('TURN_CLE_SECRET');
    }

    protected function tearDown(): void
    {
        putenv('TURN_CLE_ID');
        putenv('TURN_CLE_SECRET');
        $_SESSION = [];
    }

    public function testSansClesOnGardeLeStunPublic(): void
    {
        $s = (new RelaisService(static fn (): string => throw new \LogicException('pas d’appel réseau sans clés')))->serveurs();
        self::assertSame([['urls' => ['stun:stun.cloudflare.com:3478']]], $s);
    }

    public function testAvecClesLesIdentifiantsEphemeresViennentDeCloudflare(): void
    {
        putenv('TURN_CLE_ID=cle123');
        putenv('TURN_CLE_SECRET=secret456');
        $appels = [];
        $http = function (string $url, string $jeton, string $corps) use (&$appels): string {
            $appels[] = [$url, $jeton, $corps];
            return json_encode(['iceServers' => ['urls' => ['stun:stun.cloudflare.com:3478', 'turn:turn.cloudflare.com:3478?transport=udp'], 'username' => 'u', 'credential' => 'c']]);
        };
        $service = new RelaisService($http);
        $s = $service->serveurs();
        self::assertSame('https://rtc.live.cloudflare.com/v1/turn/keys/cle123/credentials/generate-ice-servers', $appels[0][0]);
        self::assertSame('secret456', $appels[0][1]);
        self::assertSame(['ttl' => 3600], json_decode($appels[0][2], true));
        self::assertSame('u', $s[0]['username']); // l'objet seul est mis en liste
        $service->serveurs();
        self::assertCount(1, $appels); // gardés en session tant qu'ils valent
        self::assertNotNull(Session::get('turn'));
    }

    public function testRelaisInjoignableAlorsStunSeul(): void
    {
        putenv('TURN_CLE_ID=cle123');
        putenv('TURN_CLE_SECRET=secret456');
        self::assertSame([['urls' => ['stun:stun.cloudflare.com:3478']]], (new RelaisService(static fn (): ?string => null))->serveurs());
        self::assertSame([['urls' => ['stun:stun.cloudflare.com:3478']]], (new RelaisService(static fn (): string => '{"erreur":1}'))->serveurs());
    }
}
