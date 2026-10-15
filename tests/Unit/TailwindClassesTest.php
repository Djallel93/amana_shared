<?php
// tests/Unit/TailwindClassesTest.php
//
// Garde-fou Tailwind 4 (14/10/2026) : les vues Blade du package ne doivent
// contenir aucun utilitaire supprimé en v4 (guide de migration Tailwind 4,
// « Removed utilities »). Les noms renommés dont le rendu est conservé par le
// pont @config des apps (shadow, shadow-sm, rounded, rounded-sm…) sont
// volontairement autorisés.

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TailwindClassesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function deniedPatterns(): array
    {
        $b = '(?<![\w-])'; // début de jeton
        $e = '(?![\w-])';  // fin de jeton

        return [
            'flex-shrink-* → shrink-*'                  => ["/{$b}flex-shrink(?:-\d+)?{$e}/"],
            'flex-grow-* → grow-*'                      => ["/{$b}flex-grow(?:-\d+)?{$e}/"],
            'outline-none → outline-hidden'             => ["/{$b}outline-none{$e}/"],
            'overflow-ellipsis → text-ellipsis'         => ["/{$b}overflow-ellipsis{$e}/"],
            'decoration-slice/clone → box-decoration-*' => ["/{$b}decoration-(?:slice|clone){$e}/"],
            '*-opacity-N → modificateur /N'             => ["/{$b}(?:bg|text|border|divide|ring|placeholder)-opacity-\d+{$e}/"],
            'blur-sm / drop-shadow-sm → -xs'            => ["/{$b}(?:backdrop-blur|blur|drop-shadow)-sm{$e}/"],
            'max-w-screen-* → max-w-(--breakpoint-*)'   => ["/{$b}max-w-screen-[a-z0-9]+{$e}/"],
            '@tailwind … → @import "tailwindcss"'       => ['/@tailwind\s+(?:base|components|utilities)\b/'],
            'focus-visible:outline nu → -solid'         => ["/{$b}(?:focus-visible|focus|hover):outline{$e}/"],
        ];
    }

    #[DataProvider('deniedPatterns')]
    public function testBladeViewsContainNoRemovedTailwindUtility(string $pattern): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
        $hits = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) ?: [] as $n => $line) {
                if (strlen($line) > 2000) {
                    continue; // CSS inliné : hors périmètre
                }
                if (preg_match($pattern, $line, $m) === 1) {
                    $hits[] = str_replace($root . '/', '', $file->getPathname()) . ':' . ($n + 1) . ' ' . $m[0];
                }
            }
        }

        $this->assertSame([], $hits, "Utilitaire(s) supprimé(s) en Tailwind 4 :\n" . implode("\n", $hits));
    }
}
