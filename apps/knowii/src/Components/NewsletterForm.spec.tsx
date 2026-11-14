import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import NewsletterForm from './NewsletterForm';

const subscribe = vi.fn();

vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<Record<string, unknown>>()),
  knowiiApiClient: { newsletter: { subscribe: (...args: unknown[]) => subscribe(...args) } },
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
// Turnstile gives a token as soon as it is mounted
vi.mock('@/Components/ui/Turnstile', () => ({
  Turnstile: ({ onTokenChange }: { onTokenChange: (token: string) => void }) => {
    queueMicrotask(() => onTokenChange('token-1'));
    return <div data-testid="turnstile" />;
  },
}));

describe('NewsletterForm', () => {
  it('loads the bot check only when the member starts to use the form (#938)', async () => {
    render(<NewsletterForm turnstileSiteKey="site-key" />);
    expect(screen.queryByTestId('turnstile')).toBeNull();

    await userEvent.click(screen.getByRole('textbox', { name: 'Email address' }));

    expect(screen.getByTestId('turnstile')).toBeInTheDocument();
  });

  it('subscribes the address and shows the answer of the server', async () => {
    subscribe.mockResolvedValue({
      type: 'success',
      category: 'success',
      message: 'Almost there! Check your inbox.',
      data: null,
      errors: [],
    });
    render(<NewsletterForm turnstileSiteKey="site-key" defaultEmail="ada@example.com" />);

    await userEvent.click(screen.getByRole('textbox', { name: 'Email address' }));
    await userEvent.click(screen.getByRole('button', { name: 'Subscribe' }));

    expect(subscribe).toHaveBeenCalledWith(expect.objectContaining({ email: 'ada@example.com', turnstileToken: 'token-1', website: '' }));
    expect(await screen.findByRole('status')).toHaveTextContent('Check your inbox');
  });

  it('says what the person agrees to, with a link to the privacy policy (#1346)', () => {
    render(<NewsletterForm turnstileSiteKey="site-key" />);

    const consent = screen.getByText(/By subscribing, you agree to receive the Knowii newsletter by email/);
    expect(consent).toHaveTextContent(
      'By subscribing, you agree to receive the Knowii newsletter by email. You can unsubscribe in every email. See the privacy policy.',
    );
    expect(screen.getByRole('link', { name: 'privacy policy' })).toHaveAttribute('href', '/privacy-policy.show');
    expect(screen.getByRole('textbox', { name: 'Email address' })).toHaveAccessibleDescription(consent.textContent ?? '');
  });
});
