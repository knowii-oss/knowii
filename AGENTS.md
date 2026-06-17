# AGENTS.md

Guidance for AI agents and contributors working in this repository. This is the
single source of truth for project conventions; `CLAUDE.md` and `.cursorrules`
both point here.

## Project

Knowii is a community knowledge platform. It is an **Nx monorepo** combining a
**Laravel 12** back-end with a **React 18 + Inertia.js** front-end, styled with
**Tailwind CSS** and **PrimeReact**.

Tech stack you are expected to be fluent in: Laravel, PHP (8.2+), Pest,
TypeScript, React, PrimeReact, Tailwind CSS, Jetstream, Inertia.js, Zod,
react-hook-form.

## Repository layout

- `app/`, `routes/`, `config/`, `database/`, `bootstrap/` — Laravel back-end
- `apps/knowii/src/` — React front-end (`Pages/`, `Components/`, `Layouts/`)
- `libs/common/` — shared TypeScript library (`common`), includes the Knowii API
  client and shared `constants.ts`
- `tests/` — Pest tests (`Feature/`, `Unit/`)
- `tools/` — build helper scripts

## Commands

Front-end / monorepo (npm + Nx):

- `npm run dev` — serve the app
- `npm run build` — production build (generates the Vite manifest)
- `npm run test` / `npm run test:all` — run tests (Vitest/Jest via Nx)
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
  `any`.
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

### Front-end (TypeScript / React / PrimeReact / Tailwind / Zod / react-hook-form)

- Pages live under `apps/knowii/src/Pages`; components under
  `apps/knowii/src/Components`.
- Every page uses the `<AppLayout>` component.
- Forms use **react-hook-form** and call the back-end through the **Knowii API
  client**.
- API URL paths are declared in `constants.ts` in the `common` library.
- Data retrieval goes through the Knowii API client (extend it when new
  operations are needed).
- Links use Inertia.js `<Link>`. Add `preserveState={true}` to links (and to
  programmatic `.visit(...)` calls) that change the URL without reloading.
