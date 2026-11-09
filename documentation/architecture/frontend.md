# Front-end design

The front-end is a React 19 application rendered through Inertia.js 3. Laravel decides which page to show and with which initial data. React renders it, and talks to the REST API for every change.

## Pages

1. A page is a component in `apps/knowii/src/Pages/<Area>/<Name>.tsx`, rendered by an Inertia controller (`app/Http/Controllers/Inertia`) or by `Route::inertia(...)` in `routes/web.php` for static pages.
2. The controller passes the initial data as props. The props interface of the page and the array in the controller must stay aligned (both carry a `WARNING` comment).
3. Every page renders inside `<AppLayout>`, which gives the header, the breadcrumb, the page actions and the footer.
4. Further data and every change go through the Knowii API client, never through Inertia form posts. The API stays complete.
5. Links use Inertia `<Link>`. A link that changes the URL without a reload sets `preserveState={true}`.

## Global data

1. Data that every page needs (the current user, the communities of the user) comes from `HandleInertiaRequests`.
2. Client-side state that every page needs lives in the React context of `libs/common/src/lib/contexts/app-context.tsx`. Example: `showToast()`. The provider keeps the toasts, and the `<Toaster>` (mounted once in `main.tsx`) shows them.

## UI components

1. The building blocks live in `apps/knowii/src/Components/ui` and are imported from `@/Components/ui`: buttons, form controls, dialog, drawer, menu, select, cards, avatar, toasts and more ([ADR 0003](../adr/0003-own-ui-components.md)).
2. Use them instead of styling raw elements. A new building block goes there, with a Vitest test (`*.spec.tsx`), keyboard support and a visible focus ring.
3. Headless UI is used only for the accessible interactive parts (dialog, listbox, menu). Everything else is plain React and Tailwind CSS.
4. Feature components (a post card, a space navigation) live in `apps/knowii/src/Components/<Feature>/` and compose the building blocks.

## Themes and tokens

1. Colors, fonts and breakpoints are the Knowii brand tokens defined with `@theme` in `apps/knowii/src/styles.css`. The default Tailwind palette is disabled.
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
3. The Knowii API client (`libs/common/src/lib/api/knowii-api-client.ts`) is the only place that calls `fetch` for the API. API paths are constants in `constants.ts`. Each method parses the response with Zod and returns a typed result, also on failure.

## Real time

`useSocket` (`libs/common/src/lib/hooks/use-socket.ts`) wraps Laravel Echo. A page names a channel and the events it wants, and gets typed payloads. The event names match `broadcastAs()` on the back-end. Pages merge incoming events into their state (by `cuid`, so a duplicate is ignored) without losing what the member is typing.

## Tests

Vitest and Testing Library, in `*.spec.tsx` files next to the component. Test the behavior the member sees: roles, labels, keyboard. Run them with `npx nx run-many -t test`.
