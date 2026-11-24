# 0073. Every API route has a limit of requests, and a request body has a size limit

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Uses: [0011](0011-rest-api-design-guide.md) (the errors of the API), [0022](0022-api-token-abilities.md) (the abilities of a token), [0044](0044-public-spaces-for-visitors.md) (the visitors), [0018](0018-content-capture.md) (the captures)

## Context

Some routes of the API had a limiter of their own (the sign-in, the posts, the comments, the uploads, the captures), and the reads of a visitor had `public-reads`. Every other route had no limit: an account or a token could call it at any rate, and the pages that visitors read had no limit either. Every route also read a body of the size of the largest upload, and two lists (the pages of a space, the comments of an item) had no page size and their items no cap.

## Decision

1. **A default limiter for the whole `api` group** (`throttle:api`, `AppServiceProvider`):
   1. Per account and per minute: 600 reads (`GET`, `HEAD`) and 120 writes (every other method), counted apart (`Constants::API_READS_PER_MINUTE`, `API_WRITES_PER_MINUTE`). A token shares the count of its account. A route that only marks something as read or seen by the member (`ApiRoutes::READ_MARKERS`: the read markers of a chat and of a conversation, the visit of a space, the notifications seen, read, unread and caught up) counts as a read: the pages send it by themselves as the member reads, so a busy chat never takes the writes of its readers.
   2. Per IP address for a request without an account: 60 a minute for every route of the API (`Constants::PUBLIC_READS_PER_MINUTE`, the number of `public-reads`).
   3. A route with a limiter of its own keeps it, and the default counts too: the stricter one answers first.
   4. The order: the priority list runs every limiter after the sign-in check and after the token ability (`RequireTokenAbility`), so a token without the ability gets 403 and uses no try of its account.
2. **The pages that visitors read** (the group `auth.optional` of `routes/web.php`) have the limiter `public-pages`: 120 a minute per IP address for a request without an account (`Constants::PUBLIC_PAGES_PER_MINUTE`). A signed-in request is not counted there.
3. **Writes count in the limiter of their creation**: adding a resource counts in `captures` (it starts a capture); the edit of a chat message and of a direct message counts in `messages`, of a post in `posts`, of a comment (of a post, a resource or an event) in `comments`. The creation and the edit of a page have no limiter of their own: the default limiter covers them.
4. **A request body is limited to 1 MB** (`Constants::MAX_REQUEST_BODY_BYTES`), checked before it is read: `public/index.php` answers 413 from `Content-Length` before the framework makes the request (which reads and decodes a JSON body at once), reads a body without a length only up to the limit, and answers 411 to a body in chunks without a length (`LimitRequestBodySize::answerBeforeTheFramework()`). The global middleware `LimitRequestBodySize`, before the session, checks the same as a second net. Only a form with files (`multipart/form-data`) on an upload route of the API (`EnsureRequestIsJson::isUploadRoute()`: the images of a community, the files of a course, the avatar, the file of invitations) may be larger: the route checks its files itself. PHP reads a form body before `public/index.php` runs, so the proxy limits the body too: 1 MB, and the size of the largest upload on the upload routes (`SELF_HOSTING.md`).
5. **Lists with a cap**: the lists of a reorder have the cap of their items (`max:`: 50 space groups, 200 spaces, 500 pages, 20 materials). A space holds at most 500 pages (`Constants::MAX_PAGES_PER_SPACE`). The page list of the API is paged (`limit`, `offset`, default and largest 500, an offset over 500 counts as 500) and, as the page list of the space page, reads no text of a page. The caps of the spaces, the space groups and the tags stay as they are: the default limiter covers a loop of creations and deletions.
6. **The comments of a post, a resource or an event are paged** (`limit` and `offset`, `CommentThread::page()`): one page holds at most 100 comments, the replies counted (`Constants::COMMENTS_PER_PAGE`, the default and the largest `limit`), in the order of the page: each thread, its top-level comment then its replies oldest first, then the next thread. A page that starts inside a thread has its top-level comment again, with the next replies only; `metadata.totalCount` counts every comment of the item, as before, and `metadata.pagination` is the pagination of the guide (ADR 0011). The page of the item gets the first page and `commentsPagination`; "Load more comments" adds the next page to the threads, a link to a comment of a later page loads the pages up to it (its thread opens if it was collapsed), and a live update loads again the pages that the page shows.
7. The OpenAPI document gives every operation its 429, and every write but an upload its 413.
8. **An image has at most 25 megapixels** (`Constants::MAX_UPLOAD_IMAGE_PIXELS`): every image that the application decodes (the avatar, the images that members upload to a community, the images of a capture) goes through `ImageProcessor`, which reads the width and the height from the header of the file and refuses a larger one before any decode ("This image is too large: at most 25 megapixels."). The decode of the largest image and its copies stay within the memory of one request.

## Consequences

1. The first-party pages stay far below the numbers: an open community page with chat and a long post makes about 10 calls when it opens, then about 2 a minute (the online members, when the member hides the online status) plus 1 to 3 reads per live event (a message, a comment, a reaction; an event of the comments loads again each loaded page of comments), and 1 read marker per new chat message, which counts as a read. A busy chat of 120 new messages a minute (four members at the limit of the messages) makes about 240 reads a minute through the live events, 300 with 60 reactions: half of the 600.
2. A test that sends more requests than a limit in one test clears or raises the limiter, as the tests of the named limiters do. The test environment keeps every limiter on.
3. Every limiter per IP address depends on the address that the proxy gives (`TRUSTED_PROXIES` and `X-Forwarded-For`, `SELF_HOSTING.md`).
4. The delete dialog of a post with more comments than one page does not know whether the comments that are not loaded are of other people: it says both outcomes, and the page asks the server for the post after the delete.

## Alternatives considered

1. A limit in the proxy only: it does not know the account, and every operator must write it.
2. One count for reads and writes: a page that reads a lot would leave no room for the writes of the member.
3. A body limit in the proxy only: the upload routes need a larger one, and the proxy does not know the routes of the application.
