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

    /** La clé publique que le navigateur utilise pour s'abonner (M1f) ; vide tant que les clés VAPID ne sont pas dans .env. */
    public static function clePublique(): ?string
    {
        $cle = Env::get('VAPID_PUBLIQUE');
        return $cle !== null && $cle !== '' ? $cle : null;
    }

    /**
     * M1f : ce téléphone devient joignable (RG-A2). Un même navigateur qui se réabonne reprend sa ligne, quel que soit le mécano qui s'y était connecté.
     *
     * @param array{endpoint?: mixed, keys?: array{p256dh?: mixed, auth?: mixed}} $souscription PushSubscription.toJSON()
     */
    public function abonner(int $mecanoId, array $souscription, ?string $appareil = null): bool
    {
        $endpoint = $souscription['endpoint'] ?? null;
        $p256dh = $souscription['keys']['p256dh'] ?? null;
        $auth = $souscription['keys']['auth'] ?? null;
        if (!is_string($endpoint) || !is_string($p256dh) || !is_string($auth) || !str_starts_with($endpoint, 'https://') || strlen($endpoint) > 600 || strlen($p256dh) > 120 || strlen($auth) > 40) {
            return false;
        }
        $this->db->executer(
            'INSERT INTO abonnements_push (mecanicien_id, endpoint, endpoint_hash, cle_p256dh, cle_auth, appareil) VALUES (:m, :e, :h, :p, :a, :d)
             ON DUPLICATE KEY UPDATE mecanicien_id = VALUES(mecanicien_id), cle_p256dh = VALUES(cle_p256dh), cle_auth = VALUES(cle_auth), appareil = VALUES(appareil), revoque_le = NULL, echecs_suite = 0',
            ['m' => $mecanoId, 'e' => $endpoint, 'h' => hash('sha256', $endpoint), 'p' => $p256dh, 'a' => $auth, 'd' => $appareil !== null ? mb_substr($appareil, 0, 120) : null]
        );
        return true;
    }

    /** Ce navigateur est-il déjà abonné pour ce mécano ? (la page M1f remet le réglage « fait ») */
    public function abonne(int $mecanoId, string $endpoint): bool
    {
        return (int) $this->db->valeur('SELECT COUNT(*) FROM abonnements_push WHERE mecanicien_id = :m AND endpoint_hash = :h AND revoque_le IS NULL', ['m' => $mecanoId, 'h' => hash('sha256', $endpoint)]) > 0;
    }

    /** M1f : « Faire sonner mon téléphone ». Une notification d'essai sur tous les appareils du mécano. @return int appareils joints */
    public function essai(int $mecanoId): int
    {
        $charge = ['contact' => null, 'titre' => 'Essai de sonnerie', 'texte' => 'Votre téléphone sonne : les appels des clients arriveront ainsi.', 'url' => '/pro/appels/activer?essai=1', 'essai' => true];
        $id = $this->db->inserer('notifications', ['mecanicien_id' => $mecanoId, 'type' => 'essai_sonnerie', 'canal' => 'push', 'statut' => 'en_attente', 'charge' => json_encode($charge, JSON_UNESCAPED_UNICODE)]);
        $abonnements = $this->abonnements($mecanoId);
        $envoyes = 0;
        try {
            $envoyes = $abonnements === [] ? 0 : ($this->transport !== null ? (int) ($this->transport)($abonnements, $charge) : $this->envoyer($abonnements, $charge));
        } catch (\Throwable $e) {
            $this->db->modifier('notifications', ['statut' => 'echec', 'tentatives' => 1, 'erreur' => mb_substr($e->getMessage(), 0, 250)], ['id' => $id]);
            return 0;
        }
        $this->db->modifier('notifications', ['statut' => $envoyes > 0 ? 'envoyee' : 'echec', 'envoyee_le' => gmdate('Y-m-d H:i:s'), 'tentatives' => 1, 'erreur' => $envoyes > 0 ? null : 'aucun appareil joint'], ['id' => $id]);
        return $envoyes;
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
