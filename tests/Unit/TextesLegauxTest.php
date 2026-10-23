<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PdfTexte;
use App\Services\TextesLegaux;
use PHPUnit\Framework\TestCase;

/** X5 : les textes de la maquette et leur PDF sans bibliothèque. */
final class TextesLegauxTest extends TestCase
{
    public function testLesTextesDeFigma(): void
    {
        self::assertCount(5, TextesLegaux::cgu()['essentiel']);
        self::assertCount(6, TextesLegaux::cgu()['articles']);
        self::assertCount(4, TextesLegaux::confidentialite()['articles']);
        self::assertCount(5, TextesLegaux::confidentialite()['tableau']);
        self::assertSame('Les règles du service, en clair.', TextesLegaux::cgu()['titre']);
    }

    public function testLePdfEstUnPdfValide(): void
    {
        foreach (['cgu', 'confidentialite'] as $quoi) {
            $pdf = TextesLegaux::pdf($quoi);
            self::assertStringStartsWith('%PDF-1.4', $pdf);
            self::assertStringEndsWith("%%EOF\n", $pdf);
            preg_match('/startxref\n(\d+)\n/', $pdf, $m);
            self::assertSame('xref', substr($pdf, (int) $m[1], 4)); // la table des objets est où elle dit être
            self::assertGreaterThan(1500, strlen($pdf));
        }
    }

    public function testLePdfCoupeLesLignesEtChangeDePage(): void
    {
        $blocs = [['titre', 'Titre']];
        for ($i = 0; $i < 120; $i++) {
            $blocs[] = ['texte', str_repeat('mot ', 40)];
        }
        $pdf = PdfTexte::generer('Long', $blocs);
        preg_match('/\/Count (\d+)/', $pdf, $m);
        self::assertGreaterThan(1, (int) $m[1]);
    }
}
