# 0018. Content capture: queued server capture of links, full captures from a browser extension

- Status: Accepted
- Date: 2026-11-09
- Issue: #1230 (steps: #1244, #1245, #1246, #1247, #1248; extension: #652)

## Context

A resources space ([ADR 0015](0015-resources-space-type.md)) holds community links. The platform must also curate content: Knowii keeps a readable copy of an article, like Obsidian Web Clipper.

The capture of today (`CreateTextResource`) runs inside the API request. It follows redirects, gets the page through Browserless (a headless Chromium container), extracts the metadata (`ProcessHtml`), the readable content (readability) and converts it to Markdown (`ResourceTextArticle`). It has these problems:

1. Browserless is slow (several seconds in the request) and gives up on pages that need more than 10 seconds. Many sites block or degrade it (bot detection, consent walls, paywalls), and it cannot capture pages behind a sign-in.
2. No queue, no retry, no progress for the member.
3. One resource type (`textArticle`). Video and streaming sites are refused with a hard-coded domain list.
4. `Resource` is global (one per URL), so a capture is shared by every community. Content captured from the session of a member (paywall, private page) would leak to other communities. Showing the full text of a third-party article to anyone is also republishing.
5. The cover image stays as base64 in the database, next to the stored images of [ADR 0016](0016-resource-images-stored-by-knowii.md).

## Decision

1. Two ways to capture, built in two phases:
   1. Phase 1: the server captures every link in a queued job (`CaptureCommunityResource`, on the queue `captures`, read by a worker of its own: see point 11).
   2. Phase 2: a browser extension sends the page that the member sees (rendered, signed in) as Markdown with its metadata. It needs personal API tokens (#689).
2. Browserless is removed: the container, `config/browserless.php`, its environment variables and the code that calls it. The server does a plain HTTP fetch through `SafeUrl`. A page that gives no readable content stays a link with its metadata. The extension covers the pages that a plain fetch cannot read.
3. Adding a resource is fast. The request checks the input, creates the community resource with the capture status `pending` and a name from the member or from the URL, and returns it (201). The job fetches the page, the metadata, the type and the content, then broadcasts `CommunityResourceUpdated` on the space channel. The capture status is `pending`, `capturing`, `captured` or `failed`. The job retries three times with a backoff. After the last failure, the status is `failed` with a reason, and the curator or a moderator can start the capture again (`POST .../resources/{resource}/capture`).
4. Resource types are a fixed enum: `article`, `video`, `audio` (podcasts), `book`, `paper`, `tool` (websites and software), `other`. The job detects the type from Open Graph (`og:type`), JSON-LD (`@type`), the domain (for example YouTube and Vimeo are videos, arXiv and DOI links are papers) and the content type of the response (a PDF is a paper). The curator and the members who manage the space can change it. Video and streaming links are accepted as `video` resources. The domain list that refused them goes away.
5. Only `article` resources get captured content. The other types are links with metadata.
6. The data splits in two:
   1. `resources` (global, one per URL) keeps the shared metadata of the page: URL, detected type, title, description, keywords, language, dates, stored cover and icon (ADR 0016).
   2. The community resource owns what the community decides and what it captured: name, description, type, level, curator, capture status. The captured content lives in `resource_captures`, one row per community resource: Markdown, word count, reading time, source (`server` or `extension`), capture date, failure reason.
7. Captured content is for the members of the community only. Lists never carry it (large field, rule Architecture 6). When public spaces exist (M2), a visitor who is not a member sees the excerpt and the link, never the captured content.
8. Markdown is the only stored form of captured content. The page shows it through the renderer of [ADR 0013](0013-markdown-rendered-on-the-server.md) (raw HTML escaped). The HTML of the page is not stored.
9. The images of a captured article are downloaded through `SafeUrl`, re-encoded and stored by Knowii (ADR 0016) under `community-resources/{cuid}/`, at most 20 images of 10 MB each. The Markdown points to the stored copies. An image that cannot be stored is dropped from the Markdown, with its alternative text kept as text.
10. The extension (phase 2) is one WebExtension (Manifest V3) for Chrome and Firefox, in the Nx monorepo under `apps/`. It extracts the content in the browser with Defuddle (MIT, the extraction library of Obsidian Web Clipper). It signs in with a personal API token that can only create resources. The capture endpoint accepts Markdown of 1 MB at most. The server treats it as untrusted member content: it renders it with the ADR 0013 renderer, and fetches its images through `SafeUrl` like a server capture. Annotations (#341) come later.
11. Each fetch of a capture (the page, the cover, the icon, the images of an article) is bounded:
    1. Redirects: the HTTP client follows none by itself. `FetchUrl::send()` follows at most 5, and checks each address with `SafeUrl` and pins its host and port to the checked address (`CURLOPT_RESOLVE`) before its request, also for a redirect to the same host on another port or scheme. curl refuses a connection to an address that is not public (`CURLOPT_PREREQFUNCTION`), as a second net.
    2. Addresses: `SafeUrl` refuses every address that is not public: the private, loopback, link-local, reserved and shared ranges, NAT64 (`64:ff9b::/96`, `64:ff9b:1::/48`), IPv4-compatible IPv6 (`::/96`), site-local IPv6 (`fec0::/10`) and multicast (`ff00::/8`, `224.0.0.0/4`). Every refused host gets one answer, "This address cannot be fetched."; a refused port has its own.
    3. Size: the decoded body of each answer is limited to 10 MB (`MAX_FETCH_SIZE_BYTES`) while the client writes it, so a compressed answer cannot pass the limit. A larger page fails the capture at once.
    4. Time: a connection has 5 seconds, the page 15 seconds, and all the images of one capture share 45 seconds (`CAPTURE_IMAGES_SECONDS`): an image after that is not fetched and becomes its alternative text. The job has 80 seconds (below the `retry_after` of the queue, 90 seconds). A page that is too large or too slow, and a job that runs out of time, are not tried again.
    5. Work: the page is parsed once for all its metadata. The stored article has at most 200 000 characters (`MAX_CAPTURE_MARKDOWN_LENGTH`): a longer one is cut at its last paragraph before the limit, and the link of the resource gives the rest.
    6. Queue: captures run on the queue `captures`, read by a worker of its own (`queue-captures` in `compose.production.yaml`, `queue_worker_captures` in the development image), so that a long list of captures never delays a live update, a notification or an account mail.

## Consequences

1. The legacy capture code goes away (no backward compatibility, there is no production instance): `CreateTextResource`, `ResourceTextArticle` and its table, `resources.cover_image_base64`, `resources.cover_image_url`, the stored HTML, Browserless and the video domain list.
2. The VPS runs one container less, and the capture no longer blocks a request.
3. The front-end shows the capture status on the resource card and page, and updates it from the space channel.
4. A page that only a browser can read is a link until a member captures it with the extension.
5. Two communities that curate the same URL capture it twice. This is the price of privacy: one community never sees what another captured.
6. Deleting a community resource deletes its `community-resources/{cuid}/` folder.
7. The extension is a second front-end to release (browser stores) and needs API tokens first.

## Alternatives considered

1. Server capture only: pages behind a sign-in, consent walls and bot detection stay out of reach.
2. Extension only: links shared from the web app or the API would have no metadata and no content.
3. Keep Browserless as an optional fallback: one more container and code path to maintain, for pages that bot detection blocks anyway.
4. Keep the capture global (one per URL): leaks content captured from a member's session to other communities, and republishes third-party text.
5. Store the captured content but never show it (search and AI only): members lose the reading view that makes curation useful.
6. Types configured per community: a settings screen and free-form types that no detection or icon can rely on.
