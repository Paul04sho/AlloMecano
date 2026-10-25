<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Le mécano connecté vu par son espace pro (M2 à M4) : son compte, son statut de dossier, son état de disponibilité.
 * Les règles de disponibilité (RG-D1 à RG-D7) et de dossier (RG-DO1 à RG-DO9) y sont ajoutées écran par écran.
 */
final class MecanoService
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function depuisBase(): self
    {
        return new self(Database::instance());
    }

    /**
     * Le compte d'un mécano connecté ; nul s'il n'existe plus ou s'il a été supprimé (RG-C6).
     *
     * @return ?array{id: int, public_id: string, prenom: string, nom: string, statut_dossier: string, etat: string}
     */
    public function compte(int $id): ?array
    {
        $m = $this->db->un('SELECT id, public_id, prenom, nom, statut_dossier, etat FROM mecaniciens WHERE id = :i AND supprime_le IS NULL', ['i' => $id]);
        return $m === null ? null : ['id' => (int) $m['id'], 'public_id' => (string) $m['public_id'], 'prenom' => (string) $m['prenom'], 'nom' => (string) $m['nom'],
            'statut_dossier' => (string) $m['statut_dossier'], 'etat' => (string) $m['etat']];
    }
}
