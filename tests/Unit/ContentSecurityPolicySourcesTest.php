<?php

use App\Support\ContentSecurityPolicySources as Sources;

it('builds the WebSocket origin of the Reverb server', function (?string $host, int|string|null $port, ?string $scheme, ?string $expected) {
    expect(Sources::reverbOrigin($host, $port, $scheme))->toBe($expected);
})->with([
    'TLS on the default port' => ['ws.knowii.net', 443, 'https', 'wss://ws.knowii.net'],
    'TLS on another port' => ['ws.knowii.net', '8443', 'https', 'wss://ws.knowii.net:8443'],
    'local, without TLS' => ['localhost', 4201, 'http', 'ws://localhost:4201'],
    'no port given, TLS' => ['ws.knowii.net', null, null, 'wss://ws.knowii.net'],
    'no host' => [null, 443, 'https', null],
    'empty host' => ['', 443, 'https', null],
]);

it('adds the Vite dev server in the local environment only', function () {
    expect(Sources::viteDevServerOrigins('local', null, 5173))->toBe(['http://localhost:5173', 'ws://localhost:5173'])
        ->and(Sources::viteDevServerOrigins('local', 'https://dev.example.test:5174/', null))->toBe(['https://dev.example.test:5174', 'wss://dev.example.test:5174'])
        ->and(Sources::viteDevServerOrigins('production', null, 5173))->toBe([])
        ->and(Sources::viteDevServerOrigins('testing', null, 5173))->toBe([]);
});

it('extracts the origin of a URL', function () {
    expect(Sources::origin('https://files.knowii.net/uploads/a.webp'))->toBe('https://files.knowii.net')
        ->and(Sources::origin('http://localhost:9000/bucket'))->toBe('http://localhost:9000')
        ->and(Sources::origin('not a url'))->toBeNull()
        ->and(Sources::origin(null))->toBeNull();
});

it('reads source lists from the environment without whole schemes or wildcards', function () {
    expect(Sources::list('https://a.example, https:, *, wss:, https://b.example'))->toBe(['https://a.example', 'https://b.example'])
        ->and(Sources::list(''))->toBe([])
        ->and(Sources::list(null))->toBe([]);
});

it('removes duplicates and empty sources', function () {
    expect(Sources::unique(['https://a.example', null, '', 'https://a.example', 'https://b.example']))->toBe(['https://a.example', 'https://b.example']);
});
