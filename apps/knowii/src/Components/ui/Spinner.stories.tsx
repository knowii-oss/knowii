import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { Spinner } from './Spinner';

/**
 * A turning circle while a part of the page loads, when the wait is short and has no known length. For a button that
 * waits, use the `loading` of `Button`, not a spinner next to it. For a list that loads, prefer lines that show its
 * shape.
 *
 * Accessibility: a polite live region (role="status") with a text for screen readers ("Loading" by default; give a
 * `label` that says what loads). The circle is hidden from screen readers.
 */
const meta = {
  component: Spinner,
} satisfies Meta<typeof Spinner>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Medium: Story = {
  play: async ({ canvasElement }) => {
    const status = within(canvasElement).getByRole('status');
    await expect(status).toHaveTextContent('Loading');
    await expect(status.querySelector('[aria-hidden="true"]')).not.toBeNull();
  },
};

export const Small: Story = {
  args: { size: 'sm' },
};

export const Large: Story = {
  args: { size: 'lg' },
};

export const WithLabel: Story = {
  args: { label: 'Loading the seed list' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('Loading the seed list');
  },
};

/**
 * The label is only for screen readers: a long one does not change the look
 */
export const LongLabel: Story = {
  args: {
    size: 'lg',
    label: 'Loading the resources that the members of the community shared about the old seeds of tomatoes and beans',
  },
};
