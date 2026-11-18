import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Community } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import { CommunityShell } from './CommunityShell';

// The user of the page: null for a visitor
const signedIn = vi.hoisted(() => ({ user: null as { cuid: string; show_online_status: boolean } | null }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  router: { reload: vi.fn() },
  usePage: () => ({ props: { auth: { user: signedIn.user } }, url: '/' }),
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
      <CommunityShell community={community} spaceGroups={[]} {...(contentWidth === undefined ? {} : { contentWidth })}>
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

describe('CommunityShell for a visitor (#1387)', () => {
  const channelOpened = vi.fn();

  beforeEach(() => {
    channelOpened.mockReset();
    Object.defineProperty(window, 'Echo', {
      value: {
        private: (channel: string) => {
          channelOpened(channel);
          return { listen: () => undefined, stopListening: () => undefined };
        },
        join: (channel: string) => {
          channelOpened(channel);
          return { here: () => undefined, joining: () => undefined, leaving: () => undefined };
        },
        leaveChannel: () => undefined,
      },
      writable: true,
    });
  });

  afterEach(() => {
    signedIn.user = null;
  });

  it('opens no channel and no presence without a user', () => {
    signedIn.user = null;
    renderShell();

    expect(channelOpened).not.toHaveBeenCalled();
    expect(screen.queryByText('Messages')).toBeNull();
  });

  it('opens its channels for a signed-in member', () => {
    signedIn.user = { cuid: 'u1', show_online_status: true };
    renderShell();

    expect(channelOpened).toHaveBeenCalledWith('community.c1');
    expect(channelOpened).toHaveBeenCalledWith('user.u1');
  });
});
