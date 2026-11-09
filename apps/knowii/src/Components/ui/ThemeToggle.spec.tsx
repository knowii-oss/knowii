import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { THEME_STORAGE_KEY } from '@knowii/common';
import { ThemeToggle } from './ThemeToggle';
import { getSavedTheme } from './theme';

describe('ThemeToggle', () => {
  beforeEach(() => {
    document.documentElement.setAttribute('data-theme', 'dark');
    const meta = document.createElement('meta');
    meta.setAttribute('name', 'theme-color');
    document.head.appendChild(meta);
    window.localStorage.clear();
  });

  afterEach(() => {
    document.head.querySelector('meta[name="theme-color"]')?.remove();
    document.documentElement.removeAttribute('data-theme');
  });

  it('switches between the dark and the light theme and saves the choice', async () => {
    render(<ThemeToggle />);
    expect(getSavedTheme()).toBeNull();

    await userEvent.click(screen.getByRole('button', { name: 'Switch to the light theme' }));

    expect(document.documentElement).toHaveAttribute('data-theme', 'light');
    expect(window.localStorage.getItem(THEME_STORAGE_KEY)).toBe('light');
    expect(document.head.querySelector('meta[name="theme-color"]')).toHaveAttribute('content', '#f5f7fa');
    expect(await screen.findByRole('button', { name: 'Switch to the dark theme' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Switch to the dark theme' }));
    expect(document.documentElement).toHaveAttribute('data-theme', 'dark');
    expect(getSavedTheme()).toBe('dark');
  });

  it('follows a theme change made elsewhere', async () => {
    render(<ThemeToggle />);
    document.documentElement.setAttribute('data-theme', 'light');
    await waitFor(() => expect(screen.getByRole('button', { name: 'Switch to the dark theme' })).toBeInTheDocument());
  });
});
