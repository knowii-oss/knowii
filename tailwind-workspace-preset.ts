import type { Config } from 'tailwindcss';

export default {
  content: [],
  // the NODE_ENV thing is for https://github.com/Acidic9/prettier-plugin-tailwind/issues/29
  mode: process.env.NODE_ENV ? 'jit' : undefined,
  darkMode: ['class', '[data-theme="dark"]'],
  theme: {
    colors: {
      white: '#FAFAFA',
      black: '#000000',
      primary: {
        DEFAULT: '#FF00D6',
        50: '#FFB8F4',
        100: '#FFA3F0',
        200: '#FF7AEA',
        300: '#FF52E3',
        400: '#FF29DD',
        500: '#FF00D6',
        600: '#C700A7',
        700: '#8F0078',
        800: '#570049',
        900: '#1F001A',
        950: '030002',
      },
      red: {
        DEFAULT: '#FA3643',
        50: '#FEE9EB',
        100: '#FED5D8',
        200: '#FDADB3',
        300: '#FC868D',
        400: '#FB5E68',
        500: '#FA3643',
        600: '#F20616',
        700: '#BB0511',
        800: '#84030C',
        900: '#4E0207',
        950: '#320105',
      },
      blue: {
        DEFAULT: '#3C74DB',
        50: '#D7E2F8',
        100: '#C6D6F4',
        200: '#A3BEEE',
        300: '#81A5E8',
        400: '#5E8DE1',
        500: '#3C74DB',
        600: '#2359BC',
        700: '#1A428D',
        800: '#112C5D',
        900: '#09162E',
        950: '#040B16',
      },
      green: {
        DEFAULT: '#05B560',
        50: '#76FBBB',
        100: '#63FBB1',
        200: '#3BFA9D',
        300: '#13F88A',
        400: '#06DD75',
        500: '#05B560',
        600: '#037E43',
        700: '#024826',
        800: '#001109',
        900: '#000000',
        950: '#000000',
      },
      gray: {
        DEFAULT: '#9AA1A6',
        50: '#FCFCFC',
        100: '#F1F2F2',
        200: '#DBDEDF',
        300: '#C5C9CC',
        400: '#B0B5B9',
        500: '#9AA1A6',
        600: '#7C858C',
        700: '#61696E',
        800: '#474D51',
        900: '#2D3033',
        950: '#202224',
      },

      orange: {
        DEFAULT: '#E87632',
        50: '#FBE4D7',
        100: '#F8D8C5',
        200: '#F4C0A0',
        300: '#F0A77B',
        400: '#EC8F57',
        500: '#E87632',
        600: '#CB5A17',
        700: '#994411',
        800: '#662D0B',
        900: '#341706',
        950: '#1B0C03',
      },
      yellow: {
        DEFAULT: '#C5883A',
        50: '#EFDEC8',
        100: '#EAD4B8',
        200: '#E1C199',
        300: '#D8AE79',
        400: '#CE9B5A',
        500: '#C5883A',
        600: '#9A6A2D',
        700: '#6E4C20',
        800: '#432E14',
        900: '#181007',
        950: '#020101',
      },
      steel: {
        DEFAULT: '#6684CA',
        50: '#EEF2F9',
        100: '#DFE5F4',
        200: '#C1CDE9',
        300: '#A3B5DF',
        400: '#849CD4',
        500: '#6684CA',
        600: '#4064B8',
        700: '#314D8E',
        800: '#233765',
        900: '#14203B',
        950: '#0D1526',
      },
    },
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
