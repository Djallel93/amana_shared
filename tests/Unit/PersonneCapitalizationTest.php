<?php
// tests/Unit/PersonneCapitalizationTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Models\Personne;
use Amana\Shared\Tests\TestCase;

class PersonneCapitalizationTest extends TestCase
{
    public function testNomIsAlwaysStoredUppercase(): void
    {
        $p = new Personne();
        $p->nom = 'dupont';
        $this->assertSame('DUPONT', $p->nom);

        $p->nom = 'DE LA FONTAINE';
        $this->assertSame('DE LA FONTAINE', $p->nom);

        $p->nom = '  martin  ';
        $this->assertSame('MARTIN', $p->nom);
    }

    public function testNomHandlesAccentsAndNonLatinScripts(): void
    {
        $p = new Personne();
        $p->nom = 'öztürk';
        $this->assertSame('ÖZTÜRK', $p->nom);

        $p->nom = 'محمد';
        $this->assertSame('محمد', $p->nom);
    }

    public function testPrenomIsTitleCased(): void
    {
        $p = new Personne();
        $p->prenom = 'amana';
        $this->assertSame('Amana', $p->prenom);

        $p->prenom = 'MARIE';
        $this->assertSame('Marie', $p->prenom);

        $p->prenom = 'mArIe cLaIrE';
        $this->assertSame('Marie Claire', $p->prenom);
    }

    public function testPrenomHandlesHyphensAndApostrophes(): void
    {
        $p = new Personne();
        $p->prenom = 'jean-pierre';
        $this->assertSame('Jean-Pierre', $p->prenom);

        $p->prenom = "o'brien";
        $this->assertSame("O'Brien", $p->prenom);

        $p->prenom = "d'ALI-ben youssef";
        $this->assertSame("D'Ali-Ben Youssef", $p->prenom);
    }

    public function testPrenomHandlesAccentsAndNonLatinScripts(): void
    {
        $p = new Personne();
        $p->prenom = 'émilie';
        $this->assertSame('Émilie', $p->prenom);

        $p->prenom = 'عمر';
        $this->assertSame('عمر', $p->prenom);
    }

    public function testNullIsLeftUntouched(): void
    {
        $p = new Personne();
        $p->nom = null;
        $p->prenom = null;

        $this->assertNull($p->nom);
        $this->assertNull($p->prenom);
    }

    public function testEmptyStringIsLeftEmpty(): void
    {
        $p = new Personne();
        $p->nom = '';
        $p->prenom = '';

        $this->assertSame('', $p->nom);
        $this->assertSame('', $p->prenom);
    }
}
