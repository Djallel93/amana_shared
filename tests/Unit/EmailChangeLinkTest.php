<?php
// tests/Unit/EmailChangeLinkTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Services\EmailChangeLink;
use Amana\Shared\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmailChangeLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->routesProfil();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testFingerprintIsStableCaseInsensitiveAndDoesNotRevealTheAddress(): void
    {
        $a = EmailChangeLink::fingerprint('Jean@Exemple.fr');

        $this->assertSame($a, EmailChangeLink::fingerprint(' jean@exemple.fr '));
        $this->assertNotSame($a, EmailChangeLink::fingerprint('autre@exemple.fr'));
        $this->assertSame(32, strlen($a));
        $this->assertStringNotContainsString('jean', $a);
    }

    public function testFingerprintDependsOnTheAppKey(): void
    {
        $avant = EmailChangeLink::fingerprint('a@b.fr');
        $this->app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('z', 32)));

        $this->assertNotSame($avant, EmailChangeLink::fingerprint('a@b.fr'));
    }

    public function testLinkCarriesIdNewAddressAndFingerprintAndIsSigned(): void
    {
        $p = $this->personne([], ['email' => 'ancien@amana.test']);
        $url = EmailChangeLink::make($p, 'nouveau@amana.test');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame((string) $p->id, $q['id']);
        $this->assertSame('nouveau@amana.test', $q['email']);
        $this->assertSame(EmailChangeLink::fingerprint('ancien@amana.test'), $q['fp']);
        $this->assertTrue(Request::create($url)->hasValidSignature());
    }

    public function testAnyTamperingBreaksTheSignature(): void
    {
        $p = $this->personne();
        $url = EmailChangeLink::make($p, 'nouveau@amana.test');

        $this->assertFalse(Request::create(str_replace('nouveau', 'pirate', $url))->hasValidSignature());
        $this->assertFalse(Request::create(preg_replace('/id=\d+/', 'id=999', $url))->hasValidSignature());
    }

    public function testLinkExpiresAfter60Minutes(): void
    {
        $p = $this->personne();
        Carbon::setTestNow('2026-09-20 10:00:00');
        $url = EmailChangeLink::make($p, 'nouveau@amana.test');

        Carbon::setTestNow('2026-09-20 10:59:00');
        $this->assertTrue(Request::create($url)->hasValidSignature());

        Carbon::setTestNow('2026-09-20 11:01:00');
        $this->assertFalse(Request::create($url)->hasValidSignature());
    }

    public function testLinkDiesOnceTheCurrentAddressChanged(): void
    {
        $p = $this->personne([], ['email' => 'ancien@amana.test']);
        parse_str((string) parse_url(EmailChangeLink::make($p, 'nouveau@amana.test'), PHP_URL_QUERY), $q);

        $this->assertTrue(EmailChangeLink::isCurrent($p, $q['fp']));

        $p->email = 'nouveau@amana.test';
        $this->assertFalse(EmailChangeLink::isCurrent($p, $q['fp']));
    }
}
