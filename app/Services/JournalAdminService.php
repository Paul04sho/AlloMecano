<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Le journal des actions des admins (RG-C5) : chaque décision, chaque consultation de pièce, chaque numéro de client affiché. */
final class JournalAdminService
{
    public const CIBLES = ['mecanicien', 'piece', 'avis', 'signalement', 'client', 'contact', 'admin', 'message'];

    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /** @param array<string, mixed> $details motif, ancien et nouveau statut… */
    public function noter(int $admin, string $action, string $cibleType, int $cibleId, array $details = [], string $ip = ''): void
    {
        if (!in_array($cibleType, self::CIBLES, true)) {
            throw new \InvalidArgumentException("Cible inconnue : $cibleType");
        }
        $this->db->inserer('journal_admin', [
            'admin_id' => $admin, 'action' => $action, 'cible_type' => $cibleType, 'cible_id' => $cibleId,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip' => $ip !== '' ? (inet_pton($ip) ?: null) : null, 'cree_le' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> les dernières lignes d'une cible */
    public function dernieres(string $cibleType, int $cibleId, int $limite = 20): array
    {
        return $this->db->tous(
            'SELECT j.action, j.details, j.cree_le, a.prenom, a.nom FROM journal_admin j JOIN admins a ON a.id = j.admin_id
              WHERE j.cible_type = :t AND j.cible_id = :i ORDER BY j.id DESC LIMIT ' . (int) $limite,
            ['t' => $cibleType, 'i' => $cibleId]
        );
    }
}
