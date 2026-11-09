import { defineConfig } from 'vitest/config';

// Vitest projects of the workspace (Vitest 4 replaced workspace files with `test.projects`).
// Nx runs each project's tests through its own Vite configuration; this file is used when running `npx vitest` from the root.
export default defineConfig({
  test: {
    projects: ['libs/*/vite.config.mts', 'apps/knowii/vitest.config.mts'],
  },
});
