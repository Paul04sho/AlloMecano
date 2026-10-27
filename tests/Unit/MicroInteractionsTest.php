<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Lot 4 : barre client qui se replie au défilement, retours tactiles aux moments clés. */
final class MicroInteractionsTest extends TestCase
{
    private static function lire(string $chemin): string
    {
        $contenu = file_get_contents(RACINE . '/' . $chemin);
        self::assertIsString($contenu);
        return $contenu;
    }

    public function testLaBarreSeReplieSurMobileSeulementEtJamaisEnMouvementReduit(): void
    {
        $css = self::lire('public/assets/css/composants/navigation.css');
        self::assertStringContainsString('@media (max-width: 1023.98px) and (prefers-reduced-motion: no-preference)', $css);
        self::assertStringContainsString('.barre--repliee { transform: translateY(-100%); }', $css);

        $js = self::lire('public/assets/js/core/mouvement.js');
        self::assertStringContainsString('export function demarrerBarre()', $js);
        self::assertStringContainsString('barre.contains(document.activeElement)', $js); // le clavier la fait revenir
        self::assertStringContainsString('{ passive: true }', $js);
        self::assertStringContainsString('demarrerBarre();', self::lire('public/assets/js/core/app.js'));
    }

    public function testLesVibrationsAttendentUnGesteEtRespectentLeMouvementReduit(): void
    {
        $app = self::lire('public/assets/js/core/app.js');
        self::assertStringContainsString('export function vibrer(motif)', $app);
        self::assertStringContainsString('navigator.userActivation?.hasBeenActive === false', $app);
        self::assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)').matches) return;", $app);

        self::assertStringContainsString('vibrer(15)', self::lire('public/assets/js/pages/position.js'));
        self::assertStringContainsString('vibrer([20, 60, 20])', self::lire('public/assets/js/pages/appel.js'));
        self::assertStringContainsString('vibrer(10)', self::lire('public/assets/js/composants/contact.js'));
        self::assertStringContainsString('vibrer(10)', self::lire('public/assets/js/composants/fiche.js'));
    }
}
