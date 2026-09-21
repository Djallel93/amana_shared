<?php
// tests/Support/FakeNavBadgeProvider.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Support;

use Amana\Shared\Contracts\NavBadgeProvider;

final class FakeNavBadgeProvider implements NavBadgeProvider
{
    /** @var array<string, int> */
    public static array $counts = [];
    public static int $calls = 0;
    public static bool $throw = false;

    public static function reset(): void
    {
        self::$counts = [];
        self::$calls = 0;
        self::$throw = false;
    }

    public function counts(): array
    {
        self::$calls++;
        if (self::$throw) {
            throw new \RuntimeException('base indisponible');
        }

        return self::$counts;
    }
}
