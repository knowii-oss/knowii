import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import AppLayout from './AppLayout';

const page = vi.hoisted(() => ({
  user: null as { cuid: string; name: string; email: string; avatar_url: string | null; time_zone?: string | null } | null,
  catchUp: null as { since: string } | null,
}));

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
      catchUp: page.catchUp,
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
    page.catchUp = null;
  });

  it('gets none of "where I am" (#1476): no question about the time zone, no pop-up, no "Catch up" entry', () => {
    page.user = null;
    page.catchUp = { since: '2026-11-01' };
    const zone = vi.spyOn(knowiiApiClient.users, 'setTimeZone');
    renderLayout();

    expect(screen.queryByText(/Show times in/)).toBeNull();
    expect(screen.queryByTestId('notification-popup')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Open the user menu' })).toBeNull();
    expect(screen.queryByText('Catch up')).toBeNull();
    expect(zone).not.toHaveBeenCalled();
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
    const count = vi
      .spyOn(knowiiApiClient.notifications, 'count')
      .mockResolvedValue({ ok: true, message: '', data: { count: 2, messages: 0 } });
    const subscribe = vi.spyOn(window.Echo, 'private');
    renderLayout();

    expect(await screen.findByRole('button', { name: 'Notifications, 2 new' })).toBeInTheDocument();
    expect(count).toHaveBeenCalledTimes(1);
    expect(subscribe).toHaveBeenCalledWith('user.u1');
    // One polite region, there from the load
    expect(screen.getAllByRole('status')).toHaveLength(1);
  });

  it('sends the "Home" of the breadcrumb of a visitor to the home page, and the community to its public page (#1485)', () => {
    page.user = null;
    renderBreadcrumb();

    expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: 'Tests' })).toHaveAttribute('href', '/community/tests');
  });

  it('keeps the "Home" of a signed-in person on the dashboard (#1485)', () => {
    page.user = { cuid: 'u1', name: 'Ada', email: 'ada@example.com', avatar_url: null };
    renderBreadcrumb();

    expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/dashboard');
    expect(screen.getByRole('link', { name: 'Tests' })).toHaveAttribute('href', '/community/tests');
  });
});

function renderBreadcrumb() {
  render(
    <AppDataProvider>
      <AppLayout
        browserPageTitle="A space"
        breadcrumbHome={{ label: 'Home', href: '/dashboard' }}
        breadcrumbItems={[{ label: 'Tests', href: '/community/tests' }, { label: 'A space' }]}
      >
        <p>Content</p>
      </AppLayout>
    </AppDataProvider>,
  );
}

describe('AppLayout for a member (#1476)', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    page.catchUp = null;
  });

  const member = { cuid: 'u1', name: 'Ada', email: 'ada@example.com', avatar_url: null, time_zone: 'UTC' };

  it('adds the entry "Catch up" to the user menu while the prop holds a date, and it opens the page "Notifications"', async () => {
    page.user = member;
    page.catchUp = { since: '2026-11-01' };
    vi.spyOn(knowiiApiClient.notifications, 'count').mockResolvedValue({ ok: true, message: '', data: { count: 0, messages: 0 } });
    renderLayout();

    await userEvent.click(screen.getByRole('button', { name: 'Open the user menu' }));

    expect(await screen.findByRole('menuitem', { name: 'Catch up' })).toHaveAttribute('href', '/notifications.index');
  });

  it('has no entry "Catch up" without a date', async () => {
    page.user = member;
    page.catchUp = null;
    vi.spyOn(knowiiApiClient.notifications, 'count').mockResolvedValue({ ok: true, message: '', data: { count: 0, messages: 0 } });
    renderLayout();

    await userEvent.click(screen.getByRole('button', { name: 'Open the user menu' }));

    expect(await screen.findByRole('menuitem', { name: 'Edit profile' })).toBeInTheDocument();
    expect(screen.queryByRole('menuitem', { name: 'Catch up' })).toBeNull();
  });
});
