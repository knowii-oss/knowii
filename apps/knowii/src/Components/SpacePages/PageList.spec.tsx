import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, Page, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import { PageList } from './PageList';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { reload: vi.fn(), visit: vi.fn() },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: Record<string, string>) =>
      `/community/${params['communitySlug']}/space/${params['spaceSlug']}/page/${params['pageSlug']}`,
    writable: true,
  });
});

const now = new Date();
const community = { cuid: 'c1', slug: 'tests', name: 'Tests' } as Community;
const space = { cuid: 's1', slug: 'start-here', name: 'Start here', type: 'pages' } as Space;
const page = (cuid: string, title: string, position: number): Page => ({
  cuid,
  kind: 'page',
  title,
  slug: cuid,
  position,
  created_at: now,
  updated_at: now,
});
const welcome = page('welcome', 'Welcome', 0);
const rules = page('rules', 'House rules', 1);
const intro = page('intro', 'Introduce yourself', 2);
const pages = [welcome, rules, intro];

function renderList(canManagePages: boolean) {
  render(
    <AppDataProvider>
      <PageList community={community} space={space} pages={pages} canManagePages={canManagePages} />
    </AppDataProvider>,
  );
}

describe('PageList', () => {
  it('lists the pages in order, with links, and no controls for members (#1179)', () => {
    renderList(false);

    const items = within(screen.getByRole('list')).getAllByRole('listitem');
    expect(items.map((item) => item.textContent)).toEqual(['1.Welcome', '2.House rules', '3.Introduce yourself']);
    expect(screen.getByRole('link', { name: 'House rules' })).toHaveAttribute('href', '/community/tests/space/start-here/page/rules');
    expect(screen.queryByRole('button', { name: 'Add a page' })).toBeNull();
    expect(screen.queryByRole('button', { name: /Move/ })).toBeNull();
  });

  it('moves a page with the arrow buttons for the managers', async () => {
    const reorder = vi
      .spyOn(knowiiApiClient.communities.pages, 'reorder')
      .mockResolvedValue({ ok: true, message: '', data: [rules, welcome, intro] });
    renderList(true);

    expect(screen.getByRole('button', { name: 'Move "Welcome" up' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Move "Introduce yourself" down' })).toBeDisabled();

    await userEvent.click(screen.getByRole('button', { name: 'Move "House rules" up' }));

    expect(reorder).toHaveBeenCalledWith('c1', 's1', ['rules', 'welcome', 'intro']);
    await waitFor(() => expect(within(screen.getByRole('list')).getAllByRole('link')[0]).toHaveTextContent('House rules'));
  });
});
