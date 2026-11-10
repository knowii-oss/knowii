# 0031. A video link alone on its line becomes a click-to-load player

- Status: Accepted
- Date: 2026-11-10
- Issue: #1206
- Pull request: #1311

## Context

Members want to show YouTube and Vimeo videos in posts, comments and messages. Course lessons already embed videos through `App\Support\VideoEmbed` and the `VideoPlayer` component ([ADR 0029](0029-course-spaces.md)): only the two providers, the privacy-friendly player hosts, and nothing loaded before the member presses Play. Member content is Markdown rendered on the server with raw HTML escaped ([ADR 0013](0013-markdown-rendered-on-the-server.md)), so a member cannot write an iframe, and the strict Content Security Policy refuses inline styles and unknown frame hosts ([ADR 0007](0007-strict-content-security-policy.md)).

## Decision

1. Syntax: a bare YouTube or Vimeo address alone in its paragraph (blank lines around it, or alone in a list item or a quote) becomes a video. A video address inside a sentence or behind a label stays a link.
2. The server decides (`App\Support\MarkdownVideos\VideoEmbedExtension`, after parsing): it wraps the paragraph in `<div class="video-embed not-prose" data-video-provider data-video-id data-video-url data-video-embed-url>`. The attributes come from `VideoEmbed::parse`, never from the text. The link stays inside the block, for readers without JavaScript and for the emails and feeds that show the HTML.
3. `MarkdownContent` replaces each block with the `VideoPlayer` of ADR 0029 (a React root per block, unmounted when the HTML changes). No request goes to YouTube or Vimeo before the member presses Play, so the privacy policy needs no change.
4. The editor has an "Add a video" button: it checks the address (`parseVideoUrl` in `libs/common`, the same rules as `VideoEmbed::parse`) and inserts it alone on its line.
5. The Content Security Policy does not change: `frame-src` already allows the two player hosts.

## Consequences

1. Posts, comments, chat messages, direct messages and pages all get videos, because they share the renderer. The preview of the editor shows the player too (it uses the server renderer).
2. A new video provider needs a change in `VideoEmbed::parse`, `parseVideoUrl`, `frame-src` and the privacy policy (third-party content).
3. `MarkdownContent` is the only place that turns marked blocks into components. The same mechanism can serve other rich blocks later (galleries, #1207).

## Alternatives considered

1. Render the iframe on the server: the provider would receive a request from every reader on page load (tracking before consent), and an iframe in member HTML weakens ADR 0013.
2. A custom syntax (`::video[url]`): harder to write and to paste, and it shows as text in other Markdown readers. A bare URL degrades to a link everywhere.
3. Thumbnails stored on our upload disk: nicer, but they need a fetch per video and a storage policy. The player shows a neutral Play area instead. It can come later.
