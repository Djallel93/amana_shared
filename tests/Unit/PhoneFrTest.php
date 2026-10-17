<?php
// tests/Unit/PhoneFrTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Support\PhoneFr;
use PHPUnit\Framework\TestCase;

class PhoneFrTest extends TestCase
{
    public function testAcceptedFormats(): void
    {
        foreach (['06 12 34 56 78', '0612345678', '+33 6 12 34 56 78', '+33612345678', '0033 6 12 34 56 78', '01 23 45 67 89'] as $tel) {
            $this->assertSame(1, preg_match(PhoneFr::REGEX, $tel), $tel);
        }
    }

    public function testRejectedFormats(): void
    {
        foreach (['', '123', '00 12 34 56 78', '06 12 34 56', '06 12 34 56 789', '+44 6 12 34 56 78', 'abcdefghij', '06-12-34-56-78', ' 0612345678'] as $tel) {
            $this->assertSame(0, preg_match(PhoneFr::REGEX, $tel), "[{$tel}]");
        }
    }

    public function testFormaterNormalisesEveryAcceptedInput(): void
    {
        foreach (['01 23 45 67 89', '0123456789', '+33 1 23 45 67 89', '+33123456789', '0033 1 23 45 67 89', '01.23.45.67.89', '01-23-45-67-89'] as $tel) {
            $this->assertSame('+33 1 23 45 67 89', PhoneFr::formater($tel), $tel);
        }

        $this->assertSame('+33 6 12 34 56 78', PhoneFr::formater('0612345678'));
    }

    public function testFormaterLeavesUnknownNumbersAsTheyAre(): void
    {
        $this->assertNull(PhoneFr::formater(null));
        $this->assertNull(PhoneFr::formater(''));
        $this->assertNull(PhoneFr::formater('   '));
        $this->assertSame('+44 20 7946 0958', PhoneFr::formater('+44 20 7946 0958'));
        $this->assertSame('06 12 34', PhoneFr::formater('06 12 34'));
    }

    public function testLienIsDialableWithoutSpaces(): void
    {
        $this->assertSame('+33123456789', PhoneFr::lien('01 23 45 67 89'));
        $this->assertSame('+33612345678', PhoneFr::lien('+33 6 12 34 56 78'));
        $this->assertSame('+442079460958', PhoneFr::lien('+44 20 7946 0958'));
        $this->assertNull(PhoneFr::lien(null));
        $this->assertNull(PhoneFr::lien('abc'));
    }

    public function testMessageIsTheOneOfTheAdminForm(): void
    {
        $this->assertSame('Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78', PhoneFr::MESSAGE);
    }
}
