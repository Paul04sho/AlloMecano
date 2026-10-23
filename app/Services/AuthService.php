<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Format;

/**
 * Connexion du mécano (téléphone +225 ou e-mail) et de l'admin (e-mail) : RG-C1, RG-C2, RG-C5.
 * 5 échecs en 15 minutes pour un même identifiant bloquent la connexion 15 minutes.
 */
final class AuthService
{
    public const ESSAIS_MAX = 5;
    public const FENETRE_MIN = 15;

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * « 07 07 12 34 56 », « +2250707123456 » ou « Koffi.Yao@Gmail.com » → identifiant normalisé, ou nul s'il n'y ressemble pas.
     *
     * @return ?array{type: string, valeur: string}
     */
    public static function normaliser(string $identifiant): ?array
    {
        $identifiant = trim($identifiant);
        if ($identifiant === '') {
            return null;
        }
        if (str_contains($identifiant, '@')) {
            return filter_var($identifiant, FILTER_VALIDATE_EMAIL) ? ['type' => 'email', 'valeur' => mb_strtolower($identifiant)] : null;
        }
        $chiffres = preg_replace('/\D/', '', $identifiant) ?? '';
        if (str_starts_with($chiffres, '225') && strlen($chiffres) === 13) {
            $chiffres = substr($chiffres, 3);
        }
        return strlen($chiffres) === 10 ? ['type' => 'telephone', 'valeur' => Format::telephoneE164($chiffres)] : null;
    }

    /** Fin du blocage si l'identifiant est bloqué (RG-C2), sinon nul. */
    public function bloqueJusqua(string $identifiant, ?\DateTimeImmutable $maintenant = null): ?\DateTimeImmutable
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $debut = $maintenant->modify('-' . self::FENETRE_MIN . ' minutes')->format('Y-m-d H:i:s');
        $l = $this->db->un(
            'SELECT COUNT(*) AS n, MAX(cree_le) AS dernier FROM tentatives_connexion WHERE identifiant = :i AND reussie = 0 AND cree_le > :d',
            ['i' => $identifiant, 'd' => $debut]
        );
        if ((int) ($l['n'] ?? 0) < self::ESSAIS_MAX) {
            return null;
        }
        $fin = (new \DateTimeImmutable((string) $l['dernier'], new \DateTimeZone('UTC')))->modify('+' . self::FENETRE_MIN . ' minutes');
        return $fin > $maintenant ? $fin : null;
    }

    /** Essais qui restent avant le blocage. */
    public function essaisRestants(string $identifiant, ?\DateTimeImmutable $maintenant = null): int
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $debut = $maintenant->modify('-' . self::FENETRE_MIN . ' minutes')->format('Y-m-d H:i:s');
        $n = (int) $this->db->valeur('SELECT COUNT(*) FROM tentatives_connexion WHERE identifiant = :i AND reussie = 0 AND cree_le > :d', ['i' => $identifiant, 'd' => $debut]);
        return max(0, self::ESSAIS_MAX - $n);
    }

    /**
     * @return array{ok: true, profil: string, id: int}|array{ok: false, erreur: string, restant?: int, jusqua?: \DateTimeImmutable}
     *         erreur : identifiant | mot_de_passe | bloque (jamais « identifiant inconnu » : on ne révèle pas qui a un compte)
     */
    public function connecter(string $identifiant, string $motDePasse, string $ip, ?\DateTimeImmutable $maintenant = null): array
    {
        $id = self::normaliser($identifiant);
        if ($id === null) {
            return ['ok' => false, 'erreur' => 'identifiant'];
        }
        $cle = $id['valeur'];
        if (($fin = $this->bloqueJusqua($cle, $maintenant)) !== null) {
            return ['ok' => false, 'erreur' => 'bloque', 'jusqua' => $fin];
        }
        $compte = $this->compte($id);
        // Même durée de calcul que le compte existe ou non : on vérifie toujours un hash.
        $hash = $compte['hash'] ?? '$2y$10$KVlxifPk5tt/5uHd2Q9XlONhWClsok4m7kZzGQS0Ulmfe40TS6TNu';
        $bon = password_verify($motDePasse, $hash) && $compte !== null && $compte['hash'] !== null;
        $this->noter($cle, $ip, $bon, $maintenant);
        if (!$bon) {
            $restant = $this->essaisRestants($cle, $maintenant);
            if ($restant === 0) {
                return ['ok' => false, 'erreur' => 'bloque', 'jusqua' => $this->bloqueJusqua($cle, $maintenant) ?? new \DateTimeImmutable('+' . self::FENETRE_MIN . ' minutes', new \DateTimeZone('UTC'))];
            }
            return ['ok' => false, 'erreur' => 'mot_de_passe', 'restant' => $restant];
        }
        $this->db->executer('DELETE FROM tentatives_connexion WHERE identifiant = :i', ['i' => $cle]);
        if ($compte['profil'] === 'admin') {
            $this->db->modifier('admins', ['derniere_connexion_le' => gmdate('Y-m-d H:i:s')], ['id' => $compte['id']]);
        }
        return ['ok' => true, 'profil' => $compte['profil'], 'id' => $compte['id']];
    }

    /**
     * Connexion Google : l'identifiant Google d'abord, sinon l'e-mail vérifié par Google (le compte garde alors son identifiant Google).
     *
     * @return ?array{profil: string, id: int}
     */
    public function connecterGoogle(string $sub, string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (['admins' => 'admin', 'mecaniciens' => 'mecano'] as $table => $profil) {
            $c = $this->db->un("SELECT id FROM $table WHERE google_sub = :s" . ($table === 'admins' ? ' AND actif = 1' : ''), ['s' => $sub]);
            if ($c === null && $email !== '') {
                $c = $this->db->un("SELECT id FROM $table WHERE email = :e" . ($table === 'admins' ? ' AND actif = 1' : ''), ['e' => $email]);
                if ($c !== null) {
                    $this->db->modifier($table, ['google_sub' => $sub], ['id' => $c['id']]);
                }
            }
            if ($c !== null) {
                return ['profil' => $profil, 'id' => (int) $c['id']];
            }
        }
        return null;
    }

    /** @param array{type: string, valeur: string} $id @return ?array{profil: string, id: int, hash: ?string} */
    private function compte(array $id): ?array
    {
        if ($id['type'] === 'email') {
            $a = $this->db->un('SELECT id, mot_de_passe_hash FROM admins WHERE email = :e AND actif = 1', ['e' => $id['valeur']]);
            if ($a !== null) {
                return ['profil' => 'admin', 'id' => (int) $a['id'], 'hash' => $a['mot_de_passe_hash']];
            }
            $m = $this->db->un('SELECT id, mot_de_passe_hash FROM mecaniciens WHERE email = :e', ['e' => $id['valeur']]);
        } else {
            $m = $this->db->un('SELECT id, mot_de_passe_hash FROM mecaniciens WHERE telephone = :t', ['t' => $id['valeur']]);
        }
        return $m !== null ? ['profil' => 'mecano', 'id' => (int) $m['id'], 'hash' => $m['mot_de_passe_hash']] : null;
    }

    private function noter(string $identifiant, string $ip, bool $reussie, ?\DateTimeImmutable $maintenant): void
    {
        $maintenant ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->db->inserer('tentatives_connexion', [
            'identifiant' => $identifiant, 'ip' => inet_pton($ip) ?: "\0\0\0\0", 'reussie' => $reussie ? 1 : 0, 'cree_le' => $maintenant->format('Y-m-d H:i:s'),
        ]);
    }
}
