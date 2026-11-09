import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

// Unit and component tests. Kept apart from vite.config.mts so the Laravel plugin does not run during tests.
export default defineConfig({
  root: import.meta.dirname,
  cacheDir: '../../node_modules/.vite/apps/knowii',
  plugins: [react()],
  resolve: {
    tsconfigPaths: true,
    alias: {
      '@': `${import.meta.dirname}/src`,
    },
  },
  test: {
    watch: false,
    environment: 'jsdom',
    setupFiles: ['./src/test-setup.ts'],
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    reporters: ['default'],
    coverage: {
      reportsDirectory: '../../coverage/apps/knowii',
      provider: 'v8',
    },
  },
});
