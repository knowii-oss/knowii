<?php

use App\Support\Markdown;

/**
 * Videos in posts, comments and messages (#1206, ADR 0031): a YouTube or Vimeo link alone on its line becomes a block
 * that the browser turns into the click-to-load player of ADR 0029.
 */
dataset('video links', [
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'vimeo' => ['https://vimeo.com/76979871', 'vimeo', '76979871', 'https://player.vimeo.com/video/76979871?dnt=1'],
]);

test('a video link alone on its line becomes a video block with the link inside', function (string $url, string $provider, string $id, string $embedUrl) {
    $html = Markdown::render("Watch this:\n\n{$url}\n\nGreat, no?");

    expect($html)->toContain('<div class="video-embed not-prose" data-video-provider="'.$provider.'" data-video-id="'.$id.'"')
        ->toContain('data-video-embed-url="'.e($embedUrl).'"')
        ->toContain('data-video-url="'.e($url).'"')
        // The link stays, for readers without JavaScript
        ->toContain('<a rel="nofollow noopener noreferrer" target="_blank" href="'.e($url).'">'.e($url).'</a>')
        ->toContain('<p>Watch this:</p>')
        ->toContain('<p>Great, no?</p>');
})->with('video links');

test('an angle-bracket autolink and a link whose text is the address also become videos', function () {
    expect(Markdown::render('<https://youtu.be/dQw4w9WgXcQ>'))->toContain('data-video-provider="youtube"')
        ->and(Markdown::render('[https://vimeo.com/76979871](https://vimeo.com/76979871)'))->toContain('data-video-provider="vimeo"');
});

test('a video link inside a sentence, with a label, or of another host stays a link', function (string $markdown) {
    expect(Markdown::render($markdown))->not->toContain('video-embed')->toContain('<a ');
})->with([
    'in a sentence' => ['See https://youtu.be/dQw4w9WgXcQ for the demo'],
    'with a label' => ['[the demo](https://youtu.be/dQw4w9WgXcQ)'],
    'other host' => ['https://example.com/watch?v=dQw4w9WgXcQ'],
    'invalid id' => ['https://youtu.be/short'],
    'two links' => ['https://youtu.be/dQw4w9WgXcQ https://vimeo.com/76979871'],
]);

test('a video link in a list or a quote becomes a video too, and raw HTML stays escaped', function () {
    expect(Markdown::render('- https://youtu.be/dQw4w9WgXcQ'))->toContain('data-video-provider="youtube"')
        ->and(Markdown::render('> https://vimeo.com/76979871'))->toContain('data-video-provider="vimeo"')
        ->and(Markdown::render('<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe>'))
        ->not->toContain('<iframe')->toContain('&lt;iframe');
});

test('the attributes come from the parsed video, never from the text of the member', function () {
    $html = Markdown::render('https://www.youtube.com/watch?v=dQw4w9WgXcQ&x="><script>alert(1)</script>');

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('"><');
});
