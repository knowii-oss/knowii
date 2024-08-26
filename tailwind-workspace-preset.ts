import type { Config } from 'tailwindcss';
import { theme } from './libs/client-ui/src/lib/theme';

export default {
  content: [],
  // the NODE_ENV thing is for https://github.com/Acidic9/prettier-plugin-tailwind/issues/29
  mode: process.env.NODE_ENV ? 'jit' : undefined,
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
    variants: {
      extend: {},
    },
    plugins: [],
  },
} satisfies Config;
