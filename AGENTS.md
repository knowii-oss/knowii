# AGENTS.md

Guidance for AI agents and contributors working in this repository. This is the
short version of the project conventions; `CLAUDE.md` points here. The full
technical documentation is in `documentation/`.

## Technical documentation (read first)

Before a structural change (a new model or concept, a new space type, a new
channel, a new dependency, a new pattern), read:

- `documentation/README.md` — index
- `documentation/architecture/` — overview, high-level design (domain model,
  multi-community scoping, request flow, authorization, real time, security),
  back-end design, front-end design
- `documentation/conventions/naming.md` — names of classes, files, tables,
  routes, events, channels, branches
- `documentation/conventions/rules.md` — the architecture rules and the
  **definition of done**
- `documentation/adr/` — architecture decision records. Never contradict an
  accepted ADR silently: write a new ADR that supersedes it.

In the same pull request as the code:

- Add an ADR (`documentation/adr/NNNN-title.md`, from `template.md`, listed in
  the index) for a structural decision (see `documentation/adr/README.md`).
- Update the architecture documents when the domain model, a flow or a runtime
  process changes.
- For each new or changed API endpoint: follow the NBB REST API Design Guide
  (ADR 0011: naming, methods, status codes, pagination, errors), Pest tests, a Bruno request in
  `documentation/api` (see its `README.md`), and a check that the generated
  OpenAPI document (`/docs/api`, Scramble) describes it correctly.
- New configuration values go to `SELF_HOSTING.md` and `.env.example`.

## Project

Knowii is a community knowledge platform. It is an **Nx monorepo** combining a
**Laravel 13** (PHP 8.5) back-end with a **React 19 + Inertia.js 3** front-end, styled with
**Tailwind CSS** (own components, Headless UI for the accessible interactive
parts).

Tech stack you are expected to be fluent in: Laravel, PHP (8.5+), Pest,
TypeScript, React, Tailwind CSS, Headless UI, Jetstream, Inertia.js, Zod,
react-hook-form.

## Repository layout

- `app/`, `routes/`, `config/`, `database/`, `bootstrap/` — Laravel back-end
- `apps/knowii/src/` — React front-end (`Pages/`, `Components/`, `Layouts/`)
- `libs/common/` — shared TypeScript library (`common`), includes the Knowii API
  client and shared `constants.ts`
- `tests/` — Pest tests (`Feature/`, `Unit/`)
- `documentation/` — technical documentation, ADRs, Bruno API collection
- `tools/` — build helper scripts

## Commands

Front-end / monorepo (npm + Nx):

- `npm run dev` — serve the app
- `npm run build` — production build (generates the Vite manifest)
- `npm run test` / `npm run test:all` — run tests (Vitest via Nx)
- `npm run lint` — lint everything (`lint:knowii`, `lint:common`, `lint:php`)
- `npm run format` — format everything (Nx + `composer format`)
- `npm run tsc` — type-check (`tsc --noEmit`, strictest config)

Back-end (Composer / Laravel):

- `composer lint` — PHPStan/Larastan static analysis
- `php artisan test` — run the Pest suite
- `composer format` — Laravel Pint

After cloning, run `npm run setup` once to enable the Git hooks (see
`CONTRIBUTING.md`).

## Working style

- If asked for a fix or explanation, give **actual code or a concrete
  explanation** — no high-level theory, no "here's how you could…" filler.
- Be terse, concise and accurate; treat the reader as an expert. No moral
  lectures, no fluff, no restating that you are an AI or your knowledge cutoff.
- Anticipate needs and suggest solutions that weren't asked for when relevant.
- Value good arguments over authority. Flag speculation explicitly.
- Cite sources at the end, not inline, when relevant.
- Care about architecture and design quality; weigh new tech and contrarian
  ideas, not just conventional wisdom.
- Fully implement requested functionality. Leave **no** TODOs, placeholders or
  missing pieces. Respect Prettier formatting.

## Coding conventions

### General

