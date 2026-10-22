<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Ulid;
use App\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorEtUlidTest extends TestCase
{
    public function testTelephoneIncompletDitCombienIlManqueDeChiffres(): void
    {
        $v = Validator::verifier(['tel' => '07 07 12 34'], ['tel' => 'requis|telephone_ci']);
        self::assertTrue($v->echoue());
        self::assertSame('Il manque 2 chiffres : 10 chiffres après +225.', $v->erreurs()['tel']);
    }

    public function testTelephoneValideEstNormaliseEnE164(): void
    {
        $v = Validator::verifier(['tel' => '07 07 12 34 56'], ['tel' => 'requis|telephone_ci']);
        self::assertFalse($v->echoue());
        self::assertSame('+2250707123456', $v->donnees()['tel']);
    }

    public function testChampFacultatifVideAccepte(): void
    {
        $v = Validator::verifier(['email' => ''], ['email' => 'email']);
        self::assertFalse($v->echoue());
    }

    public function testMotDePasseHuitCaracteresEtUnChiffre(): void
    {
        self::assertTrue(Validator::verifier(['m' => 'abcdefgh'], ['m' => 'mot_de_passe'])->echoue());
        self::assertFalse(Validator::verifier(['m' => 'abcdefg1'], ['m' => 'mot_de_passe'])->echoue());
    }

    public function testUlidFormatEtUnicite(): void
    {
        $a = Ulid::generer();
        $b = Ulid::generer();
        self::assertTrue(Ulid::estValide($a));
        self::assertNotSame($a, $b);
        self::assertSame(26, strlen($a));
    }
}
