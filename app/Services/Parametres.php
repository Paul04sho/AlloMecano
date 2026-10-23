<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Seuils des règles métier (table parametres). Lus une fois par requête.
 *
 *   Parametres::int('appel.sonnerie_s')   // 30
 *   Parametres::liste('recherche.rayons_km') // [2, 5, 10]
 */
final class Parametres
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** Valeurs de secours si la base n'est pas encore installée (mêmes que les données de départ). */
    private const DEFAUTS = [
        'recherche.rayons_km' => '2,5,10', 'recherche.min_resultats' => '3', 'recherche.max_resultats' => '10',
        'recherche.bonus_specialiste' => '0.75', 'recherche.malus_autre_rive' => '1.30', 'recherche.rayon_etendu_km' => '20',
        'appel.sonnerie_s' => '30', 'appel.relance_push_s' => '5', 'appel.echec_reseau_s' => '15', 'appel.integre_actif' => '1',
        'retour.absence_min_s' => '3', 'retour.bandeau_h' => '24', 'antiabus.contacts_client_10min' => '5', 'antiabus.contacts_meca_h' => '3',
        'urgence.samu' => '185', 'urgence.pompiers' => '180', 'urgence.police' => '170',
    ];

    private static function tout(): array
    {
        if (self::$cache === null) {
            try {
                $lignes = Database::instance()->tous('SELECT cle, valeur FROM parametres');
                self::$cache = array_column($lignes, 'valeur', 'cle') + self::DEFAUTS;
            } catch (\Throwable) {
                self::$cache = self::DEFAUTS;
            }
        }
        return self::$cache;
    }

    public static function texte(string $cle, string $defaut = ''): string
    {
        return self::tout()[$cle] ?? $defaut;
    }

    public static function int(string $cle, int $defaut = 0): int
    {
        return (int) (self::tout()[$cle] ?? $defaut);
    }

    public static function decimal(string $cle, float $defaut = 0.0): float
    {
        return (float) (self::tout()[$cle] ?? $defaut);
    }

    /** @return list<int> */
    public static function liste(string $cle): array
    {
        return array_map('intval', array_filter(explode(',', self::tout()[$cle] ?? '')));
    }

    public static function vider(): void
    {
        self::$cache = null;
    }
}
