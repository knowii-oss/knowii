import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Community } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import { CommunityShell } from './CommunityShell';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { reload: vi.fn() },
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: () => '/space', writable: true });
  Object.defineProperty(window, 'Echo', {
    value: { private: () => ({ listen: () => undefined, stopListening: () => undefined }), leaveChannel: () => undefined },
    writable: true,
  });
});

const community = { cuid: 'c1', name: 'Tests', slug: 'tests' } as Community;

function renderShell(contentWidth?: 'wide' | 'readable') {
  render(
    <AppDataProvider>
      <CommunityShell community={community} spaceGroups={[]} contentWidth={contentWidth}>
        <p>Content</p>
      </CommunityShell>
    </AppDataProvider>,
  );
  return screen.getByText('Content');
}

describe('CommunityShell (#1264)', () => {
  it('makes the content a container, so that its layouts follow the width left by the navigation', () => {
    const content = renderShell();

    expect(content.closest('.\\@container')).not.toBeNull();
    expect(content.closest('.readable-width')).toBeNull();
  });

  it('keeps long text at a readable width when asked', () => {
    const content = renderShell('readable');

    expect(content.parentElement).toHaveClass('readable-width');
    expect(content.closest('.\\@container')).not.toBeNull();
  });

  it('names the navigation of the spaces', () => {
    renderShell();

    expect(screen.getByRole('complementary', { name: 'Tests spaces' })).toBeInTheDocument();
  });
});
