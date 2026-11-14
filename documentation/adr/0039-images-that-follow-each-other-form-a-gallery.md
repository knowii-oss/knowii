# 0039. Images that follow each other form a gallery

- Status: Accepted
- Date: 2026-11-14
- Issue: #1207
- Pull request: #1378

## Context

Members can add up to 10 images at a time to a post, a comment, a message or a page (#1364), and the editor writes them one per line. In the text, each image was shown at its full size, one under the other: ten images made a very long post, and there was no way to look at one image on the whole screen.

Member content is Markdown rendered on the server with raw HTML escaped ([ADR 0013](0013-markdown-rendered-on-the-server.md)). The strict Content Security Policy refuses inline styles ([ADR 0007](0007-strict-content-security-policy.md)). `MarkdownContent` already turns the video blocks that the server marks into players ([ADR 0031](0031-videos-in-member-content.md)).

## Decision

1. Syntax: no new syntax. Images that follow each other with no text between them (one per line, or separated only by blank lines) form a gallery. One image alone in its paragraph is a gallery of one. An image inside a sentence and an image that is a link stay as they are. There is no "gallery post" type: any post, comment, chat message, direct message or page can hold galleries.
2. The server decides (`App\Support\MarkdownGalleries\ImageGalleryExtension`, after parsing): it replaces the paragraphs of images with `<div class="image-gallery not-prose" data-image-count="N">`. Inside, each image is a link to its file: `<a class="image-gallery-item" href="..."><img src alt loading="lazy"></a>`. The renderer writes the images itself, with the same rule against unsafe addresses as the image renderer of CommonMark, and escaped attributes.
3. The grid is CSS only (`styles.css`), chosen from `data-image-count`: one image keeps its own shape, two images are two squares, three images are one large image and two small ones, four or more are a 2 x 2 grid. From the fifth image on, the items are hidden by the CSS, and the fourth one shows "+N" (with a text for screen readers).
4. The viewer (`ImageViewer`, on the Headless UI Dialog): a click or Enter on an image opens it on the whole screen, with its alternative text as the caption and its position ("3 / 7"). Previous and next work with the buttons, the arrow keys, Home, End and a swipe. "Open the original" opens the file in a new tab. There is no download link and no custom zoom. `MarkdownContent` listens to the clicks on its content and holds the one viewer: there is no React root per gallery.
5. The caption is the alternative text, which the editor requires. There is no second text per image.

## Consequences

1. Posts, comments, chat messages, direct messages and pages all get galleries, because they share the renderer and `MarkdownContent`. The preview of the editor shows them too.
2. Without JavaScript, in an email or in a feed, a gallery is a list of images that link to their files.
3. The grid does not depend on the size of the images (each tile has a fixed shape and crops its image), so the layout does not move when the images load. A single image still takes its own height: the stored width and height come with the sizes of the third step of #1207.
4. The HTML of existing content is stored: an old post shows its images as before until someone edits it. The third step of #1207 renders the stored HTML again (it adds `srcset`), which also brings the galleries to the old content.
5. `MaxMarkdownImages` still counts the `<img>` elements of the rendered text: a gallery does not change the limit of 20 images.
6. A rich block in member content now has two patterns: a block that needs a component of its own gets a React root (videos); a block that only needs a shared dialog is plain HTML with CSS, and `MarkdownContent` handles its clicks (galleries). Prefer the second one when it is enough.

## Alternatives considered

1. A React component per gallery, mounted in a root like the video player: the server HTML (images one under the other) would show first and jump to the grid when the script runs, and each gallery would cost a React root.
2. A custom syntax (`:::gallery`): harder to write, and it shows as text in other Markdown readers. Images that follow each other are what the editor already writes.
3. A JavaScript masonry layout from the real size of each image: it needs the sizes before the images load, and inline styles, which the CSP refuses.
4. A separate visible caption per image: a second text to keep in step with the alternative text. Sébastien chose the alternative text only.
