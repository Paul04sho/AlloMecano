<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\QrCode;
use PHPUnit\Framework\TestCase;

/** QR code maison : comparé, masque par masque, à la bibliothèque de référence (python-qrcode) lors de l'écriture. */
final class QrCodeTest extends TestCase
{
    /** Empreintes relevées sur la matrice identique, module pour module, à celle de python-qrcode (niveau M, mode octets). */
    public function testLaMatriceNeBougePas(): void
    {
        foreach ([0 => '782370ff', 3 => '60cf41ef'] as $masque => $attendu) {
            $lignes = array_map(static fn (array $l): string => implode('', array_map(static fn (bool $b): string => $b ? '1' : '0', $l)), QrCode::matrice('tel:+2250707123456', $masque));
            self::assertSame($attendu, sprintf('%08x', crc32(implode(chr(10), $lignes))));
        }
    }

    public function testLaTailleSuitLaVersion(): void
    {
        self::assertCount(25, QrCode::matrice('tel:+2250707123456')); // version 2
        self::assertCount(21, QrCode::matrice('abc'));                // version 1
        self::assertCount(37, QrCode::matrice(str_repeat('q', 84)));  // version 5, la plus grande
    }

    public function testLesRepresDePositionSontEnPlace(): void
    {
        $m = QrCode::matrice('tel:+2250707123456');
        foreach ([[0, 0], [18, 0], [0, 18]] as [$x, $y]) {
            self::assertTrue($m[$y][$x] && $m[$y + 6][$x + 6] && $m[$y + 3][$x + 3]);
            self::assertFalse($m[$y + 1][$x + 1]);
        }
        self::assertTrue($m[17][8]); // module sombre fixe
    }

    public function testMemeTexteMemeMatriceEtMasqueChoisiSiAbsent(): void
    {
        self::assertSame(QrCode::matrice('hello'), QrCode::matrice('hello'));
        self::assertContains(QrCode::matrice('hello'), array_map(static fn (int $k): array => QrCode::matrice('hello', $k), range(0, 7)));
    }

    public function testLeSvgEstUnSeulTracéAvecZoneDeSilence(): void
    {
        $svg = QrCode::svg('tel:+2250707123456', 112);
        self::assertStringContainsString('viewBox="0 0 33 33"', $svg); // 25 + 2 × 4
        self::assertSame(1, substr_count($svg, '<path'));
        self::assertStringContainsString('width="112"', $svg);
    }

    public function testTropLongEstRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        QrCode::matrice(str_repeat('x', 85));
    }
}
