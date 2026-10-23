<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Notifications push du mécano (Web Push, clés VAPID dans .env). Chaque envoi est tracé dans `notifications`.
 * Sans clés VAPID ou sans abonnement, rien ne part : l'appel reste visible dans l'app ouverte du mécano (RG-A2).
 */
final class PushService
{
    /** @param ?callable(list<array<string, mixed>>, array<string, mixed>): int $transport remplace l'envoi réseau (tests) : abonnements, charge → nombre envoyé */
    public function __construct(private readonly Database $db, private $transport = null)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** Abonnements actifs du mécano (RG-A2). */
    public function abonnements(int $mecanoId): array
    {
        return $this->db->tous(
            'SELECT id, endpoint, cle_p256dh, cle_auth FROM abonnements_push WHERE mecanicien_id = :m AND revoque_le IS NULL',
            ['m' => $mecanoId]
        );
    }

    /**
     * « Appel entrant » : une ligne dans `notifications`, puis l'envoi push. Renvoyé tel quel à chaque relance (RG-A5).
     *
     * @param array{contact: string, titre: string, texte: string, url: string} $charge
     */
    public function appelEntrant(int $mecanoId, array $charge): void
    {
        $id = $this->db->inserer('notifications', [
            'mecanicien_id' => $mecanoId, 'type' => 'appel_entrant', 'canal' => 'push', 'statut' => 'en_attente',
            'charge' => json_encode($charge, JSON_UNESCAPED_UNICODE),
        ]);
        $abonnements = $this->abonnements($mecanoId);
        if ($abonnements === []) {
            $this->db->modifier('notifications', ['statut' => 'echec', 'erreur' => 'aucun abonnement'], ['id' => $id]);
            return;
        }
        try {
            $envoyes = $this->transport !== null ? (int) ($this->transport)($abonnements, $charge) : $this->envoyer($abonnements, $charge);
            $this->db->modifier('notifications', ['statut' => $envoyes > 0 ? 'envoyee' : 'echec', 'envoyee_le' => gmdate('Y-m-d H:i:s'), 'tentatives' => 1, 'erreur' => $envoyes > 0 ? null : 'refusé par le service push'], ['id' => $id]);
        } catch (\Throwable $e) {
            // Un push qui échoue ne doit jamais casser l'appel
            $this->db->modifier('notifications', ['statut' => 'echec', 'tentatives' => 1, 'erreur' => mb_substr($e->getMessage(), 0, 250)], ['id' => $id]);
        }
    }

    /** Dernier push d'appel de ce contact : sert à renvoyer toutes les 5 s (RG-A5). */
    public function dernierPush(int $mecanoId, string $contactPublicId): ?\DateTimeImmutable
    {
        $d = $this->db->valeur(
            'SELECT MAX(cree_le) FROM notifications
              WHERE mecanicien_id = :m AND type = \'appel_entrant\' AND JSON_UNQUOTE(JSON_EXTRACT(charge, \'$.contact\')) = :c',
            ['m' => $mecanoId, 'c' => $contactPublicId]
        );
        return $d ? new \DateTimeImmutable((string) $d, new \DateTimeZone('UTC')) : null;
    }

    /**
     * @param list<array<string, mixed>> $abonnements
     * @param array<string, mixed> $charge
     */
    private function envoyer(array $abonnements, array $charge): int
    {
        $publique = Env::get('VAPID_PUBLIQUE');
        $privee = Env::get('VAPID_PRIVEE');
        if ($publique === null || $privee === null) {
            return 0;
        }
        $webpush = new WebPush(['VAPID' => ['subject' => Env::get('VAPID_SUJET', 'mailto:bonjour@allomecano.ci'), 'publicKey' => $publique, 'privateKey' => $privee]], ['TTL' => 30, 'urgency' => 'high']);
        foreach ($abonnements as $a) {
            $webpush->queueNotification(Subscription::create(['endpoint' => $a['endpoint'], 'publicKey' => $a['cle_p256dh'], 'authToken' => $a['cle_auth']]), json_encode($charge, JSON_UNESCAPED_UNICODE));
        }
        $ok = 0;
        $par = array_column($abonnements, null, 'endpoint');
        foreach ($webpush->flush() as $rapport) {
            $a = $par[$rapport->getEndpoint()] ?? null;
            if ($a === null) {
                continue;
            }
            if ($rapport->isSuccess()) {
                $ok++;
                $this->db->executer('UPDATE abonnements_push SET dernier_succes_le = UTC_TIMESTAMP(), echecs_suite = 0 WHERE id = :i', ['i' => $a['id']]);
            } elseif ($rapport->isSubscriptionExpired()) {
                $this->db->executer('UPDATE abonnements_push SET revoque_le = UTC_TIMESTAMP() WHERE id = :i', ['i' => $a['id']]); // 404 / 410
            } else {
                $this->db->executer('UPDATE abonnements_push SET echecs_suite = echecs_suite + 1, revoque_le = IF(echecs_suite + 1 >= 3, UTC_TIMESTAMP(), NULL) WHERE id = :i', ['i' => $a['id']]);
            }
        }
        return $ok;
    }
}
