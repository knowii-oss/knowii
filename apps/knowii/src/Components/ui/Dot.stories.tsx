import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { Avatar } from './Avatar';
import { Dot } from './Dot';

/**
 * A small round mark next to an item: `new` for a space with content that the member did not see, `online` for a
 * member who is here now. Put it in a flex row (`className="shrink-0"`), or in the corner of a `relative` box
 * (`className="absolute right-0 bottom-0"`).
 *
 * Never a control, and never the only sign of a state: a color and a shape do not reach every person. Not a count
 * (`CountBadge`) and not a state in words (`Badge`).
 *
 * Accessibility: the round shape is always hidden from screen readers. With `label`, the text sits next to it for
 * screen readers only and becomes a part of the name of the link around it (", New content"). Without `label` the dot
 * is decoration: the item must then say the same thing in words elsewhere (the heading "Online now" of a list).
 */
const meta = {
  component: Dot,
  args: {
    tone: 'new',
  },
} satisfies Meta<typeof Dot>;

export default meta;
type Story = StoryObj<typeof meta>;

/**
 * The `new` tone alone: decoration, with nothing for a screen reader
 */
export const New: Story = {
  play: async ({ canvasElement }) => {
    const dot = canvasElement.querySelector('span');
    await expect(dot).toHaveAttribute('aria-hidden', 'true');
    await expect(dot).toBeEmptyDOMElement();
    // Nothing else is in the page: no text, no role, and the keyboard does not stop on it
    await expect(canvasElement).toHaveTextContent(/^$/);
    await expect(canvasElement.querySelectorAll('[role], [tabindex], [aria-label]')).toHaveLength(0);
    await userEvent.tab();
    await expect(canvasElement.ownerDocument.body).toHaveFocus();
  },
};

/**
 * The `online` tone alone
 */
export const Online: Story = {
  args: { tone: 'online' },
  play: async ({ canvasElement }) => {
    const dot = canvasElement.querySelector('span');
    await expect(dot).toHaveAttribute('aria-hidden', 'true');
    await expect(canvasElement).toHaveTextContent(/^$/);
  },
};

/**
 * In the row of a link, with its text for screen readers: the text is a part of the name of the link
 */
export const NewWithLabelInALink: Story = {
  args: { label: ', New content', className: 'shrink-0' },
  render: (args) => (
    <a
      href="#general"
      className="flex max-w-xs items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      <span className="min-w-0 grow truncate">General</span>
      <Dot {...args} />
    </a>
  ),
  play: async ({ canvasElement }) => {
    const link = within(canvasElement).getByRole('link', { name: 'General, New content' });
    // The shape itself stays hidden: only the text is for screen readers
    await expect(link.querySelector('.rounded-full')).toHaveAttribute('aria-hidden', 'true');
    await userEvent.tab();
    await expect(link).toHaveFocus();
  },
};

/**
 * On the corner of an avatar, in a list whose heading says "Online now": the dot adds nothing for a screen reader
 */
export const OnlineInTheCornerOfAnAvatar: Story = {
  args: { tone: 'online', className: 'absolute right-0 bottom-0' },
  render: (args) => (
    <a
      href="#rose"
      className="relative inline-flex rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      <Avatar name="Rose Gardener" size="sm" />
      <Dot {...args} />
      <span className="sr-only">Rose Gardener</span>
    </a>
  ),
  play: async ({ canvasElement }) => {
    const link = within(canvasElement).getByRole('link', { name: 'Rose Gardener' });
    const dot = link.querySelector('.bg-success');
    await expect(dot).toHaveAttribute('aria-hidden', 'true');
    await expect(dot).toBeEmptyDOMElement();
    await expect(dot).toHaveClass('absolute');
  },
};
