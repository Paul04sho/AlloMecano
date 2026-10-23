<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Les textes de X5 · CGU (Figma 143:372) et X5 · Confidentialité (144:424), à l'identique de la maquette. Une seule source pour la page et pour le PDF.
 * Les deux restent à relire par un juriste avant la mise en ligne (la maquette le dit : durée des pièces, déclaration ARTCI).
 */
final class TextesLegaux
{
    public const MAJ = '1ER OCTOBRE 2026';

    /** @return array{rubrique: string, titre: string, essentiel: list<string>, articles: list<array{titre: string, paragraphes: list<string>, lien?: array{libelle: string, href: string}}>} */
    public static function cgu(): array
    {
        return [
            'rubrique' => 'Conditions générales d’utilisation',
            'titre' => 'Les règles du service, en clair.',
            'essentiel' => [
                'allomécano met en relation, il ne répare pas.',
                'Le prix se discute avec le mécanicien.',
                'On vérifie l’identité et les documents, pas chaque réparation.',
                'Votre position exacte sert à la recherche et n’est pas conservée.',
                'Vous pouvez signaler un mécanicien à tout moment.',
            ],
            'articles' => [
                ['titre' => 'Ce qu’est allomécano', 'paragraphes' => [
                    'allomécano met en relation des automobilistes et des motards en panne à Abidjan avec des mécaniciens dont l’identité et les documents ont été vérifiés.',
                    'allomécano ne réalise aucune réparation et n’emploie pas les mécaniciens.',
                ]],
                ['titre' => 'Chercher sans compte', 'paragraphes' => [
                    'Pour chercher un mécanicien, aucun compte n’est demandé. Votre prénom et votre numéro ne sont demandés que pour laisser un avis ou faire un signalement.',
                ]],
                ['titre' => 'Le prix et le paiement', 'paragraphes' => [
                    'Le prix se discute directement entre vous et le mécanicien, avant le début des travaux. Les tarifs affichés sur une fiche sont indicatifs.',
                    'allomécano n’encaisse aucun paiement et ne prend aucune commission sur la réparation.',
                ]],
                ['titre' => 'Ce que « vérifié » veut dire', 'paragraphes' => [
                    'Un mécanicien vérifié a fourni sa pièce d’identité, son permis de conduire et un diplôme ou une attestation de métier. Une personne de l’équipe a comparé sa photo à sa pièce d’identité.',
                    'La vérification porte sur l’identité et les documents. Elle ne garantit pas chaque réparation.',
                ]],
                ['titre' => 'Votre position', 'paragraphes' => [
                    'Votre position exacte sert uniquement à trier les mécaniciens par distance, à vol d’oiseau. Elle n’est pas conservée après la recherche.',
                ], 'lien' => ['libelle' => 'Lire la politique de confidentialité', 'href' => '/confidentialite']],
                ['titre' => 'Signaler un mécanicien', 'paragraphes' => [
                    'Depuis sa fiche, vous pouvez signaler un mécanicien à tout moment. L’équipe examine chaque signalement et peut suspendre le compte.',
                ]],
            ],
        ];
    }

    /** @return array{rubrique: string, titre: string, intro: string, tableau: list<array{0: string, 1: string, 2: string}>, note: string, articles: list<array{titre: string, paragraphes: list<string>, mono?: string}>} */
    public static function confidentialite(): array
    {
        return [
            'rubrique' => 'Confidentialité',
            'titre' => 'Ce qu’on garde, pourquoi, et combien de temps.',
            'intro' => 'On garde le moins de données possible, et jamais plus longtemps qu’il ne faut. Votre position exacte, on ne la garde pas.',
            'tableau' => [
                ['Position exacte', 'La recherche', 'Non conservée'],
                ['Hexagone d’environ 1 km d’une recherche sans résultat', 'Savoir où recruter (anonyme)', '90 jours'],
                ['Prénom et téléphone du client', 'Avis, signalement, rappel par le mécano', 'Masqués au mécano après 48 h'],
                ['Pièces du mécano', 'La vérification', 'Durée à fixer avec le juriste'],
                ['Journal des consultations de pièces', 'La traçabilité', '1 an'],
            ],
            'note' => 'Déclaration auprès de l’ARTCI à vérifier avant la mise en ligne.',
            'articles' => [
                ['titre' => 'Qui est responsable', 'paragraphes' => ['allomécano, Abidjan, Côte d’Ivoire. Pour toute question sur vos données :'], 'mono' => 'confidentialite@allomecano.ci'],
                ['titre' => 'Ce qu’on ne fait jamais', 'paragraphes' => ['On ne vend aucune donnée. On n’affiche aucune publicité. On ne garde pas l’historique de vos positions.']],
                ['titre' => 'Vos droits', 'paragraphes' => ['Vous pouvez demander à voir, corriger ou effacer vos données à tout moment, par le formulaire de contact. On vous répond sous 30 jours.']],
                ['titre' => 'Les pièces des mécaniciens', 'paragraphes' => ['Les pièces d’identité ne sont vues que par l’équipe de vérification. Elles ne sont jamais publiées ni téléchargeables, et chaque consultation est inscrite dans un journal.']],
            ],
        ];
    }

    /** Le PDF de la page (X5 « Télécharger en PDF »). */
    public static function pdf(string $quoi): string
    {
        $d = $quoi === 'cgu' ? self::cgu() : self::confidentialite();
        $blocs = [['rubrique', mb_strtoupper($d['rubrique'], 'UTF-8')], ['titre', $d['titre']], ['rubrique', 'MIS À JOUR LE ' . self::MAJ], ['filet', '']];
        if ($quoi === 'cgu') {
            $blocs[] = ['sous-titre', 'L’essentiel en 5 lignes'];
            foreach ($d['essentiel'] as $i => $ligne) {
                $blocs[] = ['texte', ($i + 1) . '. ' . $ligne];
            }
        } else {
            $blocs[] = ['texte', $d['intro']];
            $blocs[] = ['sous-titre', 'Vos données'];
            foreach ($d['tableau'] as [$donnee, $pourquoi, $duree]) {
                $blocs[] = ['texte', $donnee . ' · ' . $pourquoi . ' · ' . $duree];
            }
            $blocs[] = ['texte', $d['note']];
        }
        foreach ($d['articles'] as $i => $a) {
            $blocs[] = ['filet', ''];
            $blocs[] = ['rubrique', 'ARTICLE ' . ($i + 1)];
            $blocs[] = ['sous-titre', $a['titre']];
            foreach ($a['paragraphes'] as $p) {
                $blocs[] = ['texte', $p . (isset($a['mono']) ? ' ' . $a['mono'] : '')];
            }
        }
        return PdfTexte::generer($d['rubrique'] . ' · allomécano', $blocs);
    }
}
