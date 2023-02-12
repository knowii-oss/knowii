//@ts-check
/** @type {import('next').NextConfig} */

// eslint-disable-next-line @typescript-eslint/no-var-requires
const helpers = require('../../helpers');

const { i18n } = require('./next-i18next.config');

console.debug(`> Building on NODE_ENV="${process.env.NODE_ENV}"`);

// eslint-disable-next-line @typescript-eslint/no-var-requires
const { withNx } = require('@nrwl/next/plugins/with-nx');

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
  i18n,
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
  /**
   * Configure Sass
   */
  sassOptions: {
    includePaths: [helpers.root('apps/knowii/styles')],
  },
  typescript: {
    /**
     * `lint:types` ran in CI already so we can safely assume no errors
     *  here, conveniently reducing build time by ~55%
     * @see https://nextjs.org/docs/api-reference/next.config.js/ignoring-typescript-errors
     */
    ignoreBuildErrors: true,
  },
};

module.exports = withNx(nextConfig);
