import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { FaBan, FaCircleCheck, FaCircleInfo, FaLock, FaPen, FaTowerBroadcast, FaTriangleExclamation } from 'react-icons/fa6';
import type { BadgeColor } from './Badge';
import { BADGE_COLORS, Badge } from './Badge';

/**
 * A short text label on an item: its state ("Draft", "Cancelled", "Happening now") or its kind. Use it next to the
 * title of the item. The text carries the meaning alone: the variant only supports it with the color of the border
 * and of the icon, and the text keeps the color of all text.
 *
 * Not a button and not a link: for an action, use a `Button`. Not the status of a save (`SaveStatus`).
 *
 * A label that people name (a member tag) takes a `color` of the palette of eight in place of the variant: the border
 * and a small mark before the text. The color only tells the labels apart.
 *
 * Accessibility: a `span` with its text, without a role and without the focus. The icon and the mark of the color are
 * hidden from screen readers. Never give a meaning by the variant or the color alone.
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

const COLOR_NAMES: Record<BadgeColor, string> = {
  gray: 'Volunteer',
  red: 'Board',
  orange: 'Speaker',
  yellow: 'Early bird',
  green: 'Onboarded',
  teal: 'Mentor',
  blue: 'Staff',
  purple: 'Founder',
};

/**
 * One color of the palette: the border and the mark take it, the text keeps the color of all text
 */
function colorStory(color: BadgeColor, theme: 'dark' | 'light'): Story {
  return {
    args: { color, children: COLOR_NAMES[color] },
    globals: { theme },
    play: async ({ canvasElement }) => {
      const text = within(canvasElement).getByText(COLOR_NAMES[color]);
      const badge = text.parentElement;
      await expect(badge).toHaveClass(`border-tag-${color}`);
      const mark = badge?.querySelector('[data-badge-mark]');
      await expect(mark).toHaveClass(`bg-tag-${color}`);
      await expect(mark).toHaveAttribute('aria-hidden', 'true');
      // The mark adds no word: the text says it all
      await expect(badge).toHaveTextContent(new RegExp(`^${COLOR_NAMES[color]}$`));
      await expect(text).toHaveClass('min-w-0');
    },
  };
}

export const ColorGray: Story = colorStory('gray', 'dark');
export const ColorRed: Story = colorStory('red', 'dark');
export const ColorOrange: Story = colorStory('orange', 'dark');
export const ColorYellow: Story = colorStory('yellow', 'dark');
export const ColorGreen: Story = colorStory('green', 'dark');
export const ColorTeal: Story = colorStory('teal', 'dark');
export const ColorBlue: Story = colorStory('blue', 'dark');
export const ColorPurple: Story = colorStory('purple', 'dark');
export const ColorGrayLight: Story = colorStory('gray', 'light');
export const ColorRedLight: Story = colorStory('red', 'light');
export const ColorOrangeLight: Story = colorStory('orange', 'light');
export const ColorYellowLight: Story = colorStory('yellow', 'light');
export const ColorGreenLight: Story = colorStory('green', 'light');
export const ColorTealLight: Story = colorStory('teal', 'light');
export const ColorBlueLight: Story = colorStory('blue', 'light');
export const ColorPurpleLight: Story = colorStory('purple', 'light');

/**
 * The eight colors on the four surfaces of the theme (switch the theme in the toolbar). A label with an icon keeps the
 * mark of its color first, and the icon in the color of secondary text.
 */
export const PaletteOnEverySurface: Story = {
  render: () => (
    <div className="grid gap-2">
      {(['bg-background', 'bg-surface', 'bg-surface-elevated', 'bg-well'] as const).map((surface) => (
        <p key={surface} className={`flex flex-wrap items-center gap-2 rounded-md p-3 ${surface}`} data-surface={surface}>
          {BADGE_COLORS.map((color) => (
            <Badge key={color} color={color}>
              {COLOR_NAMES[color]}
            </Badge>
          ))}
          <Badge color="green" icon={<FaLock />}>
            Managers only label
          </Badge>
        </p>
      ))}
    </div>
  ),
  play: async ({ canvasElement }) => {
    const rows = canvasElement.querySelectorAll('[data-surface]');
    await expect(rows).toHaveLength(4);
    for (const row of rows) {
      await expect(row.querySelectorAll('[data-badge-mark]')).toHaveLength(BADGE_COLORS.length + 1);
    }
    const locked = within(canvasElement).getAllByText('Managers only label')[0];
    await expect(locked?.parentElement?.querySelector('svg')?.closest('[aria-hidden="true"]')).toHaveClass('text-muted');
  },
};
