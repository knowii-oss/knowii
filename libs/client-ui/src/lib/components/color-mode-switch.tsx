import { IconButton, useColorMode } from '@chakra-ui/react';
import { useTranslation } from 'next-i18next';
import { FaMoon, FaSun } from 'react-icons/fa';
import { useTheme } from 'next-themes';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface ColorModeSwitchProps {}

/**
 * Switch between light and dark mode
 * @constructor
 */
export function ColorModeSwitch(_props: ColorModeSwitchProps) {
  const { t } = useTranslation('common');
  // ChakraUI color mode
  const { colorMode, toggleColorMode } = useColorMode();

  const toggleThemeAndColorMode = () => {
    toggleColorMode();
    setTheme(theme === 'dark' ? 'light' : 'dark');
  };

  // Tailwind theme
  const { theme, setTheme } = useTheme();

  return (
    <IconButton
      size="sm"
      aria-label={colorMode === 'light' ? t('colorMode.dark') : t('colorMode.light')}
      icon={colorMode === 'light' ? <FaMoon className="hover:text-gray-700" /> : <FaSun className="hover:text-yellow-400" />}
      onClick={toggleThemeAndColorMode}
      variant="ghost"
    ></IconButton>
  );
}

export default ColorModeSwitch;
