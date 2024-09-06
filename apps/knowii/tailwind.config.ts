import type { Config } from 'tailwindcss';
const { createGlobPatternsForDependencies } = require('@nx/react/tailwind');
const { join } = require('path');
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

import { theme } from './src/theme.mjs';

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    // Laravel and Jetstream files
    '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    '../../vendor/laravel/jetstream/**/*.blade.php',
    '../../storage/framework/views/*.php',
    '../../resources/views/**/*.blade.php',
    join(__dirname, '{src,pages,components,app}/**/*!(*.stories|*.spec).{ts,tsx,html}'),
    ...createGlobPatternsForDependencies(__dirname),
  ],
  darkMode: ['class', '[data-theme="dark"]'],
  theme: {
    colors: theme.colors,
    extend: {
      fontFamily: {
        app: [
          'ui-sans-serif',
          'system-ui',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Oxygen',
          'Ubuntu',
          'Cantarell',
          'Fira Sans',
          'Droid Sans',
          'Noto Sans',
          'sans-serif',
        ],
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },
      screens: {
        sm: { min: '600px' },
        md: { min: '960px' },
        lg: { min: '1280px' },
        xg: { min: '1600px' },
        xl: { min: '1920px' },
        '2xl': { min: '2440px' },
        print: { raw: 'print' },
        portrait: { raw: '(orientation: portrait)' },
      },
    },
  },
  plugins: [forms, typography],
} satisfies Config;
