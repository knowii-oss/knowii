# Front-end design

The front-end is a React 19 application rendered through Inertia.js 3. Laravel decides which page to show and with which initial data. React renders it, and talks to the REST API for every change.

## Pages

1. A page is a component in `apps/knowii/src/Pages/<Area>/<Name>.tsx`, rendered by an Inertia controller (`app/Http/Controllers/Inertia`) or by `Route::inertia(...)` in `routes/web.php` for static pages.
2. The controller passes the initial data as props. The props interface of the page and the array in the controller must stay aligned (both carry a `WARNING` comment).
3. Every page renders inside `<AppLayout>`, which gives the header, the breadcrumb, the page actions and the footer. Community pages also render `<CommunityShell>` (`Components/Spaces`): the space navigation in a sidebar on desktop and in a drawer on mobile, reloaded when the space tree changes.
4. Further data and every change go through the Knowii API client, never through Inertia form posts. The API stays complete.
5. Links use Inertia `<Link>`. A link that changes the URL without a reload sets `preserveState={true}`.
6. A visitor (nobody signed in, `page.props.auth.user` is null) reads the public spaces through four pages: the space, the post, the page or lesson and the resource ([ADR 0044](../adr/0044-public-spaces-for-visitors.md)). `<AppLayout>` gives them "Sign in" and "Sign up" in place of the user menu and no dashboard link, and tells the components below it through `VisitorProvider` (`Components/Reader`). `<SignInPrompt>` ("Sign in to reply", "Sign in to post", "Sign in to download") takes the place of a composer or of the download. Reactions are counts, and no member name is a link. The props are cut by the server: the client never hides what it received. The public page of a community (#1485, `Pages/Communities/Public.tsx`) is the fifth page: a visitor and a signed-in outsider get it instead of the community home, with the name, the description, the navigation of the public spaces and the newest public posts, and a place for the "Join" button (#1486). In the breadcrumb of a visitor "Home" goes to `/` (the layout does it), and the community to this page. A public space shows no notice to its members (#1493); the texts of the space form for a manager are in `Components/Spaces/PublicSpaceNotice.tsx`.

## Rendering errors

A component that throws while rendering does not blank the page. `<ErrorBoundary>` (`@/Components/ui`) catches the error, logs it to the console and shows a fallback:

1. `<AppLayout>` wraps the page content: the header, the navigation and the footer stay, the content shows "Something went wrong on this page" with "Try again" and "Reload the page". An Inertia visit to another URL clears the error (`resetKey={page.url}`).
2. `main.tsx` wraps the whole application, for an error in the layout itself.
3. Member content uses the block variant, so one broken item does not hide the others: each post card, the post body, each comment (`<ErrorBoundary variant="block" label="this post">`). Wrap new member content the same way (chat messages, pages, lessons).

## Global data

1. Data that every page needs (the current user, the communities of the user) comes from `HandleInertiaRequests`.
2. Client-side state that every page needs lives in the React context of `libs/common/src/lib/contexts/app-context.tsx`. Example: `showToast()`. The provider keeps the toasts, and the `<Toaster>` (mounted once in `main.tsx`) shows them.

## UI components

1. The building blocks live in `apps/knowii/src/Components/ui` and are imported from `@/Components/ui`: buttons, form controls, dialog, drawer, menu, select, cards, avatar, toasts and more ([ADR 0003](../adr/0003-own-ui-components.md)).
2. Use them instead of styling raw elements. A new building block goes there, with a Vitest test (`*.spec.tsx`), keyboard support and a visible focus ring.
3. Headless UI is used only for the accessible interactive parts (dialog, listbox, menu). Everything else is plain React and Tailwind CSS.
4. Feature components (a post card, a space navigation) live in `apps/knowii/src/Components/<Feature>/` and compose the building blocks.

Member content: `MarkdownContent` shows the HTML that the server renders. It replaces each `div.video-embed` block (a YouTube or Vimeo link alone on its line, [ADR 0031](../adr/0031-videos-in-member-content.md)) with the click-to-load `VideoPlayer`. The `MarkdownEditor` has an "Add a video" button that checks the address with `parseVideoUrl` (`libs/common`).

Images in the editor (#1207): the image button, a paste or a drop takes up to 10 images at a time. `ImageInsertPanel` lists them with a preview, a required description (the alt text), the state of each upload, and buttons to reorder and remove. "Insert the images" uploads them one after the other and writes them one per line; nothing goes in the text before every upload worked, and a second try uploads only the images that failed.

Galleries ([ADR 0039](../adr/0039-images-that-follow-each-other-form-a-gallery.md)): the server writes the images that follow each other as `div.image-gallery`, with each image as a link to its file. The grid is CSS only (`.image-gallery` in `styles.css`, chosen from `data-image-count`), so there is no layout jump when the script starts. `MarkdownContent` listens to the clicks on its content and opens `ImageViewer` (Headless UI Dialog): the image on the whole screen, its alternative text as the caption, previous and next with the buttons, the arrow keys, Home, End and a swipe, and "Open the original". A click with a modifier key stays a normal link. A rich block that only needs a shared dialog follows this pattern; a block that needs its own component gets a React root, like the videos.

## Themes and tokens

1. Colors, fonts and breakpoints are the Knowii brand tokens defined with `@theme` in `apps/knowii/src/styles.css`. The default Tailwind palette is disabled. The Noto Sans font comes from the `@fontsource-variable/noto-sans` package: `main.tsx` imports its CSS and Vite bundles the WOFF2 files, so the font is served from the Knowii origin. Never load a font or a stylesheet from a font service (#1341, GDPR): the CSP allows none.
2. Two themes: dark (default) and light, selected with `data-theme` on `<html>`. The inline script in `app.blade.php` applies the saved choice before the first paint. `useTheme()` and `<ThemeToggle>` change it.
3. Tokens change with the theme: never hardcode `white` or `black`. Check every screen in both themes.

## Content Security Policy

The policy has no `'unsafe-inline'` for scripts or styles ([ADR 0007](../adr/0007-strict-content-security-policy.md)):

1. Set styles with classes, or with React `style` props (they go through the CSSOM, which the policy allows).
2. Never call `setAttribute('style', ...)`, and never add a `<style>` element without the nonce from `<meta property="csp-nonce">`.
3. Third-party scripts are added by our own code (`'strict-dynamic'`). Avoid embeds that inject styles.
4. Images, media, frames and connections go only to the origins listed in `config/secure-headers.php`.

## Data and validation

1. Zod schemas in `libs/common/src/lib/types` describe the entities (`community.schema.ts`) and in `libs/common/src/lib/api` the requests and responses (`create-space-request.schema.ts`).
2. Forms use react-hook-form with the Zod resolver. The limits come from `libs/common/src/lib/constants.ts`, aligned with `App\Constants`.
3. The Knowii API client (`libs/common/src/lib/api/knowii-api-client.ts`) is the only place that calls `fetch` for the API. API paths are constants in `constants.ts`. New methods use `apiRequest()` (`api-result.ts`): it parses `data` with a Zod schema and returns an `ApiResult` (`ok`, `data` or `message` and field `errors`), never throws.

## Real time

`useSocket` (`libs/common/src/lib/hooks/use-socket.ts`) wraps Laravel Echo. A page names a channel and the events it wants, and gets typed payloads. The event names match `broadcastAs()` on the back-end. Each hook removes only its own listeners when it unmounts, and the channel is left when its last user unmounts, so several components can listen on the same channel. A visitor opens no connection: the hook takes `enabled`, which a page or component sets to false for a visitor, and `window.Echo` (`bootstrap.ts`) is created on its first use, because creating it opens the connection. Pages merge incoming events into their state (by `cuid`, so a duplicate is ignored) without losing what the member is typing.

## Notifications: the bell and the panel

The bell and its panel (#1484, [ADR 0042](../adr/0042-notifications.md), design 5.1, 5.2, 6.12 and 9) are in `Components/Notifications`. They show the "Needs you" part only. `<AppLayout>` renders them for a signed-in member, on every page. A visitor gets no bell, no count request, no socket and no live region.

1. One source of truth for the number. `NotificationsProvider` holds the count, and the count is always the value of the server (`GET /notifications/count`). It is loaded on page load, when the window gets the focus, when the socket reconnects (`useSocketReconnected`) and after each `notifications.changed` event of the channel `user.{cuid}`. The client never adds 1. Events of a short time give one request (a trailing debounce of 300 ms), and a late answer never replaces a newer one. The bell name holds the number ("Notifications, 3 new", "Notifications, 9 or more new") and the badge (1 to 9, then "9+") is hidden from assistive technology.
2. The panel mounts when it opens, so each opening loads the list again (`use-notification-feed.ts`: 20 lines per page, "Load older", and "Older" behind a closed disclosure, loaded the first time it opens, never counted). On a desktop it is a non-modal region under the bell: Tab leaves it, Escape and a click outside close it. Below the `md` breakpoint (960 px, the phone layout of the app) it is the `Drawer` component. The focus goes to the heading "Notifications" on opening, and back to the bell on closing.
3. A line is a list item with one link and real buttons (`NotificationLine`). The link goes to the exact item (`notification-link.ts`): `#comment-{cuid}` on the post page or the resource page (the permalinks of #1205), and `?thread={cuid}#message-{cuid}` on the chat space. `ChatRoom` follows that address: it opens the thread, or loads older messages (10 pages at most) until the message is there, and gives it the focus. The target of a jump keeps a visible outline until the focus leaves it. A line that is not seen yet shows "New" in bold, never by color alone. The word stays while the panel is open.
4. Seen: a line that stayed visible in the open panel for one second (an `IntersectionObserver`, or every line when the browser has none) is marked seen. One request holds all the lines of that moment, then the count is loaded again.
5. "Done" marks the row read and removes the line. The status text of the panel (polite) says "Done. Undo" for 8 seconds. "Undo" puts the line back; a 422 (a newer notification exists) reloads the list. The focus goes to the next line, else the line before, else the heading. The buttons of a line never use `disabled` or `loading`: they use `aria-disabled` and `aria-busy` with a guard.
6. An event while the panel is open never moves the list. Lines that arrived wait behind "2 new. Show". A line that another device marked done and that has the focus stays, marked "Done on another device", until the focus leaves it.
7. One polite live region exists from the load (in `NotificationsProvider`). It says the actor and the verb of a new level 1 row that the member did not see ("Ben replied to your post"), once per 30 seconds at most. Several arrivals of the window become "3 new for you". It never announces a change of the count. Nothing uses `assertive`.
8. With `prefers-reduced-motion` nothing slides or shimmers (the skeleton lines use `motion-safe:animate-pulse`, the `Drawer` has its own rule), and the jump to a comment or a message is instant.
9. The shared Inertia prop `communityCount` tells the panel whether to show the name of the community on each line (more than one community).

## Tests

Vitest and Testing Library, in `*.spec.tsx` files next to the component. Test the behavior the member sees: roles, labels, keyboard. Run them with `npx nx run-many -t test`.
