import { extendTheme, ThemeOverride, UseToastOptions, withDefaultColorScheme } from '@chakra-ui/react';
import { theme } from './theme';
import { THEME_LIGHT } from '@knowii/common';
//import { THEME_LIGHT } from '@knowii/common';

export const customTheme = extendTheme(
  {
    initialColorMode: THEME_LIGHT,
    useSystemColorMode: false, // Force the default theme
    fonts: {
      ...theme.fonts,
    },
    colors: {
      ...theme.colors,
    },
    components: {
      Button: {
        baseStyle: {
          rounded: 'md',
        },
        variants: {
          outline: () => ({
            border: '2px solid',
          }),
        },
      },
    },
  } as ThemeOverride,
  withDefaultColorScheme({ colorScheme: 'primary', components: ['Button'] }),
);

export const defaultToastOptions = {
  position: 'top',
  duration: 5000,
  isClosable: true,
} as UseToastOptions;
