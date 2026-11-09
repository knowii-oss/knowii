import { beforeAll, describe, expect, it, vi } from 'vitest';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Community, CommunityResource, Paginated, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
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
    level: 'beginner',
    type: 'article',
    capture_status: 'captured',
    resource: { cuid: `r-${cuid}`, name, url, type: 'article', description: '' },
    curator: null,
    created_at: now,
    updated_at: now,
  } as unknown as CommunityResource;
}

function page(items: CommunityResource[], nextOffset: number | null = null): Paginated<CommunityResource> {
  return {
    items,
    pagination: { limit: 24, offset: 0, previousOffset: null, nextOffset, currentPage: 1, pageCount: 1, totalCount: items.length },
  };
}

function renderList(items: CommunityResource[], canContribute = false, nextOffset: number | null = null) {
  return render(
    <AppDataProvider>
      <ResourceList community={community} space={space} initialResources={page(items, nextOffset)} canContribute={canContribute} />
    </AppDataProvider>,
  );
}

describe('ResourceList', () => {
  it('links each resource to its page and to the article', () => {
    renderList([resource('a', 'Smart notes')]);

    const list = screen.getByRole('list');
    expect(within(list).getByRole('link', { name: /^Article.*Smart notes Smart notes description$/ })).toHaveAttribute(
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

    expect(await screen.findByRole('dialog')).toHaveTextContent('Share a link in Cool links.');
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

  it('follows the capture of a resource: status, then the title of the page (ADR 0018)', () => {
    renderList([{ ...resource('a', 'Smart Notes Guide'), capture_status: 'pending' } as CommunityResource]);
    expect(screen.getByText('Capturing the page…')).toBeInTheDocument();

    act(() => handlers.get('.community.resource.updated')?.({ ...resource('a', 'How to take smart notes'), capture_status: 'captured' }));

    expect(screen.queryByText('Capturing the page…')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: /How to take smart notes/ })).toBeInTheDocument();
  });

  it('loads the next page and skips the resources already shown (#877)', async () => {
    const list = vi
      .spyOn(knowiiApiClient.resources, 'list')
      .mockResolvedValue({ ok: true, message: '', data: page([resource('a', 'First'), resource('b', 'Second')]) });
    renderList([resource('a', 'First')], false, 1);

    await userEvent.click(screen.getByRole('button', { name: 'Load more resources' }));

    expect(list).toHaveBeenCalledWith('c1', 's1', 24, 1);
    expect(screen.getAllByRole('listitem')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: 'Load more resources' })).not.toBeInTheDocument();
  });

  it('shows the number of resources and keeps it current (#1137)', () => {
    renderList([resource('a', 'First')]);
    expect(screen.getByText('1 resource')).toBeInTheDocument();

    act(() => handlers.get('.community.resource.created')?.(resource('b', 'Second')));
    // The same resource announced twice (dialog and event) counts once
    act(() => handlers.get('.community.resource.created')?.(resource('b', 'Second')));
    expect(screen.getByText('2 resources')).toBeInTheDocument();

    act(() => handlers.get('.community.resource.deleted')?.({ cuid: 'a' }));
    expect(screen.getByText('1 resource')).toBeInTheDocument();
  });
});
