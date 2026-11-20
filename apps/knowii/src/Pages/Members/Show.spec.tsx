import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import axe from 'axe-core';
import type { Community, MemberProfile } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import MemberPage from './Show';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { visit: vi.fn(), reload: vi.fn() },
}));

vi.mock('@/Layouts/AppLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (name: string, params?: Record<string, string>) => `/${name}/${Object.values(params ?? {}).join('/')}`,
    writable: true,
  });
});

const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: 'A place to learn',
  visibility: 'private',
} as Community;

const member: MemberProfile = {
  cuid: 'm1',
  name: 'Ada Lovelace',
  username: 'ada',
  avatar_url: null,
  headline: '',
  bio: '',
  location: '',
  phone: '',
  social_links: {},
  member_since: new Date('2026-11-01T10:00:00Z'),
};

function renderPage(changes: Partial<MemberProfile> = {}) {
  return render(
    <AppDataProvider>
      <MemberPage
        community={community}
        spaceGroups={[]}
        member={{ ...member, ...changes }}
        messaging={{ isSelf: false, blockedByMe: false, canMessage: true }}
      />
    </AppDataProvider>,
  );
}

/** The texts of the header of the member page, from the top to the bottom */
function headerLines(): string[] {
  const header = screen.getByRole('heading', { level: 1, name: 'Ada Lovelace' }).parentElement;
  return Array.from(header?.children ?? []).map((element) => `${element.tagName.toLowerCase()}: ${element.textContent}`);
}

describe('the headline on the member page (#1688)', () => {
  it('shows the headline between the name and the username', () => {
    renderPage({ headline: 'Product designer, writes about note-taking' });

    expect(headerLines()).toEqual(['h1: Ada Lovelace', 'p: Product designer, writes about note-taking', 'p: @ada']);
    // A long word without a space breaks, and nothing cuts the line
    const headline = screen.getByText('Product designer, writes about note-taking');
    expect(headline).toHaveClass('wrap-anywhere');
    expect(headline).not.toHaveClass('truncate');
    expect(headline.className).not.toMatch(/line-clamp|text-muted|overflow-hidden/);
  });

  it('shows no paragraph without a headline: the header is the name and the username', () => {
    renderPage({ headline: '' });

    expect(headerLines()).toEqual(['h1: Ada Lovelace', 'p: @ada']);
  });

  it('shows a headline that looks like HTML or Markdown as the text that the member typed', () => {
    const { container } = renderPage({ headline: '<b>Hi</b> & **you** <img src=x onerror=alert(1)>' });

    expect(screen.getByText('<b>Hi</b> & **you** <img src=x onerror=alert(1)>')).toBeInTheDocument();
    expect(container.querySelector('b')).toBeNull();
    expect(container.querySelector('strong')).toBeNull();
    expect(container.querySelector('img')).toBeNull();
    expect(headerLines()[1]).toBe('p: <b>Hi</b> & **you** <img src=x onerror=alert(1)>');
  });

  it('does not turn a web address of the headline into a link', () => {
    renderPage({ headline: 'See https://example.com and [my site](https://example.com/me)' });

    const headline = screen.getByText('See https://example.com and [my site](https://example.com/me)');
    expect(headline.querySelector('a')).toBeNull();
    // No link of the page goes to the host of the headline: each address is read as an address, and its host is compared
    const hosts = screen.getAllByRole('link').map((link) => new URL(link.getAttribute('href') ?? '', 'http://localhost').host);
    expect(hosts.filter((host) => host === 'example.com')).toEqual([]);
  });

  it('has no automated violation (axe), with a headline and without one', async () => {
    const withHeadline = renderPage({ headline: 'Product designer, writes about note-taking', bio: 'I like notes', location: 'Brussels' });
    expect((await axe.run(withHeadline.container)).violations.map((violation) => violation.id)).toEqual([]);
    withHeadline.unmount();

    const without = renderPage();
    expect((await axe.run(without.container)).violations.map((violation) => violation.id)).toEqual([]);
  });
});
