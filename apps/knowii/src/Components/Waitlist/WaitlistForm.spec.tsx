import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { knowiiApiClient } from '@knowii/common';
import type { TurnstileApi } from '@/Components/ui';
import WaitlistForm from './WaitlistForm';

describe('WaitlistForm', () => {
  let renderCount = 0;

  beforeEach(() => {
    renderCount = 0;
    const turnstileStub: TurnstileApi = {
      render: (_container, options) => {
        renderCount += 1;
        options.callback(`token-${renderCount}`);
        return `widget-${renderCount}`;
      },
      remove: vi.fn(),
    };
    window.turnstile = turnstileStub;
  });

  afterEach(() => {
    delete window.turnstile;
    vi.restoreAllMocks();
  });

  it('sends the email with the Turnstile token and shows the confirmation', async () => {
    const join = vi.spyOn(knowiiApiClient.waitlist, 'join').mockResolvedValue({
      category: 'business',
      type: 'success',
      message: 'Almost there! Check your inbox to confirm your email address.',
    });

    render(<WaitlistForm turnstileSiteKey="site-key" />);

    await userEvent.type(screen.getByRole('textbox', { name: 'Email address' }), 'ada@example.com');
    const subscribe = screen.getByRole('button', { name: 'Subscribe' });
    await waitFor(() => expect(subscribe).toBeEnabled());
    await userEvent.click(subscribe);

    expect(join).toHaveBeenCalledWith({ email: 'ada@example.com', turnstileToken: 'token-1', website: '' });
    expect(await screen.findByRole('status')).toHaveTextContent('Check your inbox');
  });

  it('validates the email address before calling the API', async () => {
    const join = vi.spyOn(knowiiApiClient.waitlist, 'join');

    render(<WaitlistForm turnstileSiteKey="site-key" />);

    await userEvent.type(screen.getByRole('textbox', { name: 'Email address' }), 'not-an-email');
    await userEvent.click(screen.getByRole('button', { name: 'Subscribe' }));

    expect(await screen.findByText('Please enter a valid email address.')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Email address' })).toHaveAttribute('aria-invalid', 'true');
    expect(join).not.toHaveBeenCalled();
  });

  it('shows the error and gets a new Turnstile token after a failure', async () => {
    vi.spyOn(knowiiApiClient.waitlist, 'join').mockResolvedValue({
      category: 'technical',
      type: 'internalError',
      message: 'The waitlist is not available right now. Please try again later.',
    });

    render(<WaitlistForm turnstileSiteKey="site-key" />);

    await userEvent.type(screen.getByRole('textbox', { name: 'Email address' }), 'ada@example.com');
    await userEvent.click(screen.getByRole('button', { name: 'Subscribe' }));

    expect(await screen.findByText('The waitlist is not available right now. Please try again later.')).toBeInTheDocument();
    await waitFor(() => expect(renderCount).toBe(2));
  });

  it('keeps the honeypot field out of the keyboard order and away from screen readers', () => {
    render(<WaitlistForm turnstileSiteKey="site-key" />);

    const honeypot = document.getElementById('waitlist-website');
    expect(honeypot).toHaveAttribute('tabindex', '-1');
    expect(honeypot?.closest('[aria-hidden="true"]')).not.toBeNull();
  });
});
