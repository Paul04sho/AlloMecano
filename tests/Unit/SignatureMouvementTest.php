<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Lot 5 : le voyant qui descend la route du récit, l'asphalte, les feux de détresse, la fiche et les résultats. */
final class SignatureMouvementTest extends TestCase
{
    private static function lire(string $chemin): string
    {
        $contenu = file_get_contents(RACINE . '/' . $chemin);
        self::assertIsString($contenu);
        return $contenu;
    }

    public function testLeVoyantSuitLaRouteSansUnitesDeConteneur(): void
    {
        $css = self::lire('public/assets/css/pages/accueil-defilement.css');
        self::assertStringContainsString('view-timeline: --route block', $css);
        self::assertSame(2, substr_count($css, 'animation-timeline: --route; animation-range: cover 25% cover 55%'));
        // Le tracé et le voyant partagent la même frise et la même plage : le voyant reste à la tête du tracé
        self::assertStringContainsString('translateY(calc(100% - 10px))', $css);
        // Les unités de conteneur (cqh, cqw) ne sont pas suivies par le rendu accéléré : ni dans le code, ni dans les keyframes
        self::assertSame(0, preg_match('/\d+cq[hw]/', $css));
    }

    public function testLesFrisesNommeesSontPorteesParUnElementQuiDefile(): void
    {
        self::assertStringContainsString('view-timeline: --asphalte block', self::lire('public/assets/css/pages/accueil-defilement.css'));
        // Le portrait est dans un cadre overflow:hidden : la frise est sur le cadre, pas sur l'image
        $fiche = self::lire('public/assets/css/pages/fiche.css');
        self::assertStringContainsString('.portrait-fiche--plein { view-timeline: --portrait block; }', $fiche);
        self::assertStringContainsString('animation-timeline: --portrait', $fiche);
    }

    public function testLesFeuxDeDetresseNeRestentPasAllumesPendantLeDelai(): void
    {
        $css = self::lire('public/assets/css/pages/accueil.css');
        self::assertStringContainsString('animation: feux-detresse 2s steps(1, end) 800ms forwards', $css);
        self::assertStringContainsString('html.demarrage-attente .heros .bouton--principal::after { animation-play-state: paused; }', $css);
    }

    public function testLaListeSePoseAuRythmeDesReperes(): void
    {
        self::assertStringContainsString('animation-delay: calc(var(--i, 0) * 70ms)', self::lire('public/assets/css/pages/resultats.css'));
        self::assertStringContainsString('<li style="--i:<?= (int) $i ?>"', self::lire('app/Views/client/resultats.php'));
        self::assertStringContainsString('style="--i:${i}"', self::lire('public/assets/js/pages/resultats.js'));
    }
}
