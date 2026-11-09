import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { THEME_BACKGROUND_COLORS, THEME_STORAGE_KEY } from '@knowii/common';

export type Theme = 'light' | 'dark';

const THEME_ATTRIBUTE = 'data-theme';
const LIGHT_SCHEME_QUERY = '(prefers-color-scheme: light)';

/**
 * Active theme, read from <html data-theme>. The inline script in app.blade.php sets it before the first paint.
 */
export function getActiveTheme(): Theme {
  return document.documentElement.getAttribute(THEME_ATTRIBUTE) === 'light' ? 'light' : 'dark';
}

/**
 * Theme saved by the user, or null when the user did not choose (the system preference applies)
 */
export function getSavedTheme(): Theme | null {
  try {
    const saved = window.localStorage.getItem(THEME_STORAGE_KEY);
    return saved === 'light' || saved === 'dark' ? saved : null;
  } catch {
    return null;
  }
}

function getSystemTheme(): Theme {
  return window.matchMedia?.(LIGHT_SCHEME_QUERY).matches ? 'light' : 'dark';
}

function syncThemeColorMeta(theme: Theme): void {
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', THEME_BACKGROUND_COLORS[theme]);
}

/**
 * Apply a theme. With persist (the default), the choice is saved and wins over the system preference.
 */
export function applyTheme(theme: Theme, persist = true): void {
  document.documentElement.setAttribute(THEME_ATTRIBUTE, theme);
  syncThemeColorMeta(theme);
  if (persist) {
    try {
      window.localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch {
      // Storage not available: the choice lasts until the next page load
    }
  }
}

function subscribe(onChange: () => void): () => void {
  const observer = new MutationObserver(onChange);
  observer.observe(document.documentElement, { attributes: true, attributeFilter: [THEME_ATTRIBUTE] });
  return () => observer.disconnect();
}

/**
 * Current theme and a function to switch it. Follows the system preference while the user has not chosen a theme.
 */
export function useTheme(): { theme: Theme; setTheme: (theme: Theme) => void; toggleTheme: () => void } {
  const theme = useSyncExternalStore<Theme>(subscribe, getActiveTheme, () => 'dark');

  useEffect(() => {
    syncThemeColorMeta(getActiveTheme());

    const media = window.matchMedia?.(LIGHT_SCHEME_QUERY);
    if (!media) {
      return;
    }
    const onSystemChange = () => {
      if (getSavedTheme() === null) {
        applyTheme(getSystemTheme(), false);
      }
    };
    media.addEventListener('change', onSystemChange);
    return () => media.removeEventListener('change', onSystemChange);
  }, []);

  const setTheme = useCallback((newTheme: Theme) => applyTheme(newTheme), []);
  const toggleTheme = useCallback(() => applyTheme(getActiveTheme() === 'dark' ? 'light' : 'dark'), []);

  return { theme, setTheme, toggleTheme };
}
