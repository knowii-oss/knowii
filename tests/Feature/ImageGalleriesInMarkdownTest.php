<?php

use App\Rules\MaxMarkdownImages;
use App\Support\Markdown;

/*
 * Images that follow each other form a gallery (#1207, ADR 0039).
 */

function galleryImage(string $name): string
{
    return "![The $name](/storage/uploads/abc/2026/11/$name.webp)";
}

test('images that follow each other, one per line, form one gallery (#1207)', function () {
    $html = Markdown::render("Our trip:\n\n".galleryImage('harbour')."\n".galleryImage('market')."\n".galleryImage('beach')."\n\nThe end.");

    expect($html)->toContain('<p>Our trip:</p>')
        ->and($html)->toContain('<div class="image-gallery not-prose" data-image-count="3">')
        ->and($html)->toContain('<a class="image-gallery-item" href="/storage/uploads/abc/2026/11/harbour.webp"><img src="/storage/uploads/abc/2026/11/harbour.webp" alt="The harbour" loading="lazy" decoding="async" /></a>')
        ->and($html)->toContain('<p>The end.</p>')
        ->and(substr_count($html, 'class="image-gallery not-prose"'))->toBe(1)
        ->and(substr_count($html, '<img '))->toBe(3)
        // The paragraphs of the images are gone: the renderer writes the images itself
        ->and($html)->not->toContain('<p><img');
});

test('images separated only by blank lines are in the same gallery, and text between them starts a new one (#1207)', function () {
    $html = Markdown::render(galleryImage('one')."\n\n".galleryImage('two')."\n\nSome text.\n\n".galleryImage('three'));

    expect(substr_count($html, 'class="image-gallery not-prose"'))->toBe(2)
        ->and($html)->toContain('data-image-count="2"')
        ->and($html)->toContain('data-image-count="1"');
});

test('one image alone is a gallery of one, an image in a sentence or in a link is not (#1207)', function () {
    expect(Markdown::render(galleryImage('alone')))->toContain('<div class="image-gallery not-prose" data-image-count="1">');

    $inline = Markdown::render('Look at '.galleryImage('this').' picture.');
    expect($inline)->not->toContain('image-gallery')->and($inline)->toContain('<p>Look at <img');

    $linked = Markdown::render('[![Badge](/badge.webp)](https://example.com)');
    expect($linked)->not->toContain('image-gallery')->and($linked)->toContain('<a rel="nofollow noopener noreferrer" target="_blank" href="https://example.com"><img');
});

test('a gallery of more than four images says how many more there are (#1207)', function () {
    $markdown = implode("\n", array_map(static fn (int $n): string => galleryImage("image$n"), range(1, 6)));
    $html = Markdown::render($markdown);

    expect($html)->toContain('data-image-count="6"')
        ->and(substr_count($html, 'class="image-gallery-item"'))->toBe(6)
        ->and($html)->toContain('<img src="/storage/uploads/abc/2026/11/image4.webp" alt="The image4" loading="lazy" decoding="async" /><span class="image-gallery-more" aria-hidden="true">+2</span><span class="image-gallery-more-label">, and 2 more images</span></a>')
        ->and(substr_count($html, 'image-gallery-more"'))->toBe(1);

    $five = Markdown::render(implode("\n", array_map(static fn (int $n): string => galleryImage("image$n"), range(1, 5))));
    expect($five)->toContain('<span class="image-gallery-more-label">, and 1 more image</span>');

    $four = Markdown::render(implode("\n", array_map(static fn (int $n): string => galleryImage("image$n"), range(1, 4))));
    expect($four)->not->toContain('image-gallery-more');
});

test('a gallery escapes the text of members and drops unsafe addresses (#1207)', function () {
    $html = Markdown::render('![A "quoted" <b>alt</b> & more](/a.webp)'."\n".'![Bad](javascript:alert(1))');

    expect($html)->toContain('alt="A &quot;quoted&quot; &lt;b&gt;alt&lt;/b&gt; &amp; more"')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('<b>')
        ->and($html)->toContain('data-image-count="1"');
});

test('the image limit still counts the images of a gallery (#1207)', function () {
    $markdown = implode("\n", array_map(static fn (int $n): string => galleryImage("image$n"), range(1, 21)));

    expect(MaxMarkdownImages::count($markdown))->toBe(21);
});
