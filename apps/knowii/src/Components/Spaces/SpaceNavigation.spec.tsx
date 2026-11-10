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
