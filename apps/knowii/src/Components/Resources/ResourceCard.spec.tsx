import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { CommunityResource } from '@knowii/common';
import { ResourceCard } from './ResourceCard';

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

function resource(images: { cover?: string | null; favicon?: string | null }, url = 'https://example.com/a'): CommunityResource {
  return {
    cuid: 'a',
    name: 'Smart notes',
    slug: 'a',
    description: 'Why it matters',
    resource: {
      name: 'Generic title',
      url,
      level: 'beginner',
      description: '',
      cover_image_thumbnail_url: images.cover ?? null,
      favicon_url: images.favicon ?? null,
    },
  } as unknown as CommunityResource;
}

describe('ResourceCard', () => {
  it('shows the stored cover and icon as decorative images (#1063)', () => {
    const { container } = render(
      <ResourceCard
        resource={resource({ cover: '/storage/resources/r/cover.webp', favicon: '/storage/resources/r/favicon.webp' })}
        href="/r"
      />,
    );

    const images = Array.from(container.querySelectorAll('img'));
    expect(images.map((image) => image.getAttribute('src'))).toEqual([
      '/storage/resources/r/cover.webp',
      '/storage/resources/r/favicon.webp',
    ]);
    expect(images.every((image) => image.getAttribute('alt') === '')).toBe(true);
    expect(screen.getByRole('link', { name: /^Smart notes Why it matters$/ })).toHaveAttribute('href', '/r');
  });

  it('works without images and never links to a non-web address', () => {
    const { container } = render(<ResourceCard resource={resource({}, 'ftp://example.com/file')} href="/r" />);

    expect(container.querySelectorAll('img')).toHaveLength(0);
    expect(screen.getByText('🌱')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /new tab/ })).not.toBeInTheDocument();
  });
});
