<?php

use App\Support\Markdown;
use Tests\TestCase;

// The renderer reads the configuration (app.url)
uses(TestCase::class);

it('renders Markdown with GitHub extensions', function () {
    $html = Markdown::render("# Title\n\n**bold** ~~gone~~\n\n| a | b |\n| - | - |\n| 1 | 2 |\n\n- [x] done");

    expect($html)->toContain('<h1>Title</h1>', '<strong>bold</strong>', '<del>gone</del>', '<table>', 'type="checkbox"');
});

it('escapes raw HTML instead of passing it through', function (string $markdown) {
    $html = Markdown::render($markdown);

    expect($html)->not->toContain('<script', '<img src=x onerror', '<iframe', '<style');
})->with([
    'script' => ['<script>alert(1)</script>'],
    'event handler' => ['<img src=x onerror=alert(1)>'],
    'iframe' => ['<iframe src="https://evil.example"></iframe>'],
    'style' => ['<style>body{display:none}</style>'],
]);

it('removes unsafe links', function (string $markdown) {
    expect(Markdown::render($markdown))->not->toContain('javascript:', 'vbscript:');
})->with([
    'javascript link' => ['[click](javascript:alert(1))'],
    'vbscript link' => ['[click](vbscript:msgbox(1))'],
    'javascript autolink' => ['<javascript:alert(1)>'],
]);

it('opens links to other sites in a new tab without referrer', function () {
    $html = Markdown::render('[Knowii](https://example.com)');

    expect($html)->toContain('target="_blank"', 'nofollow', 'noopener', 'noreferrer');
});
