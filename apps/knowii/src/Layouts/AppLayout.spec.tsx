import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import AppLayout from './AppLayout';

const page = vi.hoisted(() => ({ user: null as { cuid: string; name: string; email: string; avatar_url: string | null } | null }));

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({
    props: {
      auth: { user: page.user },
      jetstream: { flash: {}, hasApiFeatures: true },
      newsletter: { enabled: false, turnstileSiteKey: null },
    },
    url: '/community/tests',
  }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

function renderLayout() {
  render(
    <AppDataProvider>
      <AppLayout browserPageTitle="A space">
        <p>Content</p>
      </AppLayout>
    </AppDataProvider>,
  );
}

describe('AppLayout for a visitor (#1387)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows "Sign in" and "Sign up" in place of the user menu, and no link to the dashboard', () => {
    page.user = null;
    renderLayout();

    expect(screen.getAllByRole('link', { name: 'Sign in' })[0]).toHaveAttribute('href', '/login');
    expect(screen.getAllByRole('link', { name: 'Sign up' })[0]).toHaveAttribute('href', '/register');
    expect(screen.queryByRole('button', { name: 'Open the user menu' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Open the main menu' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Dashboard' })).toBeNull();
    expect(screen.getByText('Content')).toBeInTheDocument();
  });

  it('keeps the theme switch', () => {
    page.user = null;
    renderLayout();

    expect(screen.getByRole('button', { name: /theme|dark|light/i })).toBeInTheDocument();
  });

  it('keeps the menus of a signed-in member, and no sign-in link', () => {
    page.user = { cuid: 'u1', name: 'Ada', email: 'ada@example.com', avatar_url: null };
    renderLayout();

    expect(screen.getByRole('button', { name: 'Open the user menu' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Open the main menu' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Sign in' })).toBeNull();
  });

  it('shows no bell, requests no count, opens no channel and has no live region for a visitor (#1484)', () => {
    page.user = null;
    const count = vi.spyOn(knowiiApiClient.notifications, 'count');
    const subscribe = vi.spyOn(window.Echo, 'private');
    renderLayout();

    expect(screen.queryByRole('button', { name: /^Notifications/ })).toBeNull();
    expect(screen.queryByRole('status')).toBeNull();
    expect(count).not.toHaveBeenCalled();
    expect(subscribe).not.toHaveBeenCalled();
  });

  it('shows the bell, loads the count and listens to the channel of the member (#1484)', async () => {
    page.user = { cuid: 'u1', name: 'Ada', email: 'ada@example.com', avatar_url: null };
    const count = vi.spyOn(knowiiApiClient.notifications, 'count').mockResolvedValue({ ok: true, message: '', data: { count: 2 } });
    const subscribe = vi.spyOn(window.Echo, 'private');
    renderLayout();

    expect(await screen.findByRole('button', { name: 'Notifications, 2 new' })).toBeInTheDocument();
    expect(count).toHaveBeenCalledTimes(1);
    expect(subscribe).toHaveBeenCalledWith('user.u1');
    // One polite region, there from the load
    expect(screen.getAllByRole('status')).toHaveLength(1);
  });
});
