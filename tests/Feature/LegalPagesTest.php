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
})->with(['/dpa']);

test('the configured sub-processors include the services that Knowii calls (#904)', function () {
    $names = implode(' ', array_column(LegalPages::subprocessors(), 'name'));

    expect($names)->toContain('Hetzner')->toContain('Cloudflare')->toContain('Resend')->toContain('EmailOctopus')->toContain('Plausible');
});
