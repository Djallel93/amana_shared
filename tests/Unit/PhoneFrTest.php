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

    public function testMessageIsTheOneOfTheAdminForm(): void
    {
        $this->assertSame('Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78', PhoneFr::MESSAGE);
    }
}
