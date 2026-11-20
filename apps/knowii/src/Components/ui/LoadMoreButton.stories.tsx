import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fireEvent, fn, userEvent, within } from 'storybook/test';
import { LoadMoreButton } from './LoadMoreButton';

/**
 * The button under a list that loads its next part ("Load more", "Load older"), or above it for a list that grows
 * upward (the older messages of a chat). Show it only while the list has more. Use `size="sm"` in a panel or a dialog,
 * and `align="start"` when the list is a narrow column that starts at the edge.
 *
 * Not for the result: the page says what loaded in its own status region ("20 more loaded") and moves the focus to the
 * first new item. Not a link to another page (`LinkButton`).
 *
 * Accessibility: a `button` whose name is its words, or `aria-label` when the page has several of them ("Load more in
 * Following"). While it loads (`loading`) it has `aria-busy` and `aria-disabled`, keeps the focus, and a second click,
 * Enter or Space does nothing.
 */
const meta = {
  component: LoadMoreButton,
  args: {
    children: 'Load more',
    onClick: fn(),
  },
} satisfies Meta<typeof LoadMoreButton>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  play: async ({ args, canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Load more' });
    await expect(button).not.toHaveAttribute('aria-busy');
    await userEvent.tab();
    await expect(button).toHaveFocus();
    await userEvent.keyboard('{Enter}');
    await expect(args.onClick).toHaveBeenCalledOnce();
  },
};

/**
 * In a panel or a dialog
 */
export const Small: Story = {
  args: { size: 'sm', children: 'Load older messages' },
};

/**
 * At the start of the row, under a narrow list
 */
export const AtTheStart: Story = {
  args: { size: 'sm', align: 'start', children: 'Load older' },
  play: async ({ canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Load older' });
    await expect(button.parentElement).toHaveClass('justify-start');
  },
};

/**
 * The next part loads: the button shows a spinner, keeps the focus, and does nothing
 */
export const Busy: Story = {
  args: { loading: true },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', { name: /Load more/ });
    await expect(button).toBeEnabled();
    await expect(button).toHaveAttribute('aria-busy', 'true');
    await expect(button).toHaveAttribute('aria-disabled', 'true');
    await expect(canvas.getByRole('status')).toHaveTextContent('Loading');
    await userEvent.tab();
    await expect(button).toHaveFocus();
    // A click event sent to the button itself: the pointer cannot reach a busy button in a browser
    await fireEvent.click(button);
    await expect(args.onClick).not.toHaveBeenCalled();
    await userEvent.keyboard('{Enter}');
    await userEvent.keyboard(' ');
    await expect(args.onClick).not.toHaveBeenCalled();
    await expect(button).toHaveFocus();
  },
};

/**
 * A long text on a phone. The text of a button stays on one line: keep the words short.
 */
export const LongTextOnPhone: Story = {
  args: { children: 'Load more resources of the space' },
  globals: { viewport: { value: 'phone', isRotated: false } },
};

/**
 * A page with several lists gives each button its own name
 */
export const WithAriaLabel: Story = {
  args: { size: 'sm', align: 'start', 'aria-label': 'Load more in Following' },
  play: async ({ args, canvasElement }) => {
    const canvas = within(canvasElement);
    const button = canvas.getByRole('button', { name: 'Load more in Following' });
    await expect(button).toHaveTextContent('Load more');
    await userEvent.click(button);
    await expect(args.onClick).toHaveBeenCalledOnce();
  },
};
