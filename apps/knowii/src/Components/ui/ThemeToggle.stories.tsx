import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, waitFor, within } from 'storybook/test';
import { THEME_STORAGE_KEY } from '@knowii/common';
import { ThemeToggle } from './ThemeToggle';
import { getSavedTheme } from './theme';

/**
 * Switches between the dark and the light theme, in the header of the application. The choice is saved in the browser
 * and wins over the preference of the system. Use it once per page.
 *
 * Accessibility: a button with an icon only. Its name says what a press does ("Switch to the light theme"), and it is
 * also its tooltip. The icon is hidden from screen readers. The button follows a change of the theme made elsewhere
 * (another toggle, the toolbar of Storybook).
 */
const meta = {
  component: ThemeToggle,
  // A press saves the theme in the browser (localStorage) and sets it on <html>: the story gives both back after it,
  // so that the toolbar of Storybook and the next story keep their theme
  beforeEach: () => {
    const savedBefore = window.localStorage.getItem(THEME_STORAGE_KEY);
    const themeBefore = document.documentElement.getAttribute('data-theme');
    return () => {
      if (savedBefore === null) {
        window.localStorage.removeItem(THEME_STORAGE_KEY);
      } else {
        window.localStorage.setItem(THEME_STORAGE_KEY, savedBefore);
      }
      if (themeBefore !== null) {
        document.documentElement.setAttribute('data-theme', themeBefore);
      }
    };
  },
} satisfies Meta<typeof ThemeToggle>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The keyboard path in the dark theme: Enter switches to the light theme and saves the choice, the button keeps the
 * focus and its name changes, and a second press goes back
 */
export const InTheDarkTheme: Story = {
  globals: { theme: 'dark' },
  play: async ({ canvasElement, step }) => {
    const canvas = within(canvasElement);
    const html = canvasElement.ownerDocument.documentElement;
    const button = canvas.getByRole('button', { name: 'Switch to the light theme' });
    await expect(button).toHaveAttribute('title', 'Switch to the light theme');

    await step('Enter switches to the light theme and saves it', async () => {
      await userEvent.tab();
      await expect(button).toHaveFocus();
      await userEvent.keyboard('{Enter}');
      await expect(html).toHaveAttribute('data-theme', 'light');
      await expect(getSavedTheme()).toBe('light');
      const renamed = await canvas.findByRole('button', { name: 'Switch to the dark theme' });
      await expect(renamed).toHaveFocus();
    });

    await step('Space switches back to the dark theme', async () => {
      await userEvent.keyboard(' ');
      await expect(html).toHaveAttribute('data-theme', 'dark');
      await expect(getSavedTheme()).toBe('dark');
      await expect(await canvas.findByRole('button', { name: 'Switch to the light theme' })).toHaveFocus();
    });
  },
};

export const InTheLightTheme: Story = {
  globals: { theme: 'light' },
  play: async ({ canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Switch to the dark theme' });
    await expect(button.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
  },
};

/**
 * Another part of the page changes the theme: the button follows it
 */
export const FollowsAChangeElsewhere: Story = {
  globals: { theme: 'dark' },
  play: async ({ canvasElement }) => {
    canvasElement.ownerDocument.documentElement.setAttribute('data-theme', 'light');
    await waitFor(() => expect(within(canvasElement).getByRole('button', { name: 'Switch to the dark theme' })).toBeInTheDocument());
  },
};
