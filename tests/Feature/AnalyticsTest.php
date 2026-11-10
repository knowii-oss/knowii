<?php

/**
 * Plausible statistics are configuration, off by default (#1316): development, acceptance and self-hosted instances
 * send nothing to the knowii.net site.
 */
test('without configuration, the pages load no analytics script', function () {
    config(['services.plausible.domain' => null, 'services.plausible.proxy_url' => null]);

    $this->get('/')->assertOk()->assertDontSee('data-domain=', false)->assertDontSee('/content/script.js', false);
});

test('a domain without a proxy, or a proxy without a domain, loads nothing', function (?string $domain, ?string $proxy) {
    config(['services.plausible.domain' => $domain, 'services.plausible.proxy_url' => $proxy]);

    $this->get('/')->assertOk()->assertDontSee('/content/script.js', false);
})->with([
    'domain only' => ['knowii.net', null],
    'proxy only' => [null, 'https://stats.example.com'],
    'proxy without host' => ['knowii.net', 'not a url'],
]);

test('with configuration, the pages load the script through the proxy with the nonce', function () {
    config(['services.plausible.domain' => 'knowii.net', 'services.plausible.proxy_url' => 'https://stats.example.com/ignored/path']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-domain="knowii.net"')
        ->toContain('data-api="https://stats.example.com/api/v1/event"')
        ->toContain('src="https://stats.example.com/content/script.js"')
        ->toMatch('/<script\s+defer\s+data-domain="knowii.net"[^>]*nonce="[^"]+"/');
});

test('the Content Security Policy allows the proxy only when analytics is configured', function () {
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

        [$scripts, $connect] = $load('knowii.net', 'https://stats.example.com');
        expect($scripts)->toContain('https://stats.example.com')
            ->and($connect)->toContain('https://stats.example.com/api/v1/event');
    } finally {
        putenv('PLAUSIBLE_DOMAIN');
        putenv('PLAUSIBLE_PROXY_URL');
    }
});
