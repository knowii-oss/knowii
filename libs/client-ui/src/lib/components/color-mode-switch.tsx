import { IconButton, useColorMode } from '@chakra-ui/react';
import { FaMoon, FaSun } from 'react-icons/fa';
import { useTheme } from 'next-themes';
import { useTranslations } from 'next-intl';
import { THEME_DARK, THEME_LIGHT } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface ColorModeSwitchProps {}

/**
 * Switch between light and dark mode
 * @constructor
 */
export function ColorModeSwitch(_props: ColorModeSwitchProps) {
  const t = useTranslations('colorModeSwitch');

  // ChakraUI color mode
  const { colorMode, toggleColorMode } = useColorMode();

  const toggleThemeAndColorMode = () => {
    toggleColorMode();
    setTheme(theme === THEME_DARK ? THEME_LIGHT : THEME_DARK);
  };

  // Tailwind theme
  const { theme, setTheme } = useTheme();

  return (
    <IconButton
      size="sm"
      aria-label={colorMode === THEME_LIGHT ? t('dark') : t('light')}
      icon={colorMode === THEME_LIGHT ? <FaMoon className="hover:text-gray-700" /> : <FaSun className="hover:text-yellow-400" />}
      onClick={toggleThemeAndColorMode}
      variant="ghost"
    ></IconButton>
  );
}

export default ColorModeSwitch;
