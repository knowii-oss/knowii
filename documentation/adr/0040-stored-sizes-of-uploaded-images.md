# 0040. Uploaded images have stored smaller sizes, and the HTML uses srcset

- Status: Accepted
- Date: 2026-11-15
- Issue: #1207
- Pull request: #1384

## Context

An uploaded image is re-encoded to WebP and scaled down to 2000 px at most (#1178). A grid tile or a phone screen still downloads that whole file. The browser also does not know the size of an image before it loads, so the page can move ([ADR 0039](0039-images-that-follow-each-other-form-a-gallery.md) left this to this step).

Member content is Markdown rendered on the server, and the HTML is stored ([ADR 0013](0013-markdown-rendered-on-the-server.md)). The strict Content Security Policy refuses inline styles ([ADR 0007](0007-strict-content-security-policy.md)), so the size must come from HTML attributes, not from `style`. Uploads are deleted by the daily prune and with their community ([ADR 0030](0030-retention-periods-enforced-by-scheduled-tasks.md)): a new file must go with its upload.

## Decision

1. Sizes: the file at upload stays the original (2000 px at most). The server also stores WebP versions that are 640 and 1280 px wide (`Constants::UPLOAD_IMAGE_SIZES`), with the same quality. Only the widths that are smaller than the original are stored: an image is never scaled up, and a small image has no version. An animated image is flattened like its original (the processor does not keep animations).
2. Storage: next to the original, on the same disk, in the same folder: `<cuid>.webp`, `<cuid>-640.webp`, `<cuid>-1280.webp`. The `uploads` table has a JSON column `sizes`: the widths that exist, for example `[640, 1280]`. An empty list means that the image is small. Null means that the versions are not built yet (an upload of before this decision). `Upload::sizePath()` and `Upload::sizeUrl()` give the path and the URL of a size. `UploadReferences` finds the cuid in an address with a size suffix.
3. One deletion: `Upload::deleteFiles()` (backed by `App\Support\UploadFiles`) deletes the original and its versions. The prune of unused uploads and the community deletion (`ContentFiles`, also used by the account deletion) both use it. It also tries the standard widths when `sizes` is null or incomplete, so a file written before a crash is not left behind.
4. Rendering: the Markdown renderer gives an `<img>` that is an upload of this instance `width`, `height`, `srcset` and `sizes` (`App\Support\MarkdownUploads`). After parsing, one query finds the uploads of all the images of the text, by the cuid in the address. The attributes are added only when the address is the stored address of the file (same path, on this instance or on the host of the storage, no query), so a member cannot borrow the attributes of an upload for another address. `srcset` lists the sizes that exist and the original with its real width. `sizes` is `(min-width: 42rem) 21rem, 50vw` for a tile of a gallery of two images or more, and `(min-width: 48rem) 48rem, 100vw` for one image (a gallery of one, or an image inside a sentence). An image without a smaller version gets `width` and `height` only. An image that is not an upload stays as it is. The link of a gallery item and the viewer keep the original.
5. The CSS of a single image (`.image-gallery[data-image-count='1']`) sets no width: it comes from the `width` attribute, capped by `max-w-full`, and `h-auto` follows the ratio. The browser reserves the place before the image loads, and the image is not distorted.
6. Existing data: `php artisan uploads:build-sizes` builds the versions of the uploads whose `sizes` is null, in chunks. `php artisan content:render-again` renders `body_html` (and the quiz `prompt_html` and `explanation_html`) again from the Markdown, in chunks. Both are actions behind contracts, safe to run again, and have `--dry-run`. A file that is missing or unreadable is reported and skipped, never deleted. The second command writes with the query builder: it creates no mention, sends no notification or email, dispatches no event, and keeps `updated_at` and `edited_at`. It renders mentions as they are rendered today.

## Consequences

1. Phones and grid tiles download a smaller file. The storage grows by a part of the size of each large upload (a version is smaller than its original).
2. The stored HTML holds addresses of the versions. If the storage changes (another disk or bucket), the content must be rendered again with `content:render-again`, like for the original.
3. The operator who upgrades runs `php artisan migrate`, then `uploads:build-sizes`, then `content:render-again` (`SELF_HOSTING.md`). Until then, old content works as before. Run `content:render-again` after `uploads:build-sizes`: the HTML needs the list of sizes.
4. A new place that deletes an upload must call `Upload::deleteFiles()`, never delete `path` alone.
5. The community export copies the originals only. The versions can be built again from them.
6. `MaxMarkdownImages` still counts the `<img>` elements. It renders without the query for the uploads, because a count does not need the sizes.
7. The images of a text are found by their address. A text that holds only the address of a size file (`<cuid>-640.webp`) shows it as a plain image.

## Alternatives considered

1. A route that resizes on the fly: it needs a cache, and an image service or CPU work on each first view. It does not fit the storage on an S3 bucket behind a CDN.
2. Sizes built by a queued job after the upload: the post can be saved before the files exist, and the HTML would point to missing files. Building two small versions in the request costs little.
3. A 2000 px entry in `sizes`: the original already is that file, and listing it twice gives no benefit.
4. Looking up the uploads at each render of a page: the HTML is stored, so the lookup happens once, when the text is written, and again only when the content is rendered again.
5. A query per image: a text can hold 20 images. One query per text is enough.
