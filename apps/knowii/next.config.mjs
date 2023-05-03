//@ts-check
/** @type {import('next').NextConfig} */

import { withNx } from '@nx/next/plugins/with-nx.js';
import { i18nConfig } from '../../i18n.config.mjs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

console.log(`> Building in "${process.env.NODE_ENV}" mode (NODE_ENV)`);

/**
 * Run `build` or `dev` with `SKIP_ENV_VALIDATION` to skip env validation.
 * This is especially useful for Docker builds.
 */
!process.env.SKIP_ENV_VALIDATION && (await import('./env.mjs'));

/**
 * @type {import('@nx/next/plugins/with-nx').WithNxOptions}
 **/
const nextConfig = {
  /**
   * Next.js configuration
   * References:
   * https://github.com/vercel/next.js/blob/canary/packages/next/next-server/server/config.ts#L12-L63
   * https://nextjs.org/docs/api-reference/next.config.js/introduction
   */
  // FIXME remove this once migrated to Next.js 13 (app folder)
  i18n: i18nConfig.i18n,
  nx: {
    // Set this to true if you would like to to use SVGR
    // See: https://github.com/gregberge/svgr
    svgr: false,
  },
  /**
   * Disable powered by header
   */
  poweredByHeader: false,
  /**
   * Enable strict mode
   */
  reactStrictMode: true,
  trailingSlash: false,
  swcMinify: true,
  typescript: {
    /**
     * `lint:types` ran in CI already so we can safely assume no errors
     *  here, conveniently reducing build time by ~55%
     * @see https://nextjs.org/docs/api-reference/next.config.js/ignoring-typescript-errors
     */
    ignoreBuildErrors: true,
  },
  // output: 'standalone', // Enable Output File Tracing:  https://nextjs.org/docs/advanced-features/output-file-tracing
  experimental: {
    appDir: true,
    // This includes files from the monorepo base two directories up
    // Required for output file tracing
    outputFileTracingRoot: path.join(__dirname, '../../'),

    // Added excludes to limit the size of serverless functions in production
    // Without this we hit the 50MB limit and those are not required in production
    // To debug function size, run: `NEXT_DEBUG_FUNCTION_SIZE=1 vercel build`
    // Reference: https://github.com/orgs/vercel/discussions/103
    outputFileTracingExcludes: {
      '*': [
        // prettier-ignore
        'apps/knowii/.next',
        'apps/knowii/.next/**',
        'node_modules/@swc/core-linux-x64-gnu',
        'node_modules/@swc/core-linux-x64-musl',
        'node_modules/@esbuild/linux-x64',
      ],
    },
  },
};

export default withNx(nextConfig);
