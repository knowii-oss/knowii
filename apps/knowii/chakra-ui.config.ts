import { extendTheme, ThemeOverride, UseToastOptions, withDefaultColorScheme } from '@chakra-ui/react';
import { theme } from './theme';

export const customTheme = extendTheme(
  {
    initialColorMode: 'light',
    useSystemColorMode: true,
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
  withDefaultColorScheme({ colorScheme: 'primary', components: ['Button'] })
);

export const defaultToastOptions = {
  position: 'top',
  duration: 5000,
  isClosable: true,
} as UseToastOptions;
