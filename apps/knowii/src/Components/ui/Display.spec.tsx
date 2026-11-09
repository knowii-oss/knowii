import { describe, expect, it, vi, afterEach } from 'vitest';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppDataProvider, useAppData } from '@knowii/common';
import type { ToastMessage } from '@knowii/common';
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

  function renderToaster() {
    render(
      <AppDataProvider>
        <Toaster />
        <CaptureShowToast />
      </AppDataProvider>,
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

    expect(screen.getByRole('alert')).toHaveTextContent('Failed');
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
});
