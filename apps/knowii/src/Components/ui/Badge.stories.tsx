import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { FaBan, FaCircleCheck, FaCircleInfo, FaPen, FaTowerBroadcast, FaTriangleExclamation } from 'react-icons/fa6';
import { Badge } from './Badge';

/**
 * A short text label on an item: its state ("Draft", "Cancelled", "Happening now") or its kind. Use it next to the
 * title of the item. The text carries the meaning alone: the variant only supports it with the color of the border
 * and of the icon, and the text keeps the color of all text.
 *
 * Not a button and not a link: for an action, use a `Button`. Not the status of a save (`SaveStatus`).
 *
 * Accessibility: a `span` with its text, without a role and without the focus. The icon is hidden from screen readers.
 * Never give a meaning by the variant alone.
 */
const meta = {
  component: Badge,
  args: {
    children: 'Draft',
  },
} satisfies Meta<typeof Badge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Neutral: Story = {
  play: async ({ canvasElement }) => {
    const badge = within(canvasElement).getByText('Draft');
    // A span, never a button or a link: the keyboard does not stop on it
    await expect(badge.closest('button, a, [role], [tabindex]')).toBeNull();
    await expect(badge.tagName).toBe('SPAN');
    await userEvent.tab();
    await expect(canvasElement.ownerDocument.body).toHaveFocus();
  },
};

export const Info: Story = {
  args: { variant: 'info', children: 'Online' },
};

export const Success: Story = {
  args: { variant: 'success', children: 'You are going' },
};

export const Warning: Story = {
  args: { variant: 'warning', children: 'Starts in 10 minutes' },
};

export const Danger: Story = {
  args: { variant: 'danger', children: 'Cancelled' },
};

/**
 * The icon comes before the text and is hidden from screen readers: the text says it all
 */
export const WithIcon: Story = {
  args: { variant: 'success', icon: <FaTowerBroadcast />, children: 'Happening now' },
  play: async ({ canvasElement }) => {
    const text = within(canvasElement).getByText('Happening now');
    const icon = text.parentElement?.querySelector('svg');
    await expect(icon?.closest('[aria-hidden="true"]')).not.toBeNull();
    await expect(text.parentElement).toHaveTextContent(/^Happening now$/);
  },
};

/**
 * Every variant with an icon, as a list of items shows them
 */
export const EveryVariantWithIcon: Story = {
  render: () => (
    <p className="flex flex-wrap items-center gap-2">
      <Badge icon={<FaPen />}>Draft</Badge>
      <Badge variant="info" icon={<FaCircleInfo />}>
        Online
      </Badge>
      <Badge variant="success" icon={<FaCircleCheck />}>
        You are going
      </Badge>
      <Badge variant="warning" icon={<FaTriangleExclamation />}>
        Starts in 10 minutes
      </Badge>
      <Badge variant="danger" icon={<FaBan />}>
        Cancelled
      </Badge>
    </p>
  ),
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    for (const text of ['Draft', 'Online', 'You are going', 'Starts in 10 minutes', 'Cancelled']) {
      await expect(canvas.getByText(text)).toBeVisible();
    }
  },
};

/**
 * Next to the title of an item, as a page shows it
 */
export const NextToATitle: Story = {
  args: { variant: 'danger', children: 'Cancelled' },
  render: (args) => (
    <h3 className="flex flex-wrap items-center gap-2 text-lg font-bold">
      <span>Seed swap of the spring</span> <Badge {...args} />
    </h3>
  ),
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('heading', { name: 'Seed swap of the spring Cancelled' })).toBeInTheDocument();
  },
};

/**
 * A text that is too long for a label wraps on a phone: the page does not scroll sideways
 */
export const LongTextOnPhone: Story = {
  args: {
    variant: 'warning',
    icon: <FaTriangleExclamation />,
    children: 'Moved to the greenhouse of the school because of the rain of this week',
  },
  globals: { viewport: { value: 'phone', isRotated: false } },
};
