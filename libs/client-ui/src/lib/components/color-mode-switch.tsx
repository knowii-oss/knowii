import { IconButton, useColorMode } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import { FaMoon, FaSun } from 'react-icons/fa';
import { useTheme } from 'next-themes';
import { I18N_TRANSLATIONS_COMMON, THEME_DARK, THEME_LIGHT } from '@knowii/common';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface ColorModeSwitchProps {}

/**
 * Switch between light and dark mode
 * @constructor
 */
export function ColorModeSwitch(_props: ColorModeSwitchProps) {
  const { t } = useTranslation(I18N_TRANSLATIONS_COMMON);
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
      aria-label={colorMode === THEME_LIGHT ? t('colorMode.dark') : t('colorMode.light')}
      icon={colorMode === THEME_LIGHT ? <FaMoon className="hover:text-gray-700" /> : <FaSun className="hover:text-yellow-400" />}
      onClick={toggleThemeAndColorMode}
      variant="ghost"
    ></IconButton>
  );
}

export default ColorModeSwitch;
