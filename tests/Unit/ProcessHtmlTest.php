<?php

use App\Traits\ProcessHtml;
use Tests\TestCase;

// purifyHtml() reads the configuration of mews/purifier
uses(TestCase::class);

function processHtml(): object
{
    return new class
    {
        use ProcessHtml;
    };
}

function htmlFixture(string $name): string
{
    return (string) file_get_contents(dirname(__DIR__).'/Fixtures/html/'.$name.'.html');
}

const MALFORMED_HTML = '<html lang="fr"><head><title>Broken <b>page</title><meta property="og:title" content="Unclosed <head><body><p>text<div>';

test('purifyHtml removes scripts, event handlers and iframes, and keeps text and links', function () {
    $clean = (string) processHtml()->purifyHtml(htmlFixture('article'));

    expect($clean)->toContain('permanent notes', 'href="https://example.com/notes"')
        ->not->toContain('<script', 'onerror', '<iframe', 'alert(');
});

test('getHtmlPageMeta reads name, property and http-equiv tags in any attribute order, with entities decoded', function () {
    $meta = processHtml()->getHtmlPageMeta(htmlFixture('article'));

    expect($meta['og:title'])->toBe('Smart notes & Zettelkasten')
        ->and($meta['og:description'])->toBe('Open Graph description, with "quotes".')
        ->and($meta['description'])->toBe('The plain description.')
        ->and($meta['twitter:creator'])->toBe('@author')
        // The first og:image is the primary one
        ->and($meta['og:image'])->toBe('https://example.com/images/cover.jpg');
});

test('the title comes from Open Graph, then Twitter, then the title element', function () {
    expect(processHtml()->getHtmlPageTitle(htmlFixture('article')))->toBe('Smart notes & Zettelkasten')
        ->and(processHtml()->getHtmlPageTitle('<meta name="twitter:title" content="From Twitter"><title>From title</title>'))->toBe('From Twitter')
        // Whitespace inside the title element is collapsed
        ->and(processHtml()->getHtmlPageTitle(htmlFixture('minimal')))->toBe('Only a title');
});

test('the description comes from Open Graph, then Twitter, then the description meta tag', function () {
    expect(processHtml()->getHtmlPageDescription(htmlFixture('article')))->toBe('Open Graph description, with "quotes".')
        ->and(processHtml()->getHtmlPageDescription('<meta name="twitter:description" content="Tw"><meta name="description" content="Plain">'))->toBe('Tw')
        ->and(processHtml()->getHtmlPageDescription(htmlFixture('minimal')))->toBe('Only a description');
});

test('dates come from the article meta tags, then from the JSON-LD data', function () {
    expect(processHtml()->getHtmlPublishedTime(htmlFixture('article')))->toBe('2026-11-01T09:30:00+01:00')
        ->and(processHtml()->getHtmlModifiedTime(htmlFixture('article')))->toBe('2026-11-05T18:00:00+01:00')
        ->and(processHtml()->getHtmlPublishedTime(htmlFixture('minimal')))->toBe('2025-03-14')
        ->and(processHtml()->getHtmlModifiedTime(htmlFixture('minimal')))->toBe('2025-04-01T10:00:00Z');
});

test('an invalid date gives null instead of an exception', function () {
    expect(processHtml()->getHtmlPublishedTime('<meta property="article:published_time" content="yesterday-ish soon">'))->toBeNull()
        ->and(processHtml()->getHtmlModifiedTime('<script type="application/ld+json">{"dateModified": "not a date"}</script>'))->toBeNull()
        ->and(processHtml()->getHtmlPublishedTime('<script type="application/ld+json">{broken json</script>'))->toBeNull();
});

test('the cover image comes from Open Graph, then Twitter, and a relative URL is resolved against the page', function () {
    expect(processHtml()->getHtmlCoverImage(htmlFixture('article')))->toBe('https://example.com/images/cover.jpg')
        ->and(processHtml()->getHtmlCoverImageAlt(htmlFixture('article')))->toBe('A desk with index cards')
        ->and(processHtml()->getHtmlCoverImage(htmlFixture('minimal')))->toBe('/media/cover.png')
        ->and(processHtml()->getHtmlCoverImage(htmlFixture('minimal'), 'https://blog.example.com/posts/notes'))->toBe('https://blog.example.com/media/cover.png')
        ->and(processHtml()->getHtmlCoverImage('<meta property="og:image" content="img/c.png">', 'https://blog.example.com/posts/notes'))->toBe('https://blog.example.com/posts/img/c.png')
        ->and(processHtml()->getHtmlCoverImage('<meta property="og:image" content="//cdn.example.com/c.png">', 'https://blog.example.com/'))->toBe('https://cdn.example.com/c.png')
        ->and(processHtml()->getHtmlCoverImageAlt(htmlFixture('minimal')))->toBeNull();
});

