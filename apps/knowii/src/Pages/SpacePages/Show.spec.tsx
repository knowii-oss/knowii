import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import type { Community, Page, Space } from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import SpacePage from './Show';

vi.mock('@inertiajs/react', () => ({
  Link: ({ children, href, ...props }: { children: React.ReactNode; href: string }) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
  Head: () => null,
  router: { reload: vi.fn(), visit: vi.fn() },
  usePage: () => ({
    props: { auth: { user: null }, jetstream: { flash: {} }, newsletter: { enabled: false, turnstileSiteKey: null } },
    url: '/',
  }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));
vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', {
    value: (_name: string, params?: Record<string, string>) => `/page/${params?.['pageSlug'] ?? params?.['spaceSlug'] ?? ''}`,
    writable: true,
  });
});

const now = new Date();
const props = {
  community: { cuid: 'c1', slug: 'tests', name: 'Tests', visibility: 'private' } as Community,
  space: { cuid: 's1', slug: 'start-here', name: 'Start here', type: 'pages', emoji: '📄' } as Space,
  spaceGroups: [],
  page: {
    cuid: 'p2',
    title: 'House rules',
    slug: 'rules',
    position: 1,
    body_html: '<p>Be kind.</p>',
    created_at: now,
    updated_at: now,
  } as Page,
  previous: { title: 'Welcome', slug: 'welcome' },
  next: { title: 'Introduce yourself', slug: 'intro' },
  position: { current: 2, total: 3 },
};

describe('SpacePage', () => {
  it('shows the text and links the previous and the next page (#1179)', () => {
    render(
      <AppDataProvider>
        <SpacePage {...props} permissions={{ canManagePages: false }} />
      </AppDataProvider>,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'House rules' })).toBeInTheDocument();
    expect(screen.getByText('Page 2 of 3')).toBeInTheDocument();
    expect(screen.getByText('Be kind.')).toBeInTheDocument();
    const nav = screen.getByRole('navigation', { name: 'Pages of the space' });
    expect(nav.querySelector('a[rel="prev"]')).toHaveAttribute('href', '/page/welcome');
    expect(nav.querySelector('a[rel="next"]')).toHaveAttribute('href', '/page/intro');
    expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
  });

  it('gives the managers Edit and Delete', () => {
    render(
      <AppDataProvider>
        <SpacePage {...props} previous={null} next={null} permissions={{ canManagePages: true }} />
      </AppDataProvider>,
    );

    expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
  });

  const lesson = {
    ...props,
    space: { ...props.space, type: 'course' } as Space,
    section: null,
    permissions: { canManagePages: false },
  };
  const course = (completed: string[], next: string | null) => ({
    total: 3,
    completed: completed.length,
    completed_lessons: completed,
    next_lesson: next,
  });

  it('lets a member mark a lesson as done and not done (#1304)', async () => {
    const complete = vi
      .spyOn(knowiiApiClient.communities.pages, 'complete')
      .mockResolvedValue({ ok: true, message: '', data: course(['p2'], 'p3') });
    const reopen = vi
      .spyOn(knowiiApiClient.communities.pages, 'reopen')
      .mockResolvedValue({ ok: true, message: '', data: course([], 'p1') });
    render(
      <AppDataProvider>
        <SpacePage {...lesson} progress={{ done: false, course: course([], 'p1') }} />
      </AppDataProvider>,
    );

    expect(screen.getByText('Lesson 2 of 3 · 0 of 3 done')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Mark as done' }));

    expect(complete).toHaveBeenCalledWith('c1', 's1', 'p2');
    expect(await screen.findByRole('status')).toHaveTextContent('Lesson done');
    expect(screen.getByText('Lesson 2 of 3 · 1 of 3 done')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Mark as not done' }));
    expect(reopen).toHaveBeenCalledWith('c1', 's1', 'p2');
    expect(await screen.findByRole('button', { name: 'Mark as done' })).toBeInTheDocument();
  });

  it('marks the lesson as done when the member goes to the next one (#1304)', async () => {
    const complete = vi
      .spyOn(knowiiApiClient.communities.pages, 'complete')
      .mockResolvedValue({ ok: true, message: '', data: course(['p2'], 'p3') });
    render(
      <AppDataProvider>
        <SpacePage {...lesson} progress={{ done: false, course: course([], 'p1') }} />
      </AppDataProvider>,
    );

    await userEvent.click(screen.getByRole('link', { name: /Next/ }));

    expect(complete).toHaveBeenCalledTimes(1);
    await waitFor(() => expect(router.visit).toHaveBeenCalledWith('/page/intro'));
  });

  it('shows no progress controls to a reader who is not a member (#1304)', () => {
    render(
      <AppDataProvider>
        <SpacePage {...lesson} progress={null} />
      </AppDataProvider>,
    );

    expect(screen.queryByRole('button', { name: 'Mark as done' })).toBeNull();
    expect(screen.getByText('Lesson 2 of 3')).toBeInTheDocument();
  });
});
