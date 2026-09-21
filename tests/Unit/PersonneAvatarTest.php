<?php
// tests/Unit/PersonneAvatarTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Models\Personne;
use Amana\Shared\Tests\TestCase;

class PersonneAvatarTest extends TestCase
{
    private function p(?string $prenom, ?string $nom, ?int $id = null): Personne
    {
        $p = new Personne();
        $p->prenom = $prenom;
        $p->nom = $nom;
        if ($id !== null) {
            $p->id = $id;
        }

        return $p;
    }

    public function testBasicInitialsAreUppercase(): void
    {
        $this->assertSame('AD', $this->p('amana', 'dupont')->initiales);
    }

    public function testAccentedAndMultibyteInitials(): void
    {
        $this->assertSame('ÉÖ', $this->p('émilie', 'öztürk')->initiales);
        $this->assertSame('ÀÑ', $this->p('àlex', 'ñandú')->initiales);
    }

    public function testCompoundAndHyphenatedNamesKeepOnlyTheFirstLetterOfEachField(): void
    {
        $this->assertSame('JM', $this->p('Jean-Pierre', 'Martin-Dupont')->initiales);
        $this->assertSame('MD', $this->p('Marie Claire', 'de la Fontaine')->initiales);
    }

    public function testLeadingNonLettersAreSkipped(): void
    {
        $this->assertSame('AO', $this->p('(Ali', "'Omar")->initiales);
        $this->assertSame('X', $this->p('  ', '12 x')->initiales);
    }

    public function testEmptyNullOrLetterlessValuesFallBackToQuestionMark(): void
    {
        $this->assertSame('?', $this->p('', '')->initiales);
        $this->assertSame('?', $this->p(null, null)->initiales);
        $this->assertSame('?', $this->p('123', '---')->initiales);
    }

    public function testOnlyOneFieldGivesASingleLetter(): void
    {
        $this->assertSame('A', $this->p('Amana', '')->initiales);
        $this->assertSame('D', $this->p(null, 'Dupont')->initiales);
    }

    public function testNonLatinScriptInitials(): void
    {
        $this->assertSame('عم', $this->p('عمر', 'محمد')->initiales);
    }

    public function testColourIsDeterministicFromIdOnly(): void
    {
        $a = $this->p('A', 'B', 42);
        $b = $this->p('Z', 'Y', 42);

        $this->assertSame($a->couleur_avatar, $b->couleur_avatar);
        $this->assertMatchesRegularExpression('/^hsl\(\d{1,3}, 55%, 30%\)$/', $a->couleur_avatar);
        $this->assertNotSame($this->p('A', 'B', 1)->couleur_avatar, $this->p('A', 'B', 2)->couleur_avatar);
    }

    public function testConsecutiveIdsGetClearlyDistinctHues(): void
    {
        $teinte = fn (int $id): int => (int) (preg_match('/hsl\((\d+),/', $this->p('A', 'B', $id)->couleur_avatar, $m) ? $m[1] : -1);

        for ($id = 1; $id < 60; $id++) {
            $ecart = abs($teinte($id) - $teinte($id + 1));
            $ecart = min($ecart, 360 - $ecart);
            $this->assertTrue($ecart >= 60, "ids {$id}/" . ($id + 1) . " : écart de teinte {$ecart}°");
        }
    }

    public function testUnsavedPersonGetsAStableDefaultColour(): void
    {
        $this->assertSame('hsl(210, 55%, 30%)', $this->p('A', 'B')->couleur_avatar);
    }

    public function testWhiteTextHasAtLeast45ContrastOnEveryHueUsed(): void
    {
        $pire = 99.0;
        for ($id = 1; $id <= 720; $id++) {
            preg_match('/hsl\((\d+), (\d+)%, (\d+)%\)/', $this->p('A', 'B', $id)->couleur_avatar, $m);
            $pire = min($pire, $this->contrasteAvecBlanc((int) $m[1], (int) $m[2] / 100, (int) $m[3] / 100));
        }

        $this->assertTrue($pire >= 4.5, "contraste minimal {$pire}");
    }

    private function contrasteAvecBlanc(int $h, float $s, float $l): float
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h % 360, 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x],
            3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
        };
        $lin = static fn (float $v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        $lum = 0.2126 * $lin($r + $m) + 0.7152 * $lin($g + $m) + 0.0722 * $lin($b + $m);

        return 1.05 / ($lum + 0.05);
    }
}
