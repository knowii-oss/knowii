<?php

use App\Models\User;

test('robots.txt refuses the AI crawlers and keeps crawlers out of member areas (#104)', function () {
    config(['robots.allow_indexing' => true]);

    $response = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $body = (string) $response->getContent();

    foreach (['GPTBot', 'ClaudeBot', 'CCBot', 'Google-Extended', 'PerplexityBot', 'Bytespider'] as $crawler) {
        expect($body)->toContain("User-agent: {$crawler}\n");
    }
    expect($body)->toContain("User-agent: *\nDisallow: /api/")
        ->and($body)->toContain('Disallow: /community/')
        ->and($body)->toContain('Disallow: /dashboard')
        ->and($body)->toContain("Allow: /\n");
});

test('robots.txt refuses everything on an instance without indexing (acceptance) (#104)', function () {
    config(['robots.allow_indexing' => false]);

    $body = (string) $this->get('/robots.txt')->getContent();

    expect($body)->toContain("User-agent: *\nDisallow: /\n")
        ->and($body)->not->toContain('Allow: /');
});

test('indexing is off by default outside production (#104)', function () {
    expect(app()->environment('production'))->toBeFalse()
        ->and(config('robots.allow_indexing'))->toBeFalse();
});

test('public pages are indexable for guests in production (#104)', function () {
    config(['robots.allow_indexing' => true]);

    $response = $this->get('/')->assertOk();

    $response->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
    expect((string) $response->getContent())->toContain('<meta name="robots" content="index, follow, max-image-preview:large"');
});

test('pages of a signed-in member are never indexed (#104)', function () {
    config(['robots.allow_indexing' => true]);
    $user = User::factory()->withUserProfile()->withPersonalCommunity()->create();

    $response = $this->actingAs($user)->get('/dashboard')->assertOk();

    $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect((string) $response->getContent())->toContain('<meta name="robots" content="noindex, nofollow"');
});

test('the API is never indexed (#104)', function () {
    config(['robots.allow_indexing' => true]);

    $this->getJson('/api/v1/ping')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('an instance without indexing sends noindex on public pages too (#104)', function () {
    config(['robots.allow_indexing' => false]);

    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
