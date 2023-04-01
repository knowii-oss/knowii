module.exports = {
  core: { builder: 'webpack5' },
  stories: ['../src/lib/**/*.stories.mdx', '../src/lib/**/*.stories.@(js|jsx|ts|tsx)'],
  addons: ['@storybook/addon-essentials', '@nrwl/react/plugins/storybook', 'storybook-addon-next-router', 'storybook-tailwind-dark-mode'],
  // Customize the Webpack configuration
  // References
  // - https://storybook.js.org/docs/react/builders/webpack#extending-storybooks-webpack-config
  // - https://nx.dev/packages/storybook/documents/custom-builder-configs
  webpackFinal: async (config, { configType }) => {
    config.resolve.alias = {
      ...config.resolve.alias,
    };
    return config;
  },
};
