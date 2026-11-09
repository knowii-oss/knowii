# 0016. Resource images are stored by Knowii, never hotlinked

- Status: Accepted
- Date: 2026-11-09
- Issue: #1063

## Context

Resource cards need the cover image and the icon of the site (#1063). The capture knows their URLs on third-party sites, and kept the full cover image as base64 in the database (a large field that lists never carry, rule Architecture 6).

Showing the third-party URLs directly (hotlinking) breaks the strict Content Security Policy: `img-src` lists explicit origins only, never a scheme ([ADR 0007](0007-strict-content-security-policy.md)), and the origins of the curated sites are unknown in advance. It also sends a request from the browser of each member to each curated site (privacy: IP address, referrer), and the image can change or disappear.

## Decision

1. At capture, the server downloads the cover image and the icon of the site once, through `SafeUrl` (#1231): public addresses only, 10 MB at most.
2. `ImageProcessor` re-encodes them (WebP when available, no metadata, no active content such as SVG scripts): the cover at 800 pixels at most on its longest side, the icon at 64 pixels. ICO and SVG icons are skipped: GD cannot read them.
3. The files are stored on the uploads disk at `resources/{resource cuid}/cover.webp` and `favicon.webp`; `resources.cover_image_path` and `favicon_path` hold the paths. The API returns `cover_image_thumbnail_url` and `favicon_url`, also in lists (they are small URLs, not image data).
4. Images are a bonus: a missing, refused or unreadable image never stops a capture.
5. Pages show the images with `alt=""`: they are decorative, the name of the resource says what it is.

## Consequences

1. No CSP change, no request from the browsers of the members to the curated sites.
2. The uploads disk holds one folder per captured resource. Deleting a resource (not built yet) must delete its folder.
3. `cover_image_base64` stays for now; #1230 (the way forward for content capture) decides whether the resource page keeps it.
4. Resources captured before this change have no stored images until they are captured again.

## Alternatives considered

1. Hotlink the third-party URLs: breaks the CSP (a scheme-wide `https:` in `img-src` is forbidden) and leaks the browsing of members.
2. An image proxy route that fetches on each view: a request to the curated site at each page view, and a generic fetcher exposed to the web.
3. Base64 thumbnails in the API: larger responses and no browser cache.
