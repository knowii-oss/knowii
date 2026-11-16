import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MarkdownContent } from './MarkdownContent';
import { AccountTimeZoneProvider } from './AccountTimeZone';
import { RelativeTime } from './RelativeTime';

describe('MarkdownContent', () => {
  it('shows the HTML rendered by the server with the prose typography', () => {
    const { container } = render(<MarkdownContent html="<p>Hello <strong>you</strong></p>" />);
    expect(screen.getByText('you').tagName).toBe('STRONG');
    expect(container.firstElementChild).toHaveClass('prose');
  });
});

describe('RelativeTime', () => {
  it('shows a relative date with the exact date in the datetime attribute', () => {
    const date = new Date(Date.now() - 2 * 60 * 60 * 1000);
    render(<RelativeTime date={date} />);
    const time = screen.getByText(/about 2 hours ago/);
    expect(time.tagName).toBe('TIME');
    expect(time).toHaveAttribute('datetime', date.toISOString());
    expect(time).toHaveAttribute('title');
  });

  it('accepts an ISO string (Inertia props) and ignores an invalid date', () => {
    const { container, rerender } = render(<RelativeTime date="2020-01-01T10:00:00.000000Z" />);
    expect(container.querySelector('time')).toHaveAttribute('datetime', '2020-01-01T10:00:00.000Z');
    rerender(<RelativeTime date="not a date" />);
    expect(container.querySelector('time')).toBeNull();
  });
});

describe('RelativeTime and the time zone of the account (#1476, design 9.9)', () => {
  const date = new Date('2026-11-15T10:00:00Z');

  it('shows the full date and hour in the zone of the account, and names the zone, without changing the relative text', () => {
    render(
      <AccountTimeZoneProvider value="Asia/Tokyo">
        <RelativeTime date={date} />
      </AccountTimeZoneProvider>,
    );

    const time = screen.getByRole('time', { hidden: true }) as HTMLElement;
    expect(time).toHaveAttribute('title', 'Nov 15, 2026, 7:00:00 PM GMT+9');
    expect(time.textContent).toMatch(/ago$/);
  });

  it('uses the zone of the browser, and names it, when the account has none', () => {
    render(<RelativeTime date={date} />);

    expect(screen.getByRole('time', { hidden: true })).toHaveAttribute('title', expect.stringMatching(/2026, \d+:\d\d:00 (AM|PM) \S+$/));
  });

  it('shows UTC for an account in UTC', () => {
    render(
      <AccountTimeZoneProvider value="UTC">
        <RelativeTime date={date} />
      </AccountTimeZoneProvider>,
    );

    expect(screen.getByRole('time', { hidden: true })).toHaveAttribute('title', 'Nov 15, 2026, 10:00:00 AM UTC');
  });
});
