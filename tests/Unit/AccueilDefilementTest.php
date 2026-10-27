<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AccueilDefilementTest extends TestCase
{
    public function testLeRecitAuDefilementEstUneAmeliorationProgressive(): void
    {
        $css = file_get_contents(RACINE . '/public/assets/css/pages/accueil-defilement.css');
        self::assertIsString($css);
        // Rien ne s'anime sans prise en charge ni en mouvement réduit : l'accueil reste visible tel quel
        self::assertStringContainsString('@supports (animation-timeline: view())', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: no-preference)', $css);
        self::assertSame(1, preg_match('/@supports[^{]*\{\s*@media \(prefers-reduced-motion: no-preference\)/', $css));
        // Seulement opacité et transformations, distances par les jetons
        self::assertSame(0, preg_match('/@keyframes[^}]*\{[^}]*(width|height|top|left|margin)\s*:/', $css));
        self::assertStringContainsString('var(--esp-24)', $css);
        self::assertStringContainsString('var(--ease-tampon)', $css);
    }

    public function testLaFeuilleNEstChargeeQueSurLAccueil(): void
    {
        $controleur = file_get_contents(RACINE . '/app/Controllers/Client/AccueilController.php');
        self::assertIsString($controleur);
        self::assertSame(2, substr_count($controleur, "'css/pages/accueil-defilement.css'"));
        self::assertNotContains('css/pages/accueil-defilement.css', \App\Core\Assets::CSS_COMMUN);
    }

    public function testLeComptageDesCommunesRespecteLeMouvementReduit(): void
    {
        $js = file_get_contents(RACINE . '/public/assets/js/pages/accueil.js');
        self::assertIsString($js);
        self::assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $js);
        self::assertStringContainsString("'IntersectionObserver' in window", $js);
    }
}
