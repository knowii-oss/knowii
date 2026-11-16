import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Space, SpaceGroup } from '@knowii/common';
import { SpaceNavigation } from './SpaceNavigation';

// Built at run time: a literal script URL is a lint error
const SCRIPT_URL = ['javascript', 'alert(1)'].join(':');

vi.mock('@inertiajs/react', () => ({
  Link: ({
    children,
    href,
    preserveState: _preserveState,
    ...props
  }: {
    children: React.ReactNode;
    href: string;
    preserveState?: boolean;
  }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

const now = new Date();

function space(overrides: Partial<Space>): Space {
  return {
    cuid: 'space',
    name: 'Space',
    slug: 'space',
    description: '',
    emoji: '',
    type: 'discussion',
    visibility: 'members',
    posting_policy: 'members',
    position: 0,
    space_group_cuid: 'group',
    created_at: now,
    updated_at: now,
    ...overrides,
  };
}

const groups: SpaceGroup[] = [
  {
    cuid: 'start',
    name: 'Start Here',
    position: 0,
    created_at: now,
    updated_at: now,
    spaces: [
      space({ cuid: 'welcome', name: 'Welcome', slug: 'welcome', emoji: '👋', visibility: 'public' }),
      space({ cuid: 'intros', name: 'Introductions', slug: 'introductions', type: 'chat' }),
    ],
  },
  { cuid: 'empty', name: 'Empty group', position: 1, created_at: now, updated_at: now, spaces: [] },
];

describe('SpaceNavigation', () => {
  it('lists the spaces under their group headings, in order, and skips empty groups', () => {
    render(<SpaceNavigation spaceGroups={groups} getSpaceHref={(s) => `/space/${s.slug}`} />);

    const nav = screen.getByRole('navigation', { name: 'Spaces' });
    const list = within(nav).getByRole('list', { name: 'Start Here' });
    const links = within(list).getAllByRole('link');
    expect(links.map((link) => link.getAttribute('href'))).toEqual(['/space/welcome', '/space/introductions']);
    expect(within(nav).queryByText('Empty group')).not.toBeInTheDocument();
  });

  it('marks the active space and the members-only spaces', () => {
    render(<SpaceNavigation spaceGroups={groups} activeSpaceCuid="intros" getSpaceHref={(s) => `/space/${s.slug}`} />);

    expect(screen.getByRole('link', { name: /Introductions/ })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: /Welcome/ })).not.toHaveAttribute('aria-current');
    expect(screen.getByRole('link', { name: /Introductions/ })).toContainElement(screen.getByLabelText('Members only'));
  });

  it('moves between the spaces with Tab and reports a navigation', async () => {
    const onNavigate = vi.fn();
    render(<SpaceNavigation spaceGroups={groups} getSpaceHref={(s) => `/space/${s.slug}`} onNavigate={onNavigate} />);

    await userEvent.tab();
    expect(screen.getByRole('link', { name: /Welcome/ })).toHaveFocus();
    await userEvent.tab();
    expect(screen.getByRole('link', { name: /Introductions/ })).toHaveFocus();
    await userEvent.click(screen.getByRole('link', { name: /Introductions/ }));
    expect(onNavigate).toHaveBeenCalledTimes(1);
  });

  it('opens a link space outside Knowii in a new tab, without the referrer (#1294)', async () => {
    const onNavigate = vi.fn();
    const withLink: SpaceGroup[] = [
      {
        cuid: 'more',
        name: 'More',
        position: 0,
        created_at: now,
        updated_at: now,
        spaces: [
          space({ cuid: 'shop', name: 'Shop', slug: 'shop', type: 'link', url: 'https://shop.example.com/' }),
          space({ cuid: 'bad', name: 'Bad', slug: 'bad', type: 'link', url: SCRIPT_URL }),
        ],
      },
    ];
    render(
      <SpaceNavigation spaceGroups={withLink} activeSpaceCuid="shop" getSpaceHref={(s) => `/space/${s.slug}`} onNavigate={onNavigate} />,
    );

    const shop = screen.getByRole('link', { name: /Shop \(opens a page outside Knowii in a new tab\)/ });
    expect(shop).toHaveAttribute('href', 'https://shop.example.com/');
    expect(shop).toHaveAttribute('target', '_blank');
    expect(shop).toHaveAttribute('rel', 'noopener noreferrer');
    // A link is never the current page
    expect(shop).not.toHaveAttribute('aria-current');
    // A URL that is not http or https never becomes a link: the space page explains the problem
    expect(screen.getByRole('link', { name: /Bad/ })).toHaveAttribute('href', '/space/bad');

    await userEvent.click(shop);
    expect(onNavigate).toHaveBeenCalledTimes(1);
  });

  it('says when there is no space yet', () => {
    render(<SpaceNavigation spaceGroups={[]} getSpaceHref={() => '#'} />);
    expect(screen.getByText('No spaces yet.')).toBeInTheDocument();
  });
});

describe('the dots of the navigation (#1476, design 5.2 and 9.5)', () => {
  const renderSpaces = (spaces: Space[]) =>
    render(
      <SpaceNavigation
        spaceGroups={[
          {
            cuid: 'g',
            name: 'Talk',
            position: 0,
            created_at: now,
            updated_at: now,
            spaces: spaces.map((item) => ({ ...item, visibility: 'public' as const })),
          },
        ]}
        getSpaceHref={(item) => `/spaces/${item.slug}`}
      />,
    );

  it('shows a dot for a space with new content, and "New content" is part of the name of the link', () => {
    renderSpaces([
      space({ cuid: 'a', name: 'News', slug: 'news', has_new: true, notification_level: 'mine' }),
      space({ cuid: 'b', name: 'Quiet', slug: 'quiet', has_new: false, notification_level: 'mine' }),
    ]);

    expect(screen.getByRole('link', { name: 'News, New content' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Quiet' })).toBeInTheDocument();
  });

  it('draws the dot with a border in the color of the text, so that forced colors keep it, and hides the shape from assistive technology', () => {
    renderSpaces([space({ cuid: 'a', name: 'News', slug: 'news', has_new: true, notification_level: 'all' })]);

    const dot = screen.getByRole('link', { name: 'News, New content' }).querySelector('span[aria-hidden="true"].rounded-full');
    expect(dot).not.toBeNull();
    expect(dot?.className).toContain('border-current');
    expect(dot?.className).toContain('border-2');
  });

  it('shows no dot for a visitor or an outsider (the fields are absent)', () => {
    renderSpaces([space({ cuid: 'a', name: 'News', slug: 'news' })]);

    expect(screen.getByRole('link', { name: 'News' })).toBeInTheDocument();
    expect(screen.queryByText(/New content/)).toBeNull();
  });

  it('shows the number of a chat space only at the level Everything', () => {
    renderSpaces([
      space({ cuid: 'a', name: 'Lounge', slug: 'lounge', type: 'chat', unread_count: 4, notification_level: 'all' }),
      space({ cuid: 'b', name: 'Help', slug: 'help', type: 'chat', unread_count: 6, notification_level: 'mine' }),
    ]);

    expect(screen.getByRole('link', { name: /Lounge.*4.*unread/ })).toBeInTheDocument();
    // Only what is for me: no number, and no dot (a chat space has no dot)
    expect(screen.getByRole('link', { name: 'Help' })).toBeInTheDocument();
    expect(screen.queryByText('6')).toBeNull();
  });

  it('shows nothing for a muted space: no dot and no number', () => {
    renderSpaces([
      space({ cuid: 'a', name: 'Muted talk', slug: 'muted', has_new: true, notification_level: 'none' }),
      space({ cuid: 'b', name: 'Muted chat', slug: 'muted-chat', type: 'chat', unread_count: 3, notification_level: 'none' }),
    ]);

    expect(screen.getByRole('link', { name: 'Muted talk' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Muted chat' })).toBeInTheDocument();
    expect(screen.queryByText(/New content|unread/)).toBeNull();
  });

  it('shows no dot for a link space', () => {
    renderSpaces([space({ cuid: 'a', name: 'Shop', slug: 'shop', type: 'link', url: 'https://example.com', has_new: false })]);

    expect(screen.queryByText(/New content/)).toBeNull();
  });
});
