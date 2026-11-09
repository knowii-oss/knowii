<?php

use App\Support\ContentSecurityPolicySources;

/**
 * The Content Security Policy never accepts a whole scheme (#1183): every origin is listed explicitly.
 */

/**
 * @return array<string, list<string>> The directives of the CSP header, with their sources
 */
function cspDirectives(string $header): array
{
    $directives = [];

    foreach (array_filter(array_map('trim', explode(';', $header))) as $directive) {
        $parts = preg_split('/\s+/', $directive) ?: [];
        $name = array_shift($parts);
        $directives[(string) $name] = $parts;
    }

    return $directives;
}

it('sends a Content Security Policy without scheme-wide sources', function () {
    $response = $this->get('/');

    $response->assertOk();
    $header = (string) $response->headers->get('Content-Security-Policy');
    expect($header)->not->toBe('');

    foreach (cspDirectives($header) as $name => $sources) {
        foreach ($sources as $source) {
            expect(preg_match('/^(https?|wss?):$/i', $source))
                ->toBe(0, "The {$name} directive accepts the whole {$source} scheme");
            expect($source)->not->toBe('*', "The {$name} directive accepts any origin");
        }
    }
});

it('limits styles, fonts, images, media and manifests to explicit origins', function () {
    $directives = cspDirectives((string) $this->get('/')->headers->get('Content-Security-Policy'));

    expect($directives['style-src'])->toContain("'self'", 'https://fonts.googleapis.com')
        ->and($directives['style-src'])->not->toContain("'unsafe-inline'")
        ->and(collect($directives['style-src'])->contains(fn (string $source) => str_starts_with($source, "'nonce-")))->toBeTrue()
        ->and($directives['font-src'])->toBe(["'self'", 'data:', 'https://fonts.gstatic.com'])
        ->and($directives['img-src'])->toBe(["'self'", 'data:', 'blob:'])
        ->and($directives['media-src'])->toBe(["'self'"])
        ->and($directives['manifest-src'])->toBe(["'self'"]);
});

it('allows WebSockets to the Reverb host only', function () {
    $directives = cspDirectives((string) $this->get('/')->headers->get('Content-Security-Policy'));

    $expectedReverb = ContentSecurityPolicySources::reverbOrigin(env('REVERB_HOST'), env('REVERB_PORT'), env('REVERB_SCHEME'));

    expect($directives['connect-src'])->toContain("'self'")
        ->and($directives['connect-src'])->not->toContain('ws:', 'wss:');

    if ($expectedReverb !== null) {
        expect($directives['connect-src'])->toContain($expectedReverb);
    }
});
