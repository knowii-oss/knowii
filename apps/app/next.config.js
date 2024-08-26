//@ts-check

// eslint-disable-next-line @typescript-eslint/no-var-requires
const { composePlugins, withNx } = require('@nx/next');

console.log(`> Building in "${process.env.NODE_ENV}" mode (NODE_ENV)`);

/**
 * @type {import('@nx/next/plugins/with-nx').WithNxOptions}
 **/
const nextConfig = {
  nx: {
    // Set this to true if you would like to use SVGR
    // See: https://github.com/gregberge/svgr
    svgr: false,
  },
  images: {
    remotePatterns: [],
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
  typescript: {
    /**
     * `lint:types` ran in CI already so we can safely assume no errors
     *  here, conveniently reducing build time by ~55%
     * @see https://nextjs.org/docs/api-reference/next.config.js/ignoring-typescript-errors
     */
    ignoreBuildErrors: true,
  },
  experimental: {
    // FIXME unsure if this helps
    serverComponentsExternalPackages: ['pino', 'pino-pretty'],
  },
};

const plugins = [
  // Add more Next.js plugins to this list if needed.
  withNx,
];

module.exports = composePlugins(...plugins)(nextConfig);
