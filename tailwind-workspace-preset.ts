import type { Config } from 'tailwindcss';

import { spacing } from 'tailwindcss/defaultTheme';
import { theme } from './apps/knowii/theme';

export default {
  content: [],
  // the NODE_ENV thing is for https://github.com/Acidic9/prettier-plugin-tailwind/issues/29
  mode: process.env.NODE_ENV ? 'jit' : undefined,
  darkMode: ['class', '[data-theme="dark"]'],
  theme: {
    extend: {
      fontFamily: {
        knowii: [
          'Lato',
          'AvenirNext',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Oxygen',
          'Ubuntu',
          'Cantarell',
          'Fira Sans',
          'Droid Sans',
          'Helvetica Neue',
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
      colors: {
        ...theme.colors,
      },
    },
    typography: (theme: (color: string) => unknown) => ({
      DEFAULT: {
        css: {
          color: theme('colors.white'),
          a: {
            color: theme('colors.blue.500'),
            '&:hover': {
              color: theme('colors.blue.700'),
            },
            code: { color: theme('colors.blue.400') },
          },
          // 'h2,h3,h4': {
          //   'scroll-margin-top': spacing[32],
          // },
          code: { color: theme('colors.primary.500') },
          'blockquote p:first-of-type::before': false,
          'blockquote p:last-of-type::after': false,
        },
      },
      dark: {
        css: {
          color: theme('colors.gray.300'),
          a: {
            color: theme('colors.blue.400'),
            '&:hover': {
              color: theme('colors.blue.600'),
            },
            code: { color: theme('colors.blue.400') },
          },
          blockquote: {
            borderLeftColor: theme('colors.gray.700'),
            color: theme('colors.gray.300'),
          },
          'h2,h3,h4': {
            color: theme('colors.gray.100'),
            'scroll-margin-top': spacing[32],
          },
          hr: { borderColor: theme('colors.gray.700') },
          ol: {
            li: {
              '&:before': { color: theme('colors.gray.500') },
            },
          },
          ul: {
            li: {
              '&:before': { backgroundColor: theme('colors.gray.500') },
            },
          },
          strong: { color: theme('colors.gray.300') },
          thead: {
            color: theme('colors.gray.100'),
          },
          tbody: {
            tr: {
              borderBottomColor: theme('colors.gray.700'),
            },
          },
        },
      },
    }),
  },
  variants: {
    extend: {},
  },
  plugins: [require('@tailwindcss/typography')],
} satisfies Config;
