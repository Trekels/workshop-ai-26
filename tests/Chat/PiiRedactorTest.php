<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\PiiRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PiiRedactorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function personalData(): iterable
    {
        yield 'e-mail' => [
            'Mail me op jan.janssens@student.be als je iets vindt.',
            'Mail me op [e-mail] als je iets vindt.',
        ];
        yield 'gsm met spaties' => ['Bel me op 0470 12 34 56.', 'Bel me op [telefoon].'];
        yield 'gsm met punten en slash' => ['Mijn nummer: 0470/12.34.56', 'Mijn nummer: [telefoon]'];
        yield 'vast nummer' => ['Overdag op 02 123 45 67', 'Overdag op [telefoon]'];
        yield 'internationaal' => ['Of via +32 470 12 34 56 (WhatsApp)', 'Of via [telefoon] (WhatsApp)'];
        yield 'alles samen' => [
            'Ik ben Jan (jan@example.com, 0470123456), iets voor 4 spelers?',
            'Ik ben Jan ([e-mail], [telefoon]), iets voor 4 spelers?',
        ];
    }

    #[DataProvider('personalData')]
    public function testMasksEmailAddressesAndPhoneNumbers(string $input, string $expected): void
    {
        self::assertSame($expected, (new PiiRedactor())->redact($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function harmlessText(): iterable
    {
        yield 'titels met cijfers' => ['Is Portal 2 of Overcooked! 2 iets voor ons?'];
        yield 'prijzen en aantallen' => ['Iets voor 4 spelers onder 20 euro, liefst 2 controllers.'];
        yield 'jaartal en versie' => ['De 2026-editie, of versie 1.0 van Jackbox 9?'];
        yield 'apenstaart zonder adres' => ['Wat betekent @ in een gamertag?'];
    }

    #[DataProvider('harmlessText')]
    public function testLeavesOrdinaryTextAlone(string $input): void
    {
        self::assertSame($input, (new PiiRedactor())->redact($input));
    }
}
