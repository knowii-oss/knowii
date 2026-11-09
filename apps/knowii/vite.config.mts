/// <reference types='vitest' />
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { nxReadySignal } from '@nx/vite/plugins/nx-ready-signal.plugin';
import type { UserConfig } from 'vite';

//console.log("Launching Vite build. Current directory: ", __dirname);

const workspaceRoot = `${import.meta.dirname}/../..`;
const buildFolder = `${workspaceRoot}/public/build`;

const viteConfig = defineConfig({
  // The monorepo root is the Vite root, so the module paths are the ones Laravel uses (apps/knowii/src/...):
  // in the manifest (production) and in the dev server URLs (hot mode)
  root: workspaceRoot,
  base: '',
  cacheDir: `${workspaceRoot}/dist/.vite/apps/knowii`,

  css: {
    // Load apps/knowii/postcss.config.js (Vite looks for it from the root otherwise)
    postcss: import.meta.dirname,
  },

  // Public assets are served by Laravel and managed separately
  publicDir: false,

  server: {
    // WARNING: Mandatory to enable HMR
    hmr: {
      host: 'localhost',
    },
    strictPort: true, // Avoid using a different port if the default is taken
    watch: {
      // Polling might be useful in some cases if file changes are not detected
      //usePolling: true,
      //interval: 1000,

      // Folders to ignore
      ignored: [
        '**/.devcontainer/**',
        '**/.nx/**',
        '**/.idea/**',
        '**/.vscode/**',
        '**/bootstrap/**',
        '**/config/**',
        '**/database/**',
        '**/dist/**',
        '**/public/**',
        '**/resources/**',
        '**/routes/**',
        '**/storage/**',
        '**/tests/**',
        '**/tmp/**',
        '**/tools/**',
        '**/vendor/**',
      ],
    },
  },

  preview: {
    port: 4300,
    host: 'localhost',
  },

  plugins: [
    // Lets Nx know when the dev and preview servers are ready
    nxReadySignal(),
    // WARNING: MUST be aligned with the config in app.blade.php
    // Reference: https://github.com/laravel/vite-plugin
    laravel({
      input: [`${import.meta.dirname}/src/main.tsx`],
      refresh: true,
      hotFile: `${workspaceRoot}/public/hot`,
      buildDirectory: 'build',
    }),
    react(),
  ],

  // WARNING
  // Mandatory to override Laravel's default and let Vite know where to locate the files
  // MUST be aligned with the entry in the "paths" property of tsconfig.base.json
  // Reference: Mention that mandatory to override the default alias defined by the Laravel plugin
  resolve: {
    // Resolve the TypeScript path mappings (e.g., @knowii/common, ziggy-js) natively
    tsconfigPaths: true,
    alias: {
      '@': `${import.meta.dirname}/src`,
    },
  },

  // Reference: https://vitejs.dev/config/build-options
  // Base path here: /var/www/html/apps/knowii
  build: {
    // WARNING: MUST be aligned with the config in app.blade.php
    outDir: buildFolder, // writes under the <root>/public folder
    emptyOutDir: true,
  },
}) satisfies UserConfig;

export default viteConfig;
