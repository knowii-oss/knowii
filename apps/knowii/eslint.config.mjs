import baseConfig from '../../eslint.config.mjs';
import nx from '@nx/eslint-plugin';

// The boundary of the toolkit (#657, ADR 0049): Components/ui imports only these modules, and the other files of its
// folder. Everything else is an error: Inertia, Ziggy, an @/ path, a relative path out of the folder.
const toolkitImports = [
  'react',
  'react-dom',
  'react-dom/client',
  '@headlessui/react',
  'react-icons/[\\w-]+',
  '@knowii/common',
  'classnames',
  'tailwind-merge',
  'date-fns',
  // A file of the folder: "./Button", never a path with ".."
  '\\./(?!.*\\.\\.).+',
];

const toolkitBoundaryMessage =
  'Components/ui is the toolkit: it imports only React, Headless UI, the icons and @knowii/common. ' +
  'Pass the part of the application as a property or through the toolkit context.';

export default [
  ...baseConfig,
  ...nx.configs['flat/react'],
  {
    files: ['**/*.ts', '**/*.tsx', '**/*.js', '**/*.jsx'],
    rules: {
      '@nx/enforce-module-boundaries': 0,
    },
  },
  {
    files: ['**/*.ts', '**/*.tsx'],
    // Override or add rules here
    rules: {},
  },
  {
    files: ['**/*.js', '**/*.jsx'],
    // Override or add rules here
    rules: {},
  },
  {
    // The specs and the stories of the toolkit may use the application (a decorator, a test of the application set-up)
    files: ['src/Components/ui/**/*.{ts,tsx,js,jsx}'],
    ignores: ['src/Components/ui/**/*.spec.*', 'src/Components/ui/**/*.stories.*'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          patterns: [{ regex: `^(?!(?:${toolkitImports.join('|')})$)`, caseSensitive: true, message: toolkitBoundaryMessage }],
        },
      ],
    },
  },
  {
    ignores: ['**/vite.config.*.timestamp*', '**/vitest.config.*.timestamp*'],
  },
];
