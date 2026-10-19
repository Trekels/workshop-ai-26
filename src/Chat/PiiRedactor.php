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

    public function redact(string $text): string
    {
        // TODO laag 5: vervang e-mailadressen door self::EMAIL en telefoonnummers door self::PHONE.
        // Twee preg_replace()-calls volstaan. De tests in tests/Chat/PiiRedactorTest.php zeggen welke vormen
        // je minstens moet herkennen, en wat je vooral níét mag kapotmaken ("Portal 2", "onder 20 euro").
        return $text;
    }
}
