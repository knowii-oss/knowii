# Component toolkit: proposal (2026-11-17)

Status: accepted by the owner on 2026-11-17 with the four decisions of section 4 (his answers are on #657). Decision record: ADR 0049. Built so far: slice 1 (#1555).

This is a proposal for the owner. Nothing is built before the four decisions of section 4.

## 1. What exists today (main at `382ded2d`)

1. `apps/knowii/src/Components/ui` holds 30 component files and 13 spec files. ADR 0003 is the base: own components on Tailwind CSS 4 and Headless UI.
2. The tests run in Vitest 5 with jsdom. `axe-core` is already a development dependency.
3. Three components know the application: `Button` and `Breadcrumb` import the `Link` of Inertia, and `MarkdownContent` imports the video player of the application. Seven import `@knowii/common` (the shared library of the workspace).
4. About 25 more shared components live one level up (`PageHeader`, `FormSection`, `InputError`, `InputLabel`, `SectionTitle` and others). Several are building blocks and belong to the toolkit.
5. Storybook 10.6 supports the stack as it is (Vite 8, React 19, Vitest 5). Read in the peer ranges of the packages on the registry on 2026-11-17.

## 2. The proposal

1. Storybook 10 with the Vite builder, in the application project. The stories sit next to the components (`Button.stories.tsx`). It starts and builds inside the container: `npm run storybook` and `npm run storybook:build`.
2. One story file per component. One story per variant and state: kinds, sizes, with and without an icon, disabled and `aria-disabled`, busy, error, long text. A toolbar switches the theme and the width (1920 px, 360 px).
3. The stories are the test cases. A Vitest spec loads every story file (the "portable stories" of Storybook), renders each story in jsdom, runs its interaction steps, and runs the automated accessibility check on it. The keyboard paths of dialog, drawer, menu, select and segmented control are interaction steps of their stories. The specs that exist stay until their cases live in a story. Nothing loses coverage.
4. The guard test fails when a component of the toolkit has no story file, and when a story file exports no story.
5. A component boundary: a lint rule says that `Components/ui` imports only React, Headless UI, the icons and `@knowii/common`. The three components of 1.3 get their application part as a property (the link component, the video renderer). After that, a move to a library is a move of files.
6. Documentation: each story file holds the "when to use, when not" text and the accessibility contract (name, role, states, focus). `documentation/architecture/frontend.md` gets a toolkit section. A new ADR extends ADR 0003.
7. Continuous integration: the Storybook build and the story tests run in the `main` job. Budget: 3 minutes more at most.

Why jsdom first and not a real browser: it needs no browser in the container image and no new CI job, and it is fast on the Windows machine. Its limit: jsdom does not compute colors and layout, so contrast and sizes are not tested by it. The accessibility panel of Storybook shows contrast in the browser, by hand. A browser run of the same stories (Vitest browser mode with Playwright) is a later slice. It is also the base of a visual comparison, see decision 2.

## 3. Slices

1. Slice 1: Storybook in the workspace, the theme and width toolbar, the test that runs every story, the guard test with an allow-list of the components that have no story yet, and the stories of five components: Button, TextInput, Checkbox, Dialog, EmptyState.
2. Slice 2: stories for the other components, in two or three pull requests. The allow-list of the guard goes to zero.
3. Slice 3: the boundary lint rule and the three components of 1.3.
4. Slice 4: the missing building blocks that the UI review (#1523) names, each with its stories. Candidates from #657: the status text ("Saved", "Not saved. Retry."), the dot and the count, the banner with a question, skeleton lines, the switch, "Load more", pills and labels. The shared components of 1.4 move in here too.
5. Slice 5: the documentation, the ADR, and the publication of decision 1.

The event screens (#1513 and later) use the toolkit from slice 1 on.

## 4. Decisions for the owner

1. Where the built Storybook is published. Recommendation: on the page site of the repository (GitHub Pages), from `main`, in slice 5. The project is open source and the catalogue holds no personal data. A contributor sees every building block without an install. Alternatives: only locally and as a CI build (nothing to host), or inside a documentation site (none exists today).
2. Visual comparison of screenshots. Recommendation: none now. The UI review changes the look of many components in the next weeks, and each change would mean new reference images. Decide again after the review fixes land. The option then is the local one: Playwright screenshots of the stories, made inside the container image so that the fonts are the same on every machine, with the images in the repository. No outside service.
3. The components stay in the application or move to `libs/ui`. Recommendation: they stay in the application, with the boundary of 2.5. No second product uses them today. A library adds build and release work now for a use that does not exist yet. The boundary keeps the move cheap.
4. New development dependencies. Recommendation: four packages, pinned to exact versions like the others, development only: `storybook`, `@storybook/react-vite`, `@storybook/addon-a11y`, `@storybook/addon-docs` (10.6.7 today). No Playwright and no browser in the image for now. The new ADR records them (rule 11 of `rules.md`).
