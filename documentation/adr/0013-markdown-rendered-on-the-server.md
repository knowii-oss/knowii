# 0013. Member content is Markdown, rendered to safe HTML on the server

- Status: Accepted
- Date: 2026-11-09
- Issue: #1174

## Context

Members write posts, comments, chat messages, direct messages and pages. The content must support basic formatting (bold, links, lists, code, tables), must never let a member inject HTML or scripts into another member's page, and must look the same in the editor preview, on the page, in emails and in the API.

## Decision

1. Members write **Markdown**. The source is stored as written (`body`), so it can be edited again.
2. The server renders it once, when it is saved, with `App\Support\Markdown` (league/commonmark: CommonMark and GitHub Flavored Markdown), and stores the HTML (`body_html`):
   - raw HTML in the Markdown is **escaped**, never passed through (`html_input: escape`),
   - unsafe links (`javascript:`, `vbscript:`, `file:`) are removed (`allow_unsafe_links: false`),
   - links to other sites open in a new tab with `rel="nofollow noopener noreferrer"`.
3. The front-end shows `body_html` with `<MarkdownContent>` (the only `dangerouslySetInnerHTML` of the app) and never renders Markdown itself.
4. The editor preview calls `POST /api/v1/markdown/preview`, which uses the same renderer, so the preview equals the result.
5. Later extensions (mentions #1177, image uploads #1178) are CommonMark extensions in the same renderer.

## Consequences

1. Changing the renderer does not change stored HTML: re-render the stored Markdown with a command (to write when needed).
2. The Content Security Policy stays the second line of defense ([ADR 0007](0007-strict-content-security-policy.md)): even rendered HTML cannot load scripts or styles from other origins.
3. Tests cover the escaping of scripts, event handlers, iframes, styles and unsafe links (`tests/Unit/MarkdownTest.php`).

## Alternatives considered

1. Render Markdown in the browser (react-markdown): every client (emails, API consumers, the MCP server) would need the same renderer and the same safety rules.
2. A rich-text (WYSIWYG) editor storing HTML: needs an HTML sanitizer on every input, and the stored format is harder to edit by hand and to export.
3. Render on each read: costs CPU on every page view for no gain.
