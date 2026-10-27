<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Assets;
use PHPUnit\Framework\TestCase;

final class TransitionPageTest extends TestCase
{
    public function testEntreeDePageCommuneEstChargeeEtRespecteLesMouvementsReduits(): void
    {
        self::assertContains('css/composants/transition-page.css', Assets::CSS_COMMUN);

        $css = file_get_contents(RACINE . '/public/assets/css/composants/transition-page.css');
        self::assertIsString($css);
        self::assertStringContainsString('var(--t-page)', $css);
        self::assertStringContainsString('var(--ease-sortie)', $css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        self::assertStringContainsString('animation: none', $css);
    }

    public function testMarquageT1DeclareSesJetonsEtAnimeLaProgressionParTransform(): void
    {
        $tokens = file_get_contents(RACINE . '/public/assets/css/tokens.css');
        self::assertIsString($tokens);
        foreach ([
            '--t-nav-attente: 150ms;',
            '--t-nav-apparition: 120ms;',
            '--t-nav-progression: 2400ms;',
            '--t-nav-finition: 180ms;',
            '--ease-trickle: cubic-bezier(.1, .6, .2, 1);',
        ] as $jeton) {
            self::assertStringContainsString($jeton, $tokens);
        }

        $css = file_get_contents(RACINE . '/public/assets/css/composants/transition-page.css');
        self::assertIsString($css);
        self::assertStringContainsString('scaleX(.8)', $css);
        self::assertStringContainsString('var(--t-nav-attente)', $css);
        self::assertStringContainsString('var(--ease-trickle)', $css);
        self::assertStringContainsString('marquage-finition', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);

        $javascript = file_get_contents(RACINE . '/public/assets/js/core/mouvement.js');
        self::assertIsString($javascript);
        self::assertStringContainsString('export function estNavigationInterne', $javascript);
        self::assertStringContainsString('export function estActionFormulaireInterne', $javascript);
        self::assertStringContainsString('return estActionFormulaireInterne(action)', $javascript);
        self::assertStringContainsString('window.addEventListener(\'submit\'', $javascript);
        self::assertStringNotContainsString('preventDefault', $javascript);
    }
}
