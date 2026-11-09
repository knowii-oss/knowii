import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { CommunityResource } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import { ResourceDetails } from './ResourceDetails';

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

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params: Record<string, string>) => `/community/${params['communitySlug']}/member/${params['username']}`,
    writable: true,
  });
});

function resource(
  overrides: Partial<CommunityResource['resource']> = {},
  curator: CommunityResource['curator'] = { cuid: 'u', name: 'Ada', username: 'ada' },
) {
  return {
    cuid: 'r',
    name: 'Smart notes',
    slug: 'smart-notes',
    description: 'Read it before your first note.',
    curator,
    created_at: new Date().toISOString(),
    resource_text_article: { reading_time: 6 },
    resource: {
      name: 'How to Take Smart Notes - Example',
      description: 'A guide.',
      url: 'https://www.example.com/smart',
      level: 'beginner',
      type: 'textArticle',
      keywords: ['zettelkasten', 'notes'],
      published_at: null,
      cover_image_thumbnail_url: '/storage/resources/r/cover.webp',
      cover_image_alt: 'A notebook',
      favicon_url: null,
      ...overrides,
    },
  } as unknown as CommunityResource;
}

function renderDetails(item: CommunityResource) {
  render(
    <AppDataProvider>
      <ResourceDetails communitySlug="tests" resource={item} shareUrl="https://knowii.test/r" />
    </AppDataProvider>,
  );
}

describe('ResourceDetails', () => {
  it('shows the community name, the page title, the metadata and who added it (#1133)', () => {
    renderDetails(resource());

    expect(screen.getByRole('heading', { level: 1, name: 'Smart notes' })).toBeInTheDocument();
    expect(screen.getByText('How to Take Smart Notes - Example')).toBeInTheDocument();
    expect(screen.getByText('example.com')).toBeInTheDocument();
    expect(screen.getByText('6 min read')).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'A notebook' })).toHaveAttribute('src', '/storage/resources/r/cover.webp');
    expect(screen.getByRole('list', { name: 'Keywords' })).toHaveTextContent('zettelkasten');
    expect(screen.getByRole('link', { name: 'Ada' })).toHaveAttribute('href', '/community/tests/member/ada');
    expect(screen.getByRole('link', { name: 'Open the article' })).toHaveAttribute('href', 'https://www.example.com/smart');
  });

  it('copies the link of the page (#1133)', async () => {
    const user = userEvent.setup();
    const writeText = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
    renderDetails(resource());

    await user.click(screen.getByRole('button', { name: 'Copy the link' }));

    expect(writeText).toHaveBeenCalledWith('https://knowii.test/r');
  });

  it('never links to a non-web address and handles a deleted curator', () => {
    renderDetails(resource({ url: 'ftp://example.com/file' }, null));

    expect(screen.queryByRole('link', { name: 'Open the article' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Ada' })).not.toBeInTheDocument();
    expect(screen.getByText(/Added by/)).toHaveTextContent('Added by Deleted member');
  });
});
