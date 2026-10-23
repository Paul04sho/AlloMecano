<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

/**
 * Client anonyme (RG-C7) : jeton aléatoire de 32 octets gardé dans le navigateur (cookie HttpOnly + copie
 * en localStorage), stocké en base sous forme d'empreinte SHA-256. Changer de téléphone fait perdre l'historique.
 */
final class ClientService
{
    public const COOKIE = 'am_client';
    private const DUREE_S = 400 * 86400; // plafond des navigateurs pour un cookie

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    public static function genererJeton(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** 32 octets en base64url sans remplissage : 43 caractères. */
    public static function jetonValide(?string $jeton): bool
    {
        return is_string($jeton) && (bool) preg_match('/^[A-Za-z0-9_-]{43}$/', $jeton);
    }

    public static function empreinte(string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    /**
     * Retrouve le client du jeton, ou en crée un. « nouveau » vaut le jeton à confier au navigateur.
     *
     * @return array{id: int, nouveau: ?string}
     */
    public function identifier(?string $jeton): array
    {
        if (self::jetonValide($jeton)) {
            $id = $this->db->valeur('SELECT id FROM clients WHERE jeton_hash = :h', ['h' => self::empreinte($jeton)]);
            if ($id !== null) {
                $this->db->executer('UPDATE clients SET vu_le = UTC_TIMESTAMP() WHERE id = :id', ['id' => (int) $id]);
                return ['id' => (int) $id, 'nouveau' => null];
            }
        }
        $neuf = self::genererJeton();
        $id = $this->db->inserer('clients', ['jeton_hash' => self::empreinte($neuf)]);
        return ['id' => $id, 'nouveau' => $neuf];
    }

    /**
     * Le cookie a disparu mais le navigateur a gardé sa copie (localStorage) : on reprend l'ancien jeton.
     * Le client créé entre-temps est supprimé s'il n'a encore rien fait (aucune recherche).
     *
     * @return ?int id du client repris, null si le jeton est invalide
     */
    public function restaurer(string $jeton, ?int $clientProvisoire): ?int
    {
        if (!self::jetonValide($jeton)) {
            return null;
        }
        // Jeton inconnu (base remise à zéro) : on le réenregistre tel quel pour que navigateur et base restent d'accord
        $id = $this->db->valeur('SELECT id FROM clients WHERE jeton_hash = :h', ['h' => self::empreinte($jeton)]);
        $repris = ['id' => $id !== null ? (int) $id : $this->db->inserer('clients', ['jeton_hash' => self::empreinte($jeton)])];
        if ($clientProvisoire !== null && $clientProvisoire !== $repris['id']) {
            $this->db->executer(
                'DELETE FROM clients WHERE id = :id AND NOT EXISTS (SELECT 1 FROM recherches r WHERE r.client_id = :id2)',
                ['id' => $clientProvisoire, 'id2' => $clientProvisoire]
            );
        }
        return $repris['id'];
    }

    public static function poserCookie(string $jeton): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        setcookie(self::COOKIE, $jeton, [
            'expires' => time() + self::DUREE_S,
            'path' => '/',
            'secure' => Env::enProduction(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
