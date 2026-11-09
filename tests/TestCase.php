<?php

namespace Tests;

use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests never depend on the DNS: every host is a public address unless a test says otherwise (#1231)
        SafeUrl::resolveUsing(static fn (string $host): array => ['93.184.215.14']);

        // No bot check unless a test turns it on: the local .env may hold the Cloudflare test keys (#737)
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
    }

    protected function tearDown(): void
    {
        SafeUrl::resolveUsing(null);

        parent::tearDown();
    }
}
