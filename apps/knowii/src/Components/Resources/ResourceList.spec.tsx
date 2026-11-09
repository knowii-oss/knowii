import { beforeAll, describe, expect, it, vi } from 'vitest';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, CommunityResource, Paginated, Space } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import { ResourceList } from './ResourceList';

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

// The resource events of the space channel: keep the handlers to fire them in the tests
const handlers = new Map<string, (payload: unknown) => void>();

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: Record<string, string>) =>
      `/community/${params['communitySlug']}/space/${params['spaceSlug']}/resource/${params['resourceSlug']}`,
    writable: true,
  });
  Object.defineProperty(window, 'Echo', {
    value: {
      private: () => ({
        listen: (event: string, handler: (payload: unknown) => void) => handlers.set(event, handler),
        stopListening: (event: string) => handlers.delete(event),
      }),
      leaveChannel: () => undefined,
    },
    writable: true,
  });
});

const now = new Date();
const community = {
  cuid: 'c1',
  name: 'Tests',
  slug: 'tests',
  description: '',
  visibility: 'public',
  created_at: now,
  updated_at: now,
} as Community;
const space = { cuid: 's1', name: 'Cool links', slug: 'cool-links', type: 'resources' } as Space;

function resource(cuid: string, name: string, url = `https://example.com/${cuid}`): CommunityResource {
  return {
    cuid,
    name,
    slug: cuid,
    description: `${name} description`,
    is_featured: false,
    resource: { cuid: `r-${cuid}`, name, url, level: 'beginner', type: 'textArticle', description: '' },
    curator: null,
    created_at: now,
    updated_at: now,
  } as unknown as CommunityResource;
}

function page(items: CommunityResource[]): Paginated<CommunityResource> {
  return {
    items,
    pagination: { limit: 24, offset: 0, previousOffset: null, nextOffset: null, currentPage: 1, pageCount: 1, totalCount: items.length },
  };
}

function renderList(items: CommunityResource[], canContribute = false) {
  return render(
    <AppDataProvider>
      <ResourceList community={community} space={space} initialResources={page(items)} canContribute={canContribute} />
    </AppDataProvider>,
  );
}

describe('ResourceList', () => {
  it('links each resource to its page and to the article', () => {
    renderList([resource('a', 'Smart notes')]);

    const list = screen.getByRole('list');
    expect(within(list).getByRole('link', { name: /^Smart notes Smart notes description$/ })).toHaveAttribute(
      'href',
      '/community/tests/space/cool-links/resource/a',
    );
    expect(within(list).getByRole('link', { name: 'Open "Smart notes" in a new tab' })).toHaveAttribute('href', 'https://example.com/a');
  });

  it('never links to a URL that is not http or https', () => {
    renderList([resource('a', 'Bad link', 'ftp://example.com/file')]);

    expect(screen.queryByRole('link', { name: /in a new tab/ })).not.toBeInTheDocument();
  });

  it('shows an empty state, and the add button only to contributors', () => {
    const { unmount } = renderList([]);
    expect(screen.getByText('No resources yet')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Add a resource' })).not.toBeInTheDocument();
    unmount();

    renderList([], true);
    expect(screen.getByRole('button', { name: 'Add a resource' })).toBeInTheDocument();
  });

  it('opens the dialog to add a resource with the keyboard', async () => {
    renderList([], true);

    await userEvent.tab();
    expect(screen.getByRole('button', { name: 'Add a resource' })).toHaveFocus();
    await userEvent.keyboard('{Enter}');

    expect(await screen.findByRole('dialog')).toHaveTextContent('Add a resource to Cool links.');
  });

  it('follows the resource events of the space', async () => {
    renderList([resource('a', 'First')]);

    act(() => handlers.get('.community.resource.created')?.(resource('b', 'Second')));
    expect(screen.getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      expect.stringContaining('Second'),
      expect.stringContaining('First'),
    ]);

    act(() => handlers.get('.community.resource.deleted')?.({ cuid: 'a' }));
    expect(screen.queryByText('First')).not.toBeInTheDocument();
  });
});
