<?php

use App\Support\LegalPages;
use Illuminate\Support\Facades\File;

/**
 * The legal pages (#902, #903, #904, #77): one controller, Markdown rendered on the server, placeholders filled from
 * the configuration (ADR 0030).
 */
dataset('legal pages', [
    'privacy policy' => ['/privacy-policy', 'Privacy policy'],
    'terms of service' => ['/terms-of-service', 'Terms of service'],
    'data processing agreement' => ['/dpa', 'Data processing agreement'],
    'imprint' => ['/imprint', 'Imprint'],
    'content policy' => ['/content-policy', 'Content policy'],
]);

test('every legal page is public and rendered on the server', function (string $url, string $title) {
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component('Legal/Show')
        ->where('title', $title)
        ->where('html', fn (string $html) => str_contains($html, '<h1>'))
    );
})->with('legal pages');

test('no legal page keeps an unknown placeholder', function (string $url) {
    $this->get($url)->assertInertia(fn ($page) => $page->where('html', fn (string $html) => ! str_contains($html, '{{')));
})->with('legal pages');

test('a page in draft shows it, a self-hosted instance replaces a page with its own file', function () {
    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page->where('draft', true));

    $override = storage_path('app/legal/policy.md');
    File::ensureDirectoryExists(dirname($override));
    File::put($override, '---
title: Our privacy
updated: 2027-01-01
draft: false
---

# Our privacy

Example <b>Ltd</b>, {{retention.session}}.');

    try {
        $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
            ->where('title', 'Our privacy')
            ->where('draft', false)
            ->where('updated', '2027-01-01')
            ->where('html', fn (string $html) => str_contains($html, 'Example &lt;b&gt;Ltd&lt;/b&gt;, 2 hours.'))
        );
    } finally {
        File::delete($override);
    }
});

test('the privacy policy and the DPA list every sub-processor of the configuration (#904)', function (string $url) {
    config(['legal.subprocessors' => [
        ['name' => 'Example AI, Inc.', 'country' => 'United States', 'purpose' => 'Summaries', 'safeguard' => 'Standard contractual clauses'],
    ]]);

    $this->get($url)->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Example AI, Inc.</td>') && ! str_contains($html, 'Hetzner Online GmbH</td>'))
    );
})->with(['/privacy-policy', '/dpa']);

test('the configured sub-processors include the services that Knowii calls (#904)', function () {
    $names = implode(' ', array_column(LegalPages::subprocessors(), 'name'));

    expect($names)->toContain('Hetzner')->toContain('Cloudflare')->toContain('Resend')->toContain('EmailOctopus')->toContain('Plausible');
});

test('the privacy policy shows the retention periods of the configuration (ADR 0030)', function () {
    config(['session.lifetime' => 45, 'retention.waitlist_months' => 6]);

    $this->get('/privacy-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'Sessions: 45 minutes after your last activity')
            && str_contains($html, 'at most 6 months after you join it'))
    );
});

test('the imprint names the publisher, the responsible person, the contact and the host (#903)', function () {
    $this->get('/imprint')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'DeveloPassion SRL')
            && str_contains($html, '0717.561.755')
            && str_contains($html, 'BE0717561755')
            && str_contains($html, 'Sébastien Dubois')
            && str_contains($html, 'mailto:contact@developassion.be')
            && str_contains($html, 'Hetzner Online GmbH'))
    );
});

test('the content policy tells how to report illegal content, with the point of contact of the DSA (#902)', function () {
    $this->get('/content-policy')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, 'How to report content')
            && str_contains($html, 'mailto:contact@developassion.be')
            && str_contains($html, 'Digital Services Act')
            && str_contains($html, 'href="/terms-of-service"'))
    );
});

test('a sub-processor of an optional feature says that only the communities that turn it on use it (#904)', function () {
    config(['legal.subprocessors' => [
        ['name' => 'Example AI, Inc.', 'country' => 'United States', 'purpose' => 'Summaries of posts', 'safeguard' => 'Standard contractual clauses', 'feature' => 'AI summaries'],
    ]]);

    $this->get('/dpa')->assertInertia(fn ($page) => $page
        ->where('html', fn (string $html) => str_contains($html, '<td>Summaries of posts. Only for the communities whose owner turns on AI summaries.</td>')
            && str_contains($html, 'at least 14 days before'))
    );
});
