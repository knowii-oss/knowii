const { join } = require('path');

module.exports = {
  plugins: {
    'postcss-import': {},
    // Reference: https://tailwindcss.com/docs/using-with-preprocessors
    'tailwindcss/nesting': {},
    tailwindcss: {
      config: join(__dirname, 'tailwind.config.ts'),
    },
    'postcss-flexbugs-fixes': {},
    // Reference: https://github.com/csstools/postcss-preset-env
    'postcss-preset-env': {
      autoprefixer: {
        flexbox: 'no-2009',
      },
      stage: 3,
      features: {
        'custom-properties': false,
        // Reference: https://tailwindcss.com/docs/using-with-preprocessors
        'nesting-rules': false,
      },
    },
  },
};