- TypeScript must compile under the strictest configuration; **never** use
  `any`. The base config enables `strict`, `noUncheckedIndexedAccess`,
  `exactOptionalPropertyTypes`, `noPropertyAccessFromIndexSignature` and
  `verbatimModuleSyntax`: never relax them to make code compile.
- Import types with `import type` (enforced by ESLint and required by
  `verbatimModuleSyntax`).
- Before committing, run `npm run tsc` and
  `npx nx run-many -t lint typecheck test` (CI fails on any error).
- Favor readability over premature performance optimization.
- Use up-to-date, valid syntax.

### Back-end (PHP / Laravel)

- API controllers handle incoming requests but **always delegate** processing to
  an Action class implementing a `DoesSomething` contract.
- API controllers use the `ApiResponses` trait and live under
  `app/Http/Controllers/API`.
- Validation lives in the Action classes and uses the constants defined in
  `Constants.php`.
- Every API endpoint is registered in `routes/api.php`; most are protected by the
  Sanctum middleware.
- Write tests with **Pest**.
- Laravel 11+ has no `Kernel.php` — configure the application in
  `bootstrap/app.php` instead.

### Front-end (TypeScript / React / Tailwind / Headless UI / Zod / react-hook-form)

- Pages live under `apps/knowii/src/Pages`; components under
  `apps/knowii/src/Components`.
- UI building blocks (buttons, form controls, dialog, drawer, menu, select,
  toasts, cards, ...) live in `apps/knowii/src/Components/ui` and are imported
  from `@/Components/ui`. Use them instead of styling raw elements. Build new
  ones there, with tests (`*.spec.tsx`, Vitest + Testing Library), keyboard
  support and visible focus. Headless UI is used only for the accessible
  interactive parts (dialog, listbox, menu); everything else is plain React and
  Tailwind CSS.
- Colors, fonts and breakpoints are the Knowii brand tokens defined with
  `@theme` in `apps/knowii/src/styles.css` (same names as store-website:
  `background`, `surface`, `surface-elevated`, `well`, `primary` (text),
  `muted`, `secondary` (magenta), `secondary-text`, `border`,
  `border-strong`, `overlay`, `success`, `warning`, `danger`). The default
  Tailwind palette is disabled: never use raw hex values or Tailwind palette
  colors.
- Two themes: dark (default) and light, selected with `data-theme` on
  `<html>` (inline script in `app.blade.php`, `useTheme()` and
  `<ThemeToggle>`). Tokens change with the theme, so never hardcode
  `white`/`black`: use `bg-overlay/10` for subtle fills and hovers,
  `text-primary` for text, `outline-primary` for focus. `text-white` is only
  for text on `secondary` or `danger` fills. Check every screen in both
  themes.
- `secondary-text` (#ff1493) fails WCAG AA for normal text on the dark
  background: use it for large or decorative text only.
- `<Button>` defaults to `type="button"`: set `type="submit"` explicitly. For
  navigation, use `<LinkButton>` (or `buttonClasses()` on a link), never a
  button inside a link.
- Toasts: call `showToast()` from `useAppData()`; the `<Toaster>` is mounted
  once in `main.tsx`.
- Content Security Policy: scripts and styles need the per-request nonce;
  `'unsafe-inline'` is not allowed for scripts or styles. Set styles with
  classes or React `style` props (CSSOM, allowed), never with
  `setAttribute('style')` or new `<style>` elements without the nonce from
  `<meta property="csp-nonce">`. Avoid third-party embeds that inject styles;
  third-party scripts must be added by our code (`'strict-dynamic'`).
- Every page uses the `<AppLayout>` component.
- Forms use **react-hook-form** and call the back-end through the **Knowii API
  client**.
- API URL paths are declared in `constants.ts` in the `common` library.
- Data retrieval goes through the Knowii API client (extend it when new
  operations are needed).
- Links use Inertia.js `<Link>`. Add `preserveState={true}` to links (and to
  programmatic `.visit(...)` calls) that change the URL without reloading.