test('the Open Graph type, the Twitter account and the language', function () {
    expect(processHtml()->getHtmlOgType(htmlFixture('article')))->toBe('article')
        ->and(processHtml()->getHtmlTwitterUsername(htmlFixture('article')))->toBe('@author')
        ->and(processHtml()->getHtmlTwitterUsername('<meta name="twitter:site" content="@site">'))->toBe('@site')
        ->and(processHtml()->getHtmlLanguage(htmlFixture('article')))->toBe('en_GB')
        ->and(processHtml()->getHtmlLanguage('<meta http-equiv="Content-Language" content="nl-BE"><html lang="fr">'))->toBe('nl-BE')
        ->and(processHtml()->getHtmlLanguage('<html lang="fr"><title>t</title></html>'))->toBe('fr');
});

test('every method returns null or an empty result for an empty page', function (string $html) {
    $process = processHtml();

    expect($process->getHtmlPageMeta($html))->toBe([])
        ->and($process->getHtmlPageTitle($html))->toBeNull()
        ->and($process->getHtmlPageDescription($html))->toBeNull()
        ->and($process->getHtmlPublishedTime($html))->toBeNull()
        ->and($process->getHtmlModifiedTime($html))->toBeNull()
        ->and($process->getHtmlCoverImage($html))->toBeNull()
        ->and($process->getHtmlCoverImageAlt($html))->toBeNull()
        ->and($process->getHtmlOgType($html))->toBeNull()
        ->and($process->getHtmlTwitterUsername($html))->toBeNull()
        ->and($process->getHtmlLanguage($html))->toBeNull()
        ->and($process->getHtmlKeywords($html))->toBe([]);
})->with([
    'empty string' => [''],
    'whitespace' => ["  \n "],
    'page without metadata' => ['<html><body><p>Hi</p></body></html>'],
]);

test('malformed HTML and other encodings never throw', function () {
    $process = processHtml();

    expect($process->getHtmlPageTitle(MALFORMED_HTML))->toBeString()
        ->and($process->getHtmlLanguage(MALFORMED_HTML))->toBe('fr')
        ->and($process->getHtmlPageTitle("<title>Caf\xE9 ISO-8859-1</title>"))->toBeString()
        ->and($process->getHtmlPageMeta("\x00\xFF<meta name=x content=y>"))->toBeArray();
});

test('keywords from the keywords meta tag are lowercased, trimmed and kept in order, without empty entries', function () {
    expect(processHtml()->getHtmlKeywords('<meta name="keywords" content=" PKM,  Smart Notes ,,Zettelkasten , ">'))
        ->toBe(['pkm', 'smart notes', 'zettelkasten']);
});

test('keywords and article tags are merged without duplicates', function () {
    $html = '<meta name="keywords" content="PKM, Obsidian">'
        .'<meta property="article:tag" content="obsidian">'
        .'<meta content=\'Second Brain\' property="article:tag">'
        .'<meta property=\'article:tag\' content="pkm">';

    expect(processHtml()->getHtmlKeywords($html))->toBe(['pkm', 'obsidian', 'second brain']);
});

test('a keyword longer than 100 characters is dropped, from both sources', function () {
    $long = str_repeat('a', 101);
    $max = str_repeat('b', 100);

    expect(processHtml()->getHtmlKeywords("<meta name=\"keywords\" content=\"{$long}, ok\"><meta property=\"article:tag\" content=\"{$long}\"><meta property=\"article:tag\" content=\"{$max}\">"))
        ->toBe(['ok', $max]);
});

test('keywords decode HTML entities and lowercase non-ASCII text', function () {
    $html = '<meta name="keywords" content="R&amp;D, Connaissance, WISSENSMANAGEMENT, ÉCOLE">';

    expect(processHtml()->getHtmlKeywords($html))->toBe(['r&d', 'connaissance', 'wissensmanagement', 'école']);
});

test('a page without keywords gives an empty list', function () {
    expect(processHtml()->getHtmlKeywords(htmlFixture('article')))->toBe([])
        ->and(processHtml()->getHtmlKeywords(MALFORMED_HTML))->toBe([]);
});
