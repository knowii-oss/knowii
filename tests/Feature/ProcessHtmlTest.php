
test('getHtmlFaviconUrl keeps the largest PNG icon and skips ICO and SVG (#1063)', function () {
    $html = '<html><head>'
        .'<link rel="icon" href="/favicon.ico">'
        .'<link rel="icon" type="image/svg+xml" href="/icon.svg">'
        .'<link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png">'
        .'<link rel="apple-touch-icon" sizes="180x180" href="https://cdn.example.com/touch.png">'
        .'<link rel="stylesheet" href="/style.css">'
        .'</head><body></body></html>';

    expect(processHtml()->getHtmlFaviconUrl($html, 'https://example.com/blog/post'))->toBe('https://cdn.example.com/touch.png');
});

test('getHtmlFaviconUrl resolves relative icons and falls back to /favicon.png (#1063)', function () {
    expect(processHtml()->getHtmlFaviconUrl('<link rel="shortcut icon" href="img/icon.png">', 'https://example.com/blog/post'))
        ->toBe('https://example.com/blog/img/icon.png')
        ->and(processHtml()->getHtmlFaviconUrl('<html><head><link rel="icon" href="/favicon.ico"></head></html>', 'https://example.com/a'))
        ->toBe('https://example.com/favicon.png')
        ->and(processHtml()->getHtmlFaviconUrl('', 'https://example.com/a'))
        ->toBe('https://example.com/favicon.png');
});
