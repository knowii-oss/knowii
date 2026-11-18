import type { ComponentProps } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { AccountTimeZoneProvider, useAccountTimeZone } from './AccountTimeZone';
import { RelativeTime } from './RelativeTime';

// The fixed "now" of the stories: the relative text is the same on every run and on every machine
const NOW = new Date('2026-11-15T10:00:00Z');

// The clock of the machine, kept when the file loads: each story gives it back (see the RelativeTime stories)
const MACHINE_NOW = Date.now;

// The date of the post in the stories: two hours before "now"
const POSTED_AT = '2026-11-15T08:00:00Z';

/**
 * What a component under the provider reads, as the notifications and the kept texts read it
 */
function ZoneOfTheAccount() {
  const zone = useAccountTimeZone();
  return (
    <p className="text-sm">{zone === null ? 'The account has no time zone: the zone of the browser applies.' : `Time zone: ${zone}`}</p>
  );
}

/**
 * A line of a post under the provider: the tooltip of the time shows the date and the hour in the zone of the account
 */
function PostLine() {
  return (
    <p className="text-sm text-muted">
      Posted <RelativeTime date={POSTED_AT} />
    </p>
  );
}

function timeElement(canvasElement: HTMLElement): HTMLElement {
  const time = canvasElement.querySelector('time');
  if (!time) {
    throw new Error('No time element');
  }
  return time;
}

/**
 * Gives the time zone of the account (#1476) to every time of the page. The layout of the application renders it once,
 * with the zone of the account (`null` when the account has none). A component reads it with `useAccountTimeZone()`;
 * `RelativeTime` shows its exact date and hour in that zone. Do not render a second one inside a page.
 *
 * Accessibility: it renders nothing. The text that uses the zone names it ("GMT+9"), so the hour is never ambiguous.
 */
const meta = {
  component: AccountTimeZoneProvider,
  render: (args) => (
    <AccountTimeZoneProvider {...args}>
      <div className="flex flex-col gap-2">
        <ZoneOfTheAccount />
        <PostLine />
      </div>
    </AccountTimeZoneProvider>
  ),
  args: {
    value: 'Asia/Tokyo',
  },
  // The stories fix "now" (date-fns reads it with Date.now), and give the clock of the machine back after the story
  beforeEach: () => {
    Date.now = () => NOW.getTime();
    return () => {
      Date.now = MACHINE_NOW;
    };
  },
} satisfies Meta<ComponentProps<typeof AccountTimeZoneProvider>>;

export default meta;
type Story = StoryObj<typeof meta>;

export const WithZone: Story = {
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText('Time zone: Asia/Tokyo')).toBeInTheDocument();
    const time = timeElement(canvasElement);
    await expect(time).toHaveTextContent('about 2 hours ago');
    await expect(time).toHaveAttribute('title', 'Nov 15, 2026, 5:00:00 PM GMT+9');
  },
};

export const OtherZone: Story = {
  args: { value: 'America/New_York' },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveAttribute('title', 'Nov 15, 2026, 3:00:00 AM EST');
  },
};

/**
 * The account has no zone: every time uses the zone of the browser, so the text of the tooltip depends on the machine
 */
export const WithoutZone: Story = {
  args: { value: null },
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText(/The account has no time zone/)).toBeInTheDocument();
    await expect(timeElement(canvasElement)).toHaveAttribute('title', expect.stringMatching(/2026, \d+:\d\d:00 (AM|PM) \S+$/));
  },
};

/**
 * Without a provider (a page outside the layout, a test), the zone is null, as for an account without a zone
 */
export const WithoutProvider: Story = {
  render: () => (
    <div className="flex flex-col gap-2">
      <ZoneOfTheAccount />
      <PostLine />
    </div>
  ),
  play: async ({ canvasElement }) => {
    await expect(within(canvasElement).getByText(/The account has no time zone/)).toBeInTheDocument();
  },
};
