import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import axe from 'axe-core';
import { eventsRoute } from '@/test-utils/events';
import { EventMemberLine } from './EventMemberLine';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
});

const ada = { cuid: 'u1', name: 'Ada Lovelace', username: 'ada', avatar_url: null };

describe('one member on the screens of events (#1514, #1515)', () => {
  it('links the name to the member page and writes the username, with "username" for a screen reader', () => {
    render(
      <p>
        <EventMemberLine member={ada} communitySlug="tests" link />
      </p>,
    );

    const link = screen.getByRole('link', { name: 'Ada Lovelace' });
    expect(link).toHaveAttribute('href', '/community/tests/member/ada');
    // A list finds the link of a member by its cuid, to give it the focus
    expect(link).toHaveAttribute('data-member-link', 'u1');
    // 44 px of height on a small screen
    expect(link).toHaveClass('touch-target');
    expect(screen.getByText('username')).toHaveClass('sr-only');
    expect(screen.getByText('@')).toHaveAttribute('aria-hidden', 'true');
    expect(document.querySelector('p')).toHaveTextContent('username @ada');
  });

  it('writes the name as text for a reader who is not a member: the member page is for members', () => {
    render(
      <p>
        <EventMemberLine member={ada} communitySlug="tests" link={false} />
      </p>,
    );

    expect(screen.queryByRole('link')).toBeNull();
    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument();
  });

  it('shows the picture of the member, or the initials without one', () => {
    const { unmount } = render(<EventMemberLine member={ada} communitySlug="tests" link />);
    expect(screen.getByText('AL')).toBeInTheDocument();
    unmount();

    render(<EventMemberLine member={{ ...ada, avatar_url: '/avatars/ada.webp' }} communitySlug="tests" link />);
    expect(document.querySelector('img')).toHaveAttribute('src', '/avatars/ada.webp');
  });

  it('has no automated violation (axe)', async () => {
    const { container } = render(
      <ul>
        <li>
          <EventMemberLine member={ada} communitySlug="tests" link />
        </li>
      </ul>,
    );

    const results = await axe.run(container, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } });

    expect(results.violations.map((violation) => violation.id)).toEqual([]);
  });
});
