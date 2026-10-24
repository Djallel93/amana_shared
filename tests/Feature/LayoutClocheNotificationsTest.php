<?php
// tests/Feature/LayoutClocheNotificationsTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Feature;

use Amana\Shared\Tests\TestCase;

/**
 * 23/10/2026 — la cloche de notifications (@amana/shared-ui) est en position fixed dans le coin
 * supérieur droit : le layout doit lui réserver la place (padding-haut de 3.75rem, pt-15, sur
 * <main>), sinon elle recouvre les boutons d'action des en-têtes de page.
 * Voir docs/notification-bell-layout.md.
 */
class LayoutClocheNotificationsTest extends TestCase
{
    public function testMainReservesRoomForTheNotificationBell(): void
    {
        $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

        preg_match('/<main class="([^"]*)"/', $layout, $match);
        $classes = preg_split('/\s+/', $match[1] ?? '') ?: [];

        $this->assertContains('pt-15', $classes);
        $this->assertNotContains('p-8', $classes);
    }

    public function testTheLayoutDocumentationExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/docs/notification-bell-layout.md');
    }
}
