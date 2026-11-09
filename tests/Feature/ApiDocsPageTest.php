<?php

use App\Models\User;

// The docs page is visible in the local environment only (viewApiDocs gate)
beforeEach(fn () => app()->detectEnvironment(fn () => 'local'));

test('the API docs page loads self-hosted assets only, with the CSP nonce (#1217)', function () {
    $html = $this->actingAs(User::factory()->withUserProfile()->create())->get('/docs/api')->assertOk()->getContent();

    expect($html)->not->toContain('unpkg.com', 'jsdelivr', '<body style')
        ->toContain('/vendor/stoplight-elements/8.4.2/web-components.min.js', '/vendor/fontawesome-svg-core/6.1.1/styles.css', 'autoAddCss: false');

    // Every script and style element carries the nonce, except JSON data blocks
    preg_match_all('/<(script|style)\b[^>]*>/i', (string) $html, $tags);
    foreach ($tags[0] as $tag) {
        if (str_contains($tag, 'application/json')) {
            continue;
        }
        expect($tag)->toContain('nonce=');
    }
});

test('the self-hosted assets of the API docs page exist', function () {
    foreach (['stoplight-elements/8.4.2/web-components.min.js', 'stoplight-elements/8.4.2/styles.min.css', 'fontawesome-svg-core/6.1.1/styles.css'] as $asset) {
        expect(public_path('vendor/'.$asset))->toBeFile();
    }
});
