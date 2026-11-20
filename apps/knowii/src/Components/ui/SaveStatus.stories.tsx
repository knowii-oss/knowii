import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { SaveStatus } from './SaveStatus';

/**
 * The status of a setting that saves at once, next to its control (a `Switch`, a choice of radios): "Saving", then
 * "Saved", or the error when the save fails, and then the control shows the old value again. Keep it in the page also
 * when it is empty (idle): a screen reader reads only the changes of a region that was there before. `savedText` says
 * the value that was saved ("Saved: Yes") when the control has more than two values.
 *
 * Accessibility: a polite live region (role="status"). It keeps the height of one line, so the page does not move. The
 * icons are hidden from screen readers: the text says it all.
 */
const meta = {
  component: SaveStatus,
  args: {
    state: { status: 'idle' },
  },
} satisfies Meta<typeof SaveStatus>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Idle: Story = {
  play: async ({ canvasElement }) => {
    const status = within(canvasElement).getByRole('status');
    await expect(status).toBeEmptyDOMElement();
    await expect(status).toHaveAttribute('aria-live', 'polite');
  },
};

export const Saving: Story = {
  args: { state: { status: 'saving' } },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('Saving');
  },
};

export const Saved: Story = {
  args: { state: { status: 'saved' } },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('Saved');
  },
};

export const SavedWithTheValue: Story = {
  args: { state: { status: 'saved' }, savedText: 'Saved: Yes' },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('Saved: Yes');
  },
};

export const Failed: Story = {
  args: { state: { status: 'error', message: 'Failed to save the indexing setting.' } },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('status')).toHaveTextContent('Failed to save the indexing setting.');
  },
};

export const FailedWithLongText: Story = {
  args: {
    state: {
      status: 'error',
      message: 'You can no longer change the settings of this community: ask an admin to give you the right again.',
    },
  },
};
