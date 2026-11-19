import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AppDataProvider } from '@knowii/common';
import { eventFixture, eventsCommunity, eventsRoute, eventsSpace } from '@/test-utils/events';
import EventFormPage from './Form';

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
  Head: () => null,
  router: { visit: vi.fn() },
  usePage: () => ({
    props: { auth: { user: { cuid: 'me', name: 'Ada Lovelace', username: 'ada', avatar_url: null, time_zone: 'Europe/Brussels' } } },
    url: '/',
  }),
}));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <main>{children}</main> }));
vi.mock('@/Components/Spaces/CommunityShell', () => ({
  CommunityShell: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

beforeAll(() => {
  Object.defineProperty(globalThis, 'route', { value: eventsRoute, writable: true });
});

const props = {
  community: eventsCommunity,
  space: eventsSpace,
  spaceGroups: [],
  timeZones: ['UTC', 'Europe/Brussels'],
  permissions: { canNotifyNow: false, canManageAllEvents: false },
};

describe('the page of the form of an event (#1514, design 6.3)', () => {
  it('is "New event" without an event, with the reader as the host, and names the form after its title', () => {
    render(
      <AppDataProvider>
        <EventFormPage {...props} event={null} />
      </AppDataProvider>,
    );

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(screen.getByRole('heading', { level: 1, name: 'New event' })).toBeInTheDocument();
    expect(screen.getByRole('form', { name: 'New event' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Remove Ada Lovelace' })).toBeInTheDocument();
    expect(screen.getByLabelText('Title')).toHaveValue('');
  });

  it('is "Edit the event" with an event, filled with its values and its host', () => {
    const event = eventFixture('e1', 'Weekly review', {
      body: 'Bring questions',
      host: { cuid: 'u2', name: 'Grace Hopper', username: 'grace', avatar_url: null },
    });
    render(
      <AppDataProvider>
        <EventFormPage {...props} event={event} />
      </AppDataProvider>,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'Edit the event' })).toBeInTheDocument();
    expect(screen.getByLabelText('Title')).toHaveValue('Weekly review');
    expect(screen.getByLabelText('Meeting address')).toHaveValue('https://meet.example.com/abc');
    expect(screen.getByRole('button', { name: 'Remove Grace Hopper' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument();
  });
});
