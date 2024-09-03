/// <reference types='vitest' />
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { nxViteTsPaths } from '@nx/vite/plugins/nx-tsconfig-paths.plugin';
import type { UserConfig } from 'vite';

//console.log("Launching Vite build. Current directory: ", __dirname);

const buildFolder = '../../public/build';

const viteConfig = defineConfig({
  cacheDir: '../../dist/.vite/apps/knowii',

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
    nxViteTsPaths(),
    // WARNING: MUST be aligned with the config in app.blade.php
    // Reference: https://github.com/laravel/vite-plugin
    laravel({
      input: [`${__dirname}/src/main.tsx`],
      refresh: true,
      hotFile: '../../public/hot',
      buildDirectory: buildFolder,
    }),
    react(),
  ],

  // WARNING
  // Mandatory to override Laravel's default and let Vite know where to locate the files
  // MUST be aligned with the entry in the "paths" property of tsconfig.base.json
  // Reference: Mention that mandatory to override the default alias defined by the Laravel plugin
  resolve: {
    alias: {
      '@': `${__dirname}/src`,
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
