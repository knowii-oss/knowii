import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { FaBell } from 'react-icons/fa6';
import { Button } from './Button';
import { CountBadge } from './CountBadge';

/**
 * The number of new things on a link or on a button: the messages that a member did not read in a navigation
 * ("3 unread"), the count of the bell. Put it inside the link or the button that it counts for. A count of zero
 * or less renders nothing. With `max`, a larger number shows as the maximum and "+" ("9+").
 *
 * Not a label (`Badge`: a state in words), not a state, and never a control.
 *
 * Accessibility: a `span`, without a role and without the focus. With `label`, a screen reader hears the number and the
 * words ("3 unread") as a part of the name of the link around it. Without `label` the badge is hidden from screen
 * readers: the control around it must then hold the number in its own name ("Notifications, 3 new").
 */
const meta = {
  component: CountBadge,
  args: {
    count: 1,
    label: 'unread',
  },
} satisfies Meta<typeof CountBadge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const One: Story = {
  play: async ({ canvasElement }) => {
    const badge = within(canvasElement).getByText('1');
    await expect(badge.tagName).toBe('SPAN');
    await expect(badge).toHaveTextContent(/^1 unread$/);
    // Never a control: no role, and the keyboard does not stop on it
    await expect(badge.closest('button, a, [role], [tabindex]')).toBeNull();
  },
};

export const TwoDigits: Story = {
  args: { count: 42 },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText('42')).toHaveTextContent(/^42 unread$/);
  },
};

/**
 * At the maximum, the number shows as it is
 */
export const AtTheMaximum: Story = {
  args: { count: 9, max: 9 },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText('9')).toHaveTextContent(/^9 unread$/);
  },
};

/**
 * Above the maximum, the text is the maximum and "+": 10 and 120 both show "9+"
 */
export const AboveTheMaximum: Story = {
  args: { count: 10, max: 9 },
  render: (args) => (
    <p className="flex items-center gap-2">
      <CountBadge {...args} />
      <CountBadge {...args} count={120} />
    </p>
  ),
  play: async ({ canvasElement }) => {
    const canvas = within(canvasElement);
    const badges = canvas.getAllByText('9+');
    await expect(badges).toHaveLength(2);
    for (const badge of badges) {
      await expect(badge).toHaveTextContent(/^9\+ unread$/);
    }
    await expect(canvas.queryByText('10')).toBeNull();
    await expect(canvas.queryByText('120')).toBeNull();
  },
};

/**
 * A count of zero renders nothing
 */
export const Zero: Story = {
  args: { count: 0 },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('span')).toBeNull();
    await expect(canvasElement).toHaveTextContent(/^$/);
  },
};

/**
 * A count below zero renders nothing, as zero does
 */
export const BelowZero: Story = {
  args: { count: -1 },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('span')).toBeNull();
    await expect(canvasElement).toHaveTextContent(/^$/);
  },
};

/**
 * In the row of a link: the number and the words are a part of the name of the link
 */
export const WithWordsInALink: Story = {
  args: { count: 3 },
  render: (args) => (
    <a
      href="#general"
      className="flex max-w-xs items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-overlay/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      <span className="min-w-0 grow truncate">General</span> <CountBadge {...args} />
    </a>
  ),
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByRole('link', { name: 'General 3 unread' })).toBeInTheDocument();
  },
};

/**
 * Without `label` the badge is hidden from screen readers: it adds nothing to the name of the button. The words of this
 * button are its name, and the name stays "Notifications" with the badge inside. So a control with a hidden badge must
 * hold the number in its own name, as the icon button of the next story does.
 */
export const HiddenInAButton: Story = {
  args: { count: 3, label: undefined },
  render: (args) => (
    <Button variant="secondary">
      Notifications <CountBadge {...args} />
    </Button>
  ),
  play: async ({ canvasElement }) => {
    // The name comes from the content of the button, and the badge is not a part of it
    const button = within(canvasElement).getByRole('button', { name: 'Notifications' });
    await expect(button).toHaveAccessibleName('Notifications');
    const badge = within(button).getByText('3');
    await expect(button).toContainElement(badge);
    await expect(badge).toHaveAttribute('aria-hidden', 'true');
    // The badge holds the number alone, without words for screen readers
    await expect(badge).toHaveTextContent(/^3$/);
    await expect(badge.querySelector('.sr-only')).toBeNull();
  },
};

/**
 * On the corner of an icon button of the header, with a maximum. The button is `relative`, and its name holds the
 * number.
 */
export const InTheCornerOfAnIconButton: Story = {
  args: { count: 12, max: 9, label: undefined, placement: 'corner' },
  render: (args) => (
    <div className="flex bg-well p-4">
      <Button variant="secondary" iconOnly aria-label="Notifications, 9 or more new" className="relative">
        <FaBell aria-hidden="true" />
        <CountBadge {...args} />
      </Button>
    </div>
  ),
  play: async ({ canvasElement }) => {
    const button = within(canvasElement).getByRole('button', { name: 'Notifications, 9 or more new' });
    const badge = within(button).getByText('9+');
    await expect(badge).toHaveAttribute('aria-hidden', 'true');
    await expect(badge).toHaveClass('absolute');
  },
};
