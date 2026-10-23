<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;

/**
 * C3 · Ma panne : véhicule (filtre strict, RG-R2), panne (fait remonter les spécialistes, RG-R6),
 * précisions facultatives, et de quoi vous reconnaître (prénom, téléphone : facultatifs, jamais publics).
 */
final class PanneService
{
    public const PRECISIONS_MAX = 300;
    public const PRENOM_MAX = 40;

    /** Exemple du champ « Précisez si vous voulez », selon la panne choisie (69:653, 69:868). */
    public const EXEMPLES = [
        'ne_demarre_pas' => 'ex. le démarreur tourne mais le moteur ne prend pas',
        'batterie' => 'ex. ça fait clic quand je tourne la clé, les phares sont faibles',
        'surchauffe' => 'ex. l’aiguille est dans le rouge, de la vapeur sort du capot',
        'pneu_creve' => 'ex. pneu avant droit à plat, je n’ai pas de roue de secours',
        'huile_fuite' => 'ex. une flaque sous le moteur, le voyant d’huile clignote',
        'freins' => 'ex. la pédale s’enfonce, ça grince quand je freine',
        'choc_accident' => 'ex. choc à l’arrière, le moteur tourne encore',
        'bruit_fumee' => 'ex. fumée blanche à l’échappement, bruit de claquement',
        'je_ne_sais_pas' => 'ex. la voiture s’est arrêtée d’un coup sur le boulevard',
    ];

    public static function exemple(?string $panne): string
    {
        return self::EXEMPLES[$panne ?? ''] ?? self::EXEMPLES['batterie'];
    }

    /**
     * @param array<string, mixed> $donnees   vehicule, panne, precisions, prenom, telephone
     * @param list<string> $vehicules          codes connus (types_vehicule)
     * @param list<string> $pannes             codes proposés (pannes actives)
     * @return array{erreurs: array<string, string>, propre: array{vehicule: ?string, panne: ?string, precisions: ?string, prenom: ?string, telephone: ?string}}
     */
    public static function valider(array $donnees, array $vehicules, array $pannes): array
    {
        $v = Validator::verifier($donnees, [
            'vehicule' => 'requis|dans:' . implode(',', $vehicules),
            'panne' => 'requis|dans:' . implode(',', $pannes),
            'precisions' => 'max:' . self::PRECISIONS_MAX,
            'prenom' => 'max:' . self::PRENOM_MAX,
            'telephone' => 'telephone_ci',
        ]);
        $erreurs = $v->erreurs();
        // Messages qui disent quoi faire (Champ 53:230)
        if (isset($erreurs['vehicule'])) { $erreurs['vehicule'] = 'Choisissez votre véhicule.'; }
        if (isset($erreurs['panne'])) { $erreurs['panne'] = 'Choisissez ce qui ne va pas.'; }
        $d = $v->donnees();
        $texte = static fn ($x): ?string => is_string($x) && trim($x) !== '' ? trim((string) preg_replace('/\s+/u', ' ', $x)) : null;
        return ['erreurs' => $erreurs, 'propre' => [
            'vehicule' => isset($erreurs['vehicule']) ? null : $texte($d['vehicule'] ?? null),
            'panne' => isset($erreurs['panne']) ? null : $texte($d['panne'] ?? null),
            'precisions' => isset($erreurs['precisions']) ? null : $texte($d['precisions'] ?? null),
            'prenom' => isset($erreurs['prenom']) ? null : $texte($d['prenom'] ?? null),
            'telephone' => isset($erreurs['telephone']) ? null : $texte($d['telephone'] ?? null),
        ]];
    }
}
