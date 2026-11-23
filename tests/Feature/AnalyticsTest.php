<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\InviteLink;

/**
 * Plausible statistics are configuration, off by default (#1316): development, acceptance and self-hosted instances
 * send nothing to a statistics site. The pages load no script of another host for the statistics: the shared prop
 * `statistics` tells the front end where to send one page view, with a cleaned address (ADR 0064).
 */
test('without configuration, the pages send no page view and load no statistics script', function () {
    config(['services.plausible.domain' => null, 'services.plausible.proxy_url' => null]);

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('statistics', null))
        ->assertDontSee('data-domain=', false)->assertDontSee('/content/script.js', false);
});

test('a domain without a proxy, or a proxy without a domain, sends nothing', function (?string $domain, ?string $proxy) {
    config(['services.plausible.domain' => $domain, 'services.plausible.proxy_url' => $proxy]);

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('statistics', null));
})->with([
    'domain only' => ['community.example.com', null],
    'proxy only' => [null, 'https://stats.example.com'],
    'proxy without host' => ['community.example.com', 'not a url'],
]);

test('with configuration, a page gets where to send its page view, and loads no script of the proxy', function () {
    config(['services.plausible.domain' => ' community.example.com ', 'services.plausible.proxy_url' => 'https://stats.example.com/ignored/path']);

    $response = $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('statistics', [
        'endpoint' => 'https://stats.example.com/api/v1/event',
        'domain' => 'community.example.com',
        'url' => url('/'),
    ]));

    expect((string) $response->getContent())->not->toContain('<script defer data-domain=')
        ->not->toContain('/content/script.js')
        ->not->toMatch('/<script[^>]+src="https:\/\/stats\.example\.com/');
});

test('the address of a page view keeps the origin, the path and the campaign keys of the query, and drops every other key', function () {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);

    $this->get('/?email=ada%40example.com&utm_source=newsletter&utm_campaign=autumn&token=abc&ref=friend&source=&utm_term[]=x')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('statistics.url', url('/').'?utm_source=newsletter&utm_campaign=autumn&ref=friend'));

    $this->get('/?email=ada%40example.com')->assertInertia(fn ($page) => $page->where('statistics.url', url('/')));
});

test('an Inertia visit gets the prop of its own page, with its own cleaned address', function () {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);
    $version = (string) app(HandleInertiaRequests::class)->version(request());

    $this->get('/?utm_medium=mail&page=2', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertJsonPath('props.statistics.url', url('/').'?utm_medium=mail');
});

test('the Content Security Policy allows the event address of the proxy only when analytics is configured, and never a script of it', function () {
    $load = function (?string $domain, ?string $proxy): array {
        foreach (['PLAUSIBLE_DOMAIN' => $domain, 'PLAUSIBLE_PROXY_URL' => $proxy] as $key => $value) {
            $value === null ? putenv($key) : putenv("{$key}={$value}");
            unset($_ENV[$key], $_SERVER[$key]);
        }
        $csp = require config_path('secure-headers.php');

        return [$csp['csp']['script-src']['allow'], $csp['csp']['connect-src']['allow']];
    };

    try {
        [$scripts, $connect] = $load(null, null);
        expect(implode(' ', [...$scripts, ...$connect]))->not->toContain('workers.dev')->not->toContain('stats.example.com');

        [$scripts, $connect] = $load('community.example.com', 'https://stats.example.com');
        expect(implode(' ', $scripts))->not->toContain('stats.example.com')
            ->and($connect)->toContain('https://stats.example.com/api/v1/event')
            ->and($connect)->not->toContain('https://stats.example.com');
    } finally {
        putenv('PLAUSIBLE_DOMAIN');
        putenv('PLAUSIBLE_PROXY_URL');
    }
});

test('the page of an invite link sends no page view, also when the instance sets the values (#1391)', function () {
    config(['services.plausible.domain' => 'community.example.com', 'services.plausible.proxy_url' => 'https://stats.example.com']);
    $link = InviteLink::factory()->create();

    $this->get(route('invite-links.open', ['inviteLinkToken' => $link->token]))->assertRedirect(route('invite-links.show'));
    $page = $this->get(route('invite-links.show'))->assertOk()->assertInertia(fn ($page) => $page->where('statistics', null));

    expect((string) $page->getContent())->not->toContain('stats.example.com');
    $this->get('/')->assertInertia(fn ($page) => $page->where('statistics.domain', 'community.example.com'));
});
