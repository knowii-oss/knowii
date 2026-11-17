import type { ResolvedComponent } from '@inertiajs/react';

// The page components of the application, by their path (`./Pages/<name>.tsx`).
// The spec files sit next to the pages. The second pattern leaves them out: without it the production build
// ships every spec file as a page, with its test libraries, and a page can lose its own entry in the Vite manifest (#1552)
export const pageComponents = import.meta.glob<ResolvedComponent>(['./Pages/**/*.tsx', '!./Pages/**/*.spec.tsx'], {
  import: 'default',
});
