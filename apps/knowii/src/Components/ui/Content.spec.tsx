import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MarkdownContent } from './MarkdownContent';
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
