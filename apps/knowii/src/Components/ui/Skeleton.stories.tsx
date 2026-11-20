import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { Skeleton } from './Skeleton';

/**
 * The grey lines that hold the place of a list while it loads, so that the page does not jump when the list comes.
 * Use it for the first load of a list whose shape is known: give the number of rows and of lines that the list will
 * show, and the round shape when its items start with an avatar.
 *
 * Not for a short wait without a known shape (`Spinner`), not for a button that waits (the `loading` of `Button`), and
 * not for an empty list (`EmptyState`). Do not put it in a `ul`: it is a `div`. Do not put it inside an element with
 * `aria-busy`: assistive technology may ignore what a busy element holds.
 *
 * Accessibility: a polite live region (role="status") whose only text is the `label`, for screen readers ("Loading
 * your notifications": say what loads). The shapes are hidden from screen readers and nothing takes the focus. The
 * pulse runs only when the person did not ask for less motion, and each shape has a border in forced colors.
 */
const meta = {
  component: Skeleton,
  args: {
    label: 'Loading the posts',
  },
} satisfies Meta<typeof Skeleton>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * One row with two lines
 */
export const OneRow: Story = {
  play: async ({ canvasElement }) => {
    const status = within(canvasElement).getByRole('status');
    // The label is the only text: the shapes add nothing to it
    await expect(status).toHaveTextContent(/^Loading the posts$/);
    const rows = status.querySelectorAll(':scope > div');
    await expect(rows).toHaveLength(1);
    for (const row of rows) {
      await expect(row).toHaveAttribute('aria-hidden', 'true');
      await expect(row.textContent).toBe('');
    }
    // Each shape pulses only without "reduce motion", and keeps a border in forced colors
    const shapes = status.querySelectorAll('.h-3');
    await expect(shapes).toHaveLength(2);
    for (const shape of shapes) {
      await expect(shape).toHaveClass('motion-safe:animate-pulse', 'forced-colors:border');
    }
    // Nothing takes the focus
    await userEvent.tab();
    await expect(canvasElement.ownerDocument.body).toHaveFocus();
  },
};

/**
 * A round shape before the lines, for a list whose items start with an avatar
 */
export const WithAvatar: Story = {
  args: { avatar: true, label: 'Loading the members' },
  play: async ({ canvasElement }) => {
    const status = within(canvasElement).getByRole('status');
    await expect(status).toHaveTextContent(/^Loading the members$/);
    await expect(status.querySelector('.rounded-full')?.closest('[aria-hidden="true"]')).not.toBeNull();
    // The round shape and the two lines: each one pulses only without "reduce motion", and keeps a border in forced colors
    const shapes = status.querySelectorAll('.rounded-full, .h-3');
    await expect(shapes).toHaveLength(3);
    for (const shape of shapes) {
      await expect(shape).toHaveClass('motion-safe:animate-pulse', 'forced-colors:border');
      await expect(shape).not.toHaveClass('animate-pulse');
    }
  },
};

export const OneLine: Story = {
  args: { lines: 1, avatar: true, label: 'Loading Following' },
  play: async ({ canvasElement }) => {
    const status = within(canvasElement).getByRole('status');
    await expect(status).toHaveTextContent(/^Loading Following$/);
    await expect(status.querySelectorAll('.h-3')).toHaveLength(1);
  },
};

/**
 * Four rows, as the notifications panel shows them while it loads
 */
export const FourRows: Story = {
  args: { rows: 4, avatar: true, label: 'Loading your notifications' },
  play: async ({ canvasElement }) => {
    const status = within(canvasElement).getByRole('status');
    await expect(status).toHaveTextContent(/^Loading your notifications$/);
    await expect(status.querySelectorAll(':scope > [aria-hidden="true"]')).toHaveLength(4);
    // Every shape of every row
    const shapes = status.querySelectorAll('.rounded-full, .h-3');
    await expect(shapes).toHaveLength(12);
    for (const shape of shapes) {
      await expect(shape).toHaveClass('motion-safe:animate-pulse', 'forced-colors:border');
    }
    // The status is in no busy element
    await expect(status.closest('[aria-busy]')).toBeNull();
  },
};

/**
 * On a phone the lines take the width of the screen: the page does not scroll sideways
 */
export const FourRowsOnPhone: Story = {
  args: { rows: 4, avatar: true, label: 'Loading your notifications' },
  globals: { viewport: { value: 'phone', isRotated: false } },
};
