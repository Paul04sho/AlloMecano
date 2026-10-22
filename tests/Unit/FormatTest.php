<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    public function testDistanceEnKilometresAvecVirgule(): void
    {
        self::assertSame('1,4', Format::km(1359));
        self::assertSame('0,8', Format::km(820));
        self::assertSame('12', Format::km(12400));
        self::assertSame("1,2\u{00A0}km", Format::distance(1200));
    }

    public function testMontantFcfaAvecEspaceFine(): void
    {
        self::assertSame("5\u{202F}000\u{00A0}FCFA", Format::fcfa(5000));
    }

    public function testTelephoneIvoirienParGroupesDeDeux(): void
    {
        $nbsp = "\u{00A0}";
        self::assertSame("+225{$nbsp}07{$nbsp}07{$nbsp}12{$nbsp}34{$nbsp}56", Format::telephone('+2250707123456'));
        self::assertSame("+225{$nbsp}07{$nbsp}07{$nbsp}12{$nbsp}34{$nbsp}56", Format::telephone('07 07 12 34 56'));
        self::assertSame('+2250707123456', Format::telephoneE164('07 07 12 34 56'));
    }

    public function testDateEnToutesLettres(): void
    {
        self::assertSame('12 mars 2026', Format::date('2026-03-12 10:14:00'));
        self::assertSame('1er octobre 2026', Format::date('2026-10-01'));
    }

    public function testTypographieFrancaiseSansCasserLesHeures(): void
    {
        self::assertSame("Où êtes-vous\u{202F}?", Format::typo('Où êtes-vous ?'));
        self::assertSame('Rendez-vous à 10:14', Format::typo('Rendez-vous à 10:14'));
        self::assertSame("Urgence\u{202F}: 185", Format::typo('Urgence : 185'));
    }
}
