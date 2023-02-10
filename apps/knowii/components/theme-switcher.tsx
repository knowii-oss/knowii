import React from 'react';
import { useTheme } from 'next-themes';
import { FaMoon, FaSun } from 'react-icons/fa';

/**
 * Switch between light and dark mode
 * @constructor
 */
const ThemeSwitcher = () => {
  const { theme, setTheme } = useTheme();

  return (
    <>
      {/*The current theme is: {theme}*/}
      <button
        aria-label="Toggle Dark Mode"
        type="button"
        onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
      >
        {theme === 'dark' ? <FaSun /> : <FaMoon />}
      </button>
    </>
  );
};

export default ThemeSwitcher;
