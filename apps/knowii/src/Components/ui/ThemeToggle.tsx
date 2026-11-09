import { FaMoon, FaSun } from 'react-icons/fa';
import { Button } from './Button';
import { useTheme } from './theme';

interface ThemeToggleProps {
  className?: string;
}

/**
 * Switches between the dark and the light theme. The choice is saved in the browser.
 */
export function ThemeToggle({ className }: ThemeToggleProps) {
  const { theme, toggleTheme } = useTheme();
  const label = theme === 'dark' ? 'Switch to the light theme' : 'Switch to the dark theme';

  return (
    <Button variant="secondary" iconOnly aria-label={label} title={label} onClick={toggleTheme} className={className}>
      {theme === 'dark' ? <FaSun aria-hidden="true" className="text-warning" /> : <FaMoon aria-hidden="true" />}
    </Button>
  );
}
