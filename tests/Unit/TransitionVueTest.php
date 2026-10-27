<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Assets;
use PHPUnit\Framework\TestCase;

final class TransitionVueTest extends TestCase
{
    public function testActivationEnLigneEtSeulementSansMouvementReduit(): void
    {
        $gabarit = file_get_contents(RACINE . '/app/Views/layouts/base.php');
        self::assertIsString($gabarit);
        // La page d'arrivée doit accepter la transition avant son premier rendu : la règle est en ligne, pas dans une feuille externe
        self::assertStringContainsString('<style>@media (prefers-reduced-motion: no-preference) { @view-transition { navigation: auto; } }</style>', $gabarit);
        self::assertStringContainsString("addEventListener('pagereveal'", $gabarit);
    }

    public function testFeuilleCommuneUtiliseLesJetons(): void
    {
        self::assertContains('css/composants/transition-vue.css', Assets::CSS_COMMUN);
        $css = file_get_contents(RACINE . '/public/assets/css/composants/transition-vue.css');
        self::assertIsString($css);
        foreach (['var(--t-page)', 'var(--t-etat)', 'var(--t-lieu)', 'var(--ease-sortie)', '::view-transition-group(*.portrait)'] as $attendu) {
            self::assertStringContainsString($attendu, $css);
        }
        self::assertStringNotContainsString('@view-transition', preg_replace('#/\*.*?\*/#s', '', $css) ?? $css);
    }

    public function testLePortraitPorteUnNomUniqueEtSur(): void
    {
        self::assertSame('view-transition-name:portrait-koffi-yao-adjame;view-transition-class:portrait', vue_portrait('koffi-yao-adjame'));
        self::assertSame('view-transition-name:portrait-koffi;view-transition-class:portrait', vue_portrait('Koffi";}<'));
        self::assertSame('', vue_portrait(null));
        self::assertSame('', vue_portrait('";}'));
    }

    public function testLaLigneEtLaFicheDuMemeMecanoPartagentLeNom(): void
    {
        $ligne = composant('ligne-classement', ['href' => '/mecano/koffi-yao', 'nom' => 'Koffi Yao', 'portrait' => ['src' => '/p.webp'], 'specialites' => ['Batterie'], 'transition' => 'koffi-yao']);
        $fiche = composant('portrait-fiche', ['photo' => ['src' => '/p.webp', 'srcset' => '/p.webp 360w', 'couleur' => '#6f665b', 'cadrage' => '50% 30%'], 'nom' => 'Koffi Yao', 'retour' => '/', 'transition' => 'koffi-yao']);
        self::assertStringContainsString('view-transition-name:portrait-koffi-yao', $ligne);
        self::assertStringContainsString('view-transition-name:portrait-koffi-yao', $fiche);
        // Sans slug (récit de l'accueil, vitrine) : aucun nom, donc jamais de doublon sur la page
        self::assertStringNotContainsString('view-transition-name', composant('ligne-classement', ['nom' => 'Koffi Yao', 'portrait' => ['src' => '/p.webp'], 'specialites' => ['Batterie']]));
    }
}
