//@ts-check
/** @type {import('next').NextConfig} */

import { withNx } from '@nrwl/next/plugins/with-nx.js';
import { i18nConfig } from '../../next-i18next.config.mjs';

console.log(`> Building in "${process.env.NODE_ENV}" mode (NODE_ENV)`);

/**
 * Run `build` or `dev` with `SKIP_ENV_VALIDATION` to skip env validation.
 * This is especially useful for Docker builds.
 */
!process.env.SKIP_ENV_VALIDATION && (await import('./env.mjs'));

/**
 * @type {import('@nrwl/next/plugins/with-nx').WithNxOptions}
 **/
const nextConfig = {
  /**
   * Next.js configuration
   * References:
   * https://github.com/vercel/next.js/blob/canary/packages/next/next-server/server/config.ts#L12-L63
   * https://nextjs.org/docs/api-reference/next.config.js/introduction
   */
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
};

export default withNx(nextConfig);