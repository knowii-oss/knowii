# 0003. Own UI components on Tailwind CSS and Headless UI

- Status: Accepted
- Date: 2026-11-09
- Pull request: #1169

## Context

The UI used PrimeReact 10. PrimeReact 11 is a rewrite under a commercial license (PrimeUI), which does not fit an AGPL project, and its theming fought the Knowii brand (Tailwind colors came from PrimeReact variables). The strict CSP also forbids the inline styles that some kits inject.

## Decision

1. Own components in `apps/knowii/src/Components/ui`, built with Tailwind CSS 4 and the Knowii brand tokens (`apps/knowii/src/styles.css`).
2. Headless UI only for the accessible interactive parts: dialog, listbox, menu.
3. Dark (default) and light themes through `data-theme` and tokens that change with the theme.
4. Every component has Vitest tests, keyboard support and a visible focus.

## Consequences

1. New screens use these components. A missing building block is added there, with tests, not styled ad hoc in a page.
2. No raw hex values and no Tailwind palette colors (the palette is disabled).

## Alternatives considered

1. PrimeReact 11: commercial license.
2. Another full UI kit (MUI, Chakra): heavy, its own design language, inline styles.
