<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Photos publiques des mécanos (RG-DO9) : WebP allégées dans public/medias/<dossier>-<largeur>.webp,
 * couleur dominante affichée pendant le chargement, point de cadrage pour object-position.
 */
final class PhotoService
{
    /**
     * @param array<string, mixed> $ligne dossier, largeurs, ratio, couleur_dominante, focal_x, focal_y
     * @return ?array{src: string, srcset: string, ratio: float, couleur: string, cadrage: string}
     */
    public static function portrait(array $ligne): ?array
    {
        if (empty($ligne['dossier'])) {
            return null;
        }
        $largeurs = array_map('intval', explode(',', (string) $ligne['largeurs']));
        $url = static fn (int $l): string => '/medias/' . $ligne['dossier'] . '-' . $l . '.webp';
        return [
            'src' => $url($largeurs[0]),
            'srcset' => implode(', ', array_map(static fn (int $l): string => $url($l) . ' ' . $l . 'w', $largeurs)),
            'ratio' => (float) $ligne['ratio'],
            'couleur' => (string) $ligne['couleur_dominante'],
            'cadrage' => round((float) $ligne['focal_x'] * 100) . '% ' . round((float) $ligne['focal_y'] * 100) . '%',
        ];
    }
}
