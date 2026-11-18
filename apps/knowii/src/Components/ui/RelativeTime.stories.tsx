import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { AccountTimeZoneProvider } from './AccountTimeZone';
import type { RelativeTimeProps } from './RelativeTime';
import { RelativeTime } from './RelativeTime';

// The fixed "now" of the stories: the relative text is the same on every run and on every machine
const NOW = new Date('2026-11-15T10:00:00Z');

// The clock of the machine, kept when the file loads: the docs page renders all the stories at once, and each one
// gives back this one
const MACHINE_NOW = Date.now;

function before(milliseconds: number): string {
  return new Date(NOW.getTime() - milliseconds).toISOString();
}

const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

interface StoryArgs extends RelativeTimeProps {
  /**
   * The time zone of the account (the layout of the application gives it), null when the account has none
   */
  zone: string | null;
}

/**
 * The time in a line of text, as a post shows it, inside the time zone of the account
 */
function TimeInText({ zone, ...args }: StoryArgs) {
  return (
    <AccountTimeZoneProvider value={zone}>
      <p className="text-sm text-muted">
        Posted <RelativeTime {...args} />
      </p>
    </AccountTimeZoneProvider>
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
 * How long ago something happened ("about 2 hours ago"), for the date of a post, a comment or a message. The exact date
 * and hour are in the tooltip, in the time zone of the account when it has one (#1476), and the zone is named. It accepts
 * a `Date` or an ISO string (the dates of the Inertia props are strings), and shows nothing for a date that is not valid.
 *
 * Accessibility: a `<time>` element with the exact date in its `datetime` attribute. The tooltip (`title`) is not read
 * by every screen reader: the relative text must be enough on its own.
 */
const meta = {
  component: RelativeTime,
  render: (args) => <TimeInText {...args} />,
  args: {
    date: before(2 * HOUR),
    zone: 'UTC',
  },
  // The stories fix "now" (date-fns reads it with Date.now), and give the clock of the machine back after the story.
  // Not a spy of storybook/test: a spy reads Date.now too, and calls itself.
  beforeEach: () => {
    Date.now = () => NOW.getTime();
    return () => {
      Date.now = MACHINE_NOW;
    };
  },
} satisfies Meta<StoryArgs>;

export default meta;
type Story = StoryObj<typeof meta>;

export const HoursAgo: Story = {
  play: async ({ canvasElement }) => {
    const time = timeElement(canvasElement);
    await expect(time).toHaveTextContent('about 2 hours ago');
    await expect(time).toHaveAttribute('datetime', '2026-11-15T08:00:00.000Z');
    await expect(time).toHaveAttribute('title', 'Nov 15, 2026, 8:00:00 AM UTC');
  },
};

export const JustNow: Story = {
  args: { date: before(20 * 1000) },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveTextContent('less than a minute ago');
  },
};

export const MinutesAgo: Story = {
  args: { date: before(5 * MINUTE) },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveTextContent('5 minutes ago');
  },
};

export const DaysAgo: Story = {
  args: { date: before(3 * DAY) },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveTextContent('3 days ago');
  },
};

export const MonthsAgo: Story = {
  args: { date: before(95 * DAY) },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveTextContent('3 months ago');
  },
};

export const YearsAgo: Story = {
  args: { date: '2023-04-02T09:30:00Z' },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveTextContent('over 3 years ago');
  },
};

/**
 * A date after "now" (an event that is planned)
 */
export const InTheFuture: Story = {
  args: { date: new Date(NOW.getTime() + 3 * DAY).toISOString() },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveTextContent('in 3 days');
  },
};

/**
 * A `Date` object, not a string
 */
export const FromADate: Story = {
  args: { date: new Date(NOW.getTime() - 2 * HOUR) },
  play: async ({ canvasElement }) => {
    await expect(timeElement(canvasElement)).toHaveAttribute('datetime', '2026-11-15T08:00:00.000Z');
  },
};

/**
 * The format of the dates of the Inertia props (microseconds)
 */
export const FromAnInertiaProp: Story = {
  args: { date: '2026-11-15T07:00:00.000000Z' },
  play: async ({ canvasElement }) => {
    const time = timeElement(canvasElement);
    await expect(time).toHaveTextContent('about 3 hours ago');
    await expect(time).toHaveAttribute('datetime', '2026-11-15T07:00:00.000Z');
  },
};

/**
 * The tooltip shows the date and the hour in the zone of the account, and names the zone; the relative text does not
 * change
 */
export const InTheZoneOfTheAccount: Story = {
  args: { zone: 'Asia/Tokyo' },
  play: async ({ canvasElement }) => {
    const time = timeElement(canvasElement);
    await expect(time).toHaveTextContent('about 2 hours ago');
    await expect(time).toHaveAttribute('title', 'Nov 15, 2026, 5:00:00 PM GMT+9');
  },
};

/**
 * The account has no zone: the tooltip uses the zone of the browser, so its text depends on the machine
 */
export const WithoutZone: Story = {
  args: { zone: null },
  play: async ({ canvasElement }) => {
    const time = timeElement(canvasElement);
    await expect(time).toHaveTextContent('about 2 hours ago');
    await expect(time).toHaveAttribute('title', expect.stringMatching(/2026, \d+:\d\d:00 (AM|PM) \S+$/));
  },
};

/**
 * A date that is not valid shows nothing
 */
export const InvalidDate: Story = {
  args: { date: 'not a date' },
  play: async ({ canvasElement }) => {
    await expect(canvasElement.querySelector('time')).toBeNull();
    await expect(within(canvasElement).getByText('Posted')).toBeInTheDocument();
  },
};
