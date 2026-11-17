# 0049. Component toolkit: Storybook, stories as tests

- Status: Accepted
- Date: 2026-11-17
- Issue: #657
- Pull request: #1555

## Context

[ADR 0003](0003-own-ui-components.md) gives Knowii its own UI components in `apps/knowii/src/Components/ui`. There are about 30 of them, and each has Vitest tests or will get them. A contributor cannot see them: to look at a button in the light theme at 360 px, they start the application and find a page that shows it. The specs test the behavior, but nobody sees all the variants and states of a component on one page, and nothing checks that a new component gets its examples.

The owner decided on 2026-11-17 (comments of #657): Storybook in the application project, four development packages, no browser in the image for now, the publication on the page site of the repository in a later slice, and no visual comparison for now.

## Decision

1. Storybook 10 with the Vite builder (`@storybook/react-vite`), configured in `apps/knowii/.storybook/`. It loads the Vite configuration of the application (`vite.config.mts`) without the Laravel plugin, so that Tailwind CSS 4 and the path aliases work the same way. The preview loads `styles.css` and the Noto Sans font, a toolbar item sets the theme on `<html data-theme>` with `applyTheme()` of `theme.ts` (dark first), and the viewport list has 360 px and 1920 px.
2. Four development packages, pinned to an exact version: `storybook` (the core and the command), `@storybook/react-vite` (React and the Vite builder), `@storybook/addon-a11y` (the accessibility panel in the browser, with contrast) and `@storybook/addon-docs` (a docs page per component, from the stories and the comments). Nothing of Storybook goes into the application bundle.
3. The stories sit next to the component: `Components/ui/<Name>.stories.tsx`, in the CSF 3 format with types. A story file has one story per variant and state (kinds, sizes, with and without an icon, disabled and `aria-disabled`, busy, error, long text, as the component has them). The comment of the meta says when to use the component and its accessibility contract.
4. The stories are the test cases. `Components/ui/stories.spec.tsx` finds every story file (`import.meta.glob`), composes the stories (`composeStories`), and for each story: renders it, runs its `play` function, and runs `axe-core` on the page. It runs in the Vitest and jsdom set-up of the application, with the other tests. A keyboard path is a `play` function (for example the Dialog: it opens, the focus goes inside, Tab stays inside, Escape closes, the focus goes back).
5. jsdom first. It needs no browser in the container image and no new CI job, and it is fast. Its limit: jsdom computes no colors and no layout. The spec turns off only the axe rules that need them (`color-contrast`), by name. The contrast, the sizes and the look are checked in the browser, in the accessibility panel of Storybook.
6. The guard (`Components/ui/stories-guard.spec.ts`): every component file of `Components/ui` has a story file, and every story file exports one story or more. The components without a story are in an allow-list in the test, sorted. A name of the list that has a story fails the test, so the list only gets shorter.
7. `npm run storybook` starts the dev server (port 6006, reachable from the host) and `npm run storybook:build` writes the static site to `dist/storybook`. The `main` job of CI builds it.
8. Later slices: the stories of the other components, then the publication of the built site on the page site of the repository, from `main`. No visual comparison of pictures for now; decide again after the fixes of the UI review (#1523).

## Consequences

1. A new component of `Components/ui` comes with its story file in the same pull request: the guard fails without it. Its keyboard path is a `play` function.
2. A story is a test: it must render without the application around it. A component that needs the application (an Inertia link) gets the smallest stand-in in its stories, not a change of the component: the link stories of `Button` stop the Inertia visit in their `onClick`.
3. The specs that exist stay. A case moves to a story only when the story covers it.
4. The strict Content Security Policy of the application does not apply to Storybook, which is a development tool. Nothing in the application changes for it.
5. jsdom does not make the page behind a dialog inert: `play` functions look for the elements inside the dialog (`within(dialog)`).

## Alternatives considered

1. Stories tested in a real browser (Vitest browser mode): it checks contrast and layout, but it needs a browser in the image and in CI. Later, when a slice needs it.
2. A catalogue page inside the application: it needs a route, the CSP and the authentication of the application, and gives no docs and no test runner.
3. A test file per component that renders its examples by hand: the examples and the tests drift apart, and nobody sees them.
