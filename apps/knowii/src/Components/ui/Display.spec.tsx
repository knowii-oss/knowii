import { describe, expect, it, vi, afterEach } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import { AppDataProvider, DEFAULT_TOAST_LIFE, useAppData } from '@knowii/common';
import type { ToastMessage } from '@knowii/common';
import { ApplicationToolkitProvider } from '@/Components/ApplicationToolkit';
import { Avatar, getInitials } from './Avatar';
import { Breadcrumb } from './Breadcrumb';
import { Card, CardBody, CardHeader } from './Card';
import { Collapsible } from './Collapsible';
import { Spinner } from './Spinner';
import { Stepper } from './Stepper';
import { Toaster } from './Toaster';

describe('Breadcrumb', () => {
  it('links the parents and marks the last item as the current page', () => {
    render(
      <Breadcrumb
        items={[{ label: 'Home', href: '/dashboard' }, { label: 'My community', href: '/communities/mine' }, { label: 'Settings' }]}
      />,
    );
    const nav = screen.getByRole('navigation', { name: 'Breadcrumb' });
    expect(within(nav).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/dashboard');
    expect(within(nav).getByRole('link', { name: 'My community' })).toBeInTheDocument();
    expect(within(nav).queryByRole('link', { name: 'Settings' })).not.toBeInTheDocument();
    expect(within(nav).getByText('Settings').closest('[aria-current]')).toHaveAttribute('aria-current', 'page');
  });

  it('hides the middle of a long path on phones until the member asks for it (#909)', async () => {
    render(
      <Breadcrumb
        items={[
          { label: 'Home', href: '/dashboard' },
          { label: 'My community', href: '/communities/mine' },
          { label: 'General', href: '/communities/mine/general' },
          { label: 'A post' },
        ]}
      />,
    );
    const nav = screen.getByRole('navigation', { name: 'Breadcrumb' });
    // Only "My community" is between the first item and the parent of the open page
    expect(within(nav).getByRole('link', { name: 'My community' }).closest('li')).toHaveAttribute('data-collapsed');
    expect(within(nav).getByRole('link', { name: 'General' }).closest('li')).not.toHaveAttribute('data-collapsed');

    await userEvent.click(within(nav).getByRole('button', { name: 'Show the full path' }));

    expect(within(nav).getByRole('link', { name: 'My community' }).closest('li')).not.toHaveAttribute('data-collapsed');
    expect(within(nav).queryByRole('button', { name: 'Show the full path' })).toBeNull();
    expect(within(nav).getByRole('link', { name: 'My community' })).toHaveFocus();
  });

  it('keeps a short path complete', () => {
    render(<Breadcrumb items={[{ label: 'Home', href: '/dashboard' }, { label: 'My community', href: '/c' }, { label: 'About' }]} />);
    expect(screen.queryByRole('button', { name: 'Show the full path' })).toBeNull();
  });
});

describe('Avatar', () => {
  it('computes the initials', () => {
    expect(getInitials('Ada Lovelace')).toBe('AL');
    expect(getInitials('  grace  ')).toBe('G');
    expect(getInitials('Jean Paul Sartre')).toBe('JS');
    expect(getInitials(null)).toBe('?');
  });

  it('is decorative', () => {
    const { container } = render(<Avatar name="Ada Lovelace" />);
    expect(container.firstChild).toHaveAttribute('aria-hidden', 'true');
    expect(container).toHaveTextContent('AL');
  });
});

describe('Card', () => {
  it('renders a section with a heading', () => {
    render(
      <Card aria-labelledby="title">
        <CardHeader title={<span id="title">Recent resources</span>} actions={<button type="button">Add</button>} />
        <CardBody>No resources yet.</CardBody>
      </Card>,
    );
    expect(screen.getByRole('heading', { level: 2, name: 'Recent resources' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Recent resources' })).toHaveTextContent('No resources yet.');
  });
});

describe('Collapsible', () => {
  it('opens and closes from its summary', async () => {
    render(
      <Collapsible title="Social links">
        <span>Content</span>
      </Collapsible>,
    );
    const details = screen.getByText('Social links').closest('details');
    expect(details).not.toHaveAttribute('open');
    await userEvent.click(screen.getByText('Social links'));
    expect(details).toHaveAttribute('open');
  });
});

describe('Spinner', () => {
  it('announces its label', () => {
    render(<Spinner label="Creating the community" />);
    expect(screen.getByRole('status')).toHaveTextContent('Creating the community');
  });
});

describe('Stepper', () => {
  it('marks the current step', () => {
    render(<Stepper steps={['Overview', 'Details']} currentStep={1} />);
    const steps = screen.getAllByRole('listitem');
    expect(steps[0]).not.toHaveAttribute('aria-current');
    expect(steps[0]).toHaveTextContent('(completed)');
    expect(steps[1]).toHaveAttribute('aria-current', 'step');
  });
});

describe('Toaster', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  let showToast: (message: ToastMessage | ToastMessage[]) => void = () => undefined;

  function CaptureShowToast() {
    showToast = useAppData().showToast;
    return null;
  }

  // As in main.tsx: the link of a toast is the Inertia link of the application
  function renderToaster() {
    render(
      <ApplicationToolkitProvider>
        <AppDataProvider>
          <Toaster />
          <CaptureShowToast />
        </AppDataProvider>
      </ApplicationToolkitProvider>,
    );
  }

  it('shows toasts in a live region and dismisses them after their life', () => {
    vi.useFakeTimers();
    renderToaster();

    act(() => showToast({ severity: 'success', summary: 'Community created', detail: 'Welcome', life: 1000 }));

    const region = screen.getByRole('region', { name: 'Notifications' });
    expect(within(region).getByText('Community created')).toBeInTheDocument();
    expect(within(region).getByText('Welcome')).toBeInTheDocument();
    expect(region.querySelector('[aria-live="polite"]')).not.toBeNull();

    act(() => vi.advanceTimersByTime(1001));
    expect(screen.queryByText('Community created')).not.toBeInTheDocument();
  });

  it('announces errors as alerts and keeps sticky toasts until dismissed', async () => {
    renderToaster();
    act(() => showToast([{ severity: 'error', summary: 'Failed', sticky: true }]));

    // The text moves into the alert once the alert is in the page (#1651)
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Failed'));
    expect(screen.getByRole('alert').closest('li')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Dismiss notification' }));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('pauses the timer while the pointer is on the toast', () => {
    vi.useFakeTimers();
    renderToaster();
    act(() => showToast({ severity: 'info', summary: 'Hello', life: 1000 }));

    const toast = screen.getByText('Hello').closest('li');
    if (!toast) {
      throw new Error('toast not found');
    }
    act(() => {
      toast.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    });
    act(() => vi.advanceTimersByTime(5000));
    expect(screen.getByText('Hello')).toBeInTheDocument();

    act(() => {
      toast.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
    });
    act(() => vi.advanceTimersByTime(1001));
    expect(screen.queryByText('Hello')).not.toBeInTheDocument();
  });

  it('keeps a toast with a link until the user follows it or dismisses it (#1576)', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    vi.useFakeTimers();
    renderToaster();
    act(() => showToast({ severity: 'success', summary: 'Space created', action: { label: 'Open the space', href: '/space/lounge' } }));

    // Far more than the life of a toast: a link needs time to be reached with the keyboard (WCAG 2.2.1)
    act(() => vi.advanceTimersByTime(DEFAULT_TOAST_LIFE * 20));
    vi.useRealTimers();
    const region = screen.getByRole('region', { name: 'Notifications' });
    const link = within(region).getByRole('link', { name: 'Open the space' });
    expect(link).toHaveAttribute('href', '/space/lounge');

    await userEvent.click(link);
    expect(visit).toHaveBeenCalledOnce();
    expect(within(region).queryByText('Space created')).not.toBeInTheDocument();
    visit.mockRestore();
  });

  it('gives the focus back to the page when a toast that holds it is dismissed', async () => {
    render(
      <AppDataProvider>
        <Toaster />
        <CaptureShowToast />
        <button type="button">Add a space</button>
      </AppDataProvider>,
    );
    act(() => showToast({ severity: 'success', summary: 'Space created', action: { label: 'Open the space', href: '/space/lounge' } }));

    const trigger = screen.getByRole('button', { name: 'Add a space' });
    trigger.focus();
    // Shift+Tab from the page reaches the toast: the toaster is before the page
    await userEvent.tab({ shift: true });
    expect(screen.getByRole('button', { name: 'Dismiss notification' })).toHaveFocus();

    await userEvent.keyboard('{Enter}');
    expect(screen.queryByText('Space created')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });
});
