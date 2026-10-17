<?php
// tests/Unit/PersonneTelephoneFormateTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Models\Personne;
use Amana\Shared\Tests\TestCase;

class PersonneTelephoneFormateTest extends TestCase
{
    public function testTelephoneFormateUsesTheFrenchDisplayFormat(): void
    {
        $p = new Personne();
        $p->telephone = '0612345678';

        $this->assertSame('+33 6 12 34 56 78', $p->telephone_formate);
        // La valeur saisie n'est pas modifiée.
        $this->assertSame('0612345678', $p->telephone);
    }

    public function testTelephoneFormateIsNullWithoutPhone(): void
    {
        $this->assertNull((new Personne())->telephone_formate);
    }
}
