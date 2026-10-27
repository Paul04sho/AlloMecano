<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Lot 3 : moments du parcours panne alignés sur docs/mouvement-et-interactions.html. */
final class MouvementParcoursTest extends TestCase
{
    private static function lire(string $chemin): string
    {
        $contenu = file_get_contents(RACINE . '/' . $chemin);
        self::assertIsString($contenu);
        return $contenu;
    }

    public function testLEcrouSouleveFaitGrandirSonOmbreAuSol(): void
    {
        self::assertStringContainsString('data-ombre', self::lire('app/Views/client/position-confirmer.php'));
        $js = self::lire('public/assets/js/pages/position-confirmer.js');
        self::assertStringContainsString("ombre?.animate([{ opacity: 0, transform: 'scale(.6)' }, { opacity: 1, transform: 'scale(1)' }]", $js);
        self::assertStringContainsString('duree(160)', $js);
    }

    public function testLArretDuRadarCoupeLesBouclesEtPasseLesAnneauxA50(): void
    {
        $css = self::lire('public/assets/css/pages/resultats.css');
        self::assertStringContainsString('.radar.arrete .radar__balayage { animation: none;', $css);
        self::assertStringContainsString('.radar.arrete .radar__anneau { animation: none; opacity: .5; transition: opacity 200ms linear; }', $css);
        self::assertStringContainsString("racine.classList.add('recherche--arretee')", self::lire('public/assets/js/pages/recherche.js'));
    }

    public function testLaBanniereReseauAnnonceEtRespecteLeMouvementReduit(): void
    {
        $js = self::lire('public/assets/js/core/app.js');
        self::assertStringContainsString('Hors ligne · on garde votre recherche', $js);
        self::assertStringContainsString('Vous êtes de nouveau en ligne', $js);
        self::assertStringContainsString("zone.setAttribute('aria-live', 'polite')", $js);
        self::assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : ms", $js);
        self::assertStringContainsString('var(--dispo-vif)', self::lire('public/assets/css/composants/signes.css'));
        self::assertStringContainsString('--dispo-vif: #3dd68c;', self::lire('public/assets/css/tokens.css'));
        self::assertStringContainsString('data-demo-reseau', self::lire('app/Views/communs/systeme.php'));
    }
}
