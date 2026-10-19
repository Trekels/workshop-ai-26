<?php

declare(strict_types=1);

namespace App\Chat;

/**
 * Data-minimalisatie: wat de gebruiker over zichzelf typt, heeft het model niet nodig om een game te kiezen.
 * Dus maskeren we het vóór het de server verlaat (en vóór het in onze database belandt).
 */
final class PiiRedactor
{
    public const string EMAIL = '[e-mail]';
    public const string PHONE = '[telefoon]';

    /**
     * Iets wat op een e-mailadres lijkt: tekens, een @, een domein met minstens één punt.
     */
    private const string EMAIL_PATTERN = '/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u';

    /**
     * Belgische nummers: 0470 12 34 56, 0470/12.34.56, 02 123 45 67, +32 470 12 34 56. Begint met 0 of +landcode,
     * daarna 8 tot 10 cijfers met eventueel een spatie, punt, slash of streepje ertussen. Niet voorafgegaan
     * door een cijfer of letter, zodat "versie 1.0" en "Jackbox 9" met rust gelaten worden.
     */
    private const string PHONE_PATTERN = '/(?<![\w\/.])(?:\+\d{2}\s?|0)\d(?:[\s.\/-]?\d){7,9}(?!\w)/';

    public function redact(string $text): string
    {
        $text = preg_replace(self::EMAIL_PATTERN, self::EMAIL, $text)
            ?? throw new \RuntimeException('E-mailpatroon faalt');

        return preg_replace(self::PHONE_PATTERN, self::PHONE, $text)
            ?? throw new \RuntimeException('Telefoonpatroon faalt');
    }
}
