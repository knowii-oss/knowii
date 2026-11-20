import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as Common from '@knowii/common';
import type * as Ui from '@/Components/ui';
import RegisterPage from './Register';

const posts: Array<[string, Record<string, unknown>]> = [];

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    Head: () => null,
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    useForm: (initial: Record<string, unknown>) => {
      const [data, setState] = React.useState(initial);
      return {
        data,
        setData: (key: string, value: unknown) => setState((current) => ({ ...current, [key]: value })),
        post: (url: string) => posts.push([url, data]),
        reset: vi.fn(),
        processing: false,
        errors: {},
      };
    },
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Components/Footer', () => ({ default: () => null }));
// A test turns the terms checkbox on
const jetstream = vi.hoisted(() => ({ hasTermsAndPrivacyPolicyFeature: false }));
// The legal texts of the instance (#1653): a test sets the ones that exist, or removes the prop
const shared = vi.hoisted((): { legalPages?: string[] } => ({ legalPages: ['privacy-policy', 'terms-of-service'] }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => ({ props: { jetstream, ...shared } }),
}));
// The widget of Cloudflare cannot load in tests: a button stands for a solved check
vi.mock('@/Components/ui', async (importOriginal) => ({
  ...(await importOriginal<typeof Ui>()),
  Turnstile: ({ onTokenChange }: { onTokenChange: (token: string | null) => void }) => (
    <button type="button" onClick={() => onTokenChange('turnstile-ok')}>
      Solve the bot check
    </button>
  ),
}));

describe('RegisterPage and the time zone (#1476)', () => {
  const realZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const fillIn = async () => {
    await userEvent.type(screen.getByLabelText('Name'), 'Grace Hopper');
    await userEvent.type(screen.getByLabelText('Email'), 'grace@example.com');
    await userEvent.type(screen.getByLabelText('Password', { exact: true }), 'A-strong-password-2026!');
    await userEvent.type(screen.getByLabelText('Confirm password'), 'A-strong-password-2026!');
  };

  it('sends the zone of the browser in a hidden field that shows nothing', async () => {
    render(<RegisterPage turnstileSiteKey={null} />);

    const field = document.querySelector<HTMLInputElement>('input[name="time_zone"]');
    expect(field).not.toBeNull();
    expect(field?.type).toBe('hidden');
    expect(field?.value).toBe(realZone);
    expect(screen.queryByLabelText(/time zone/i)).toBeNull();

    await fillIn();
    await userEvent.click(screen.getByRole('button', { name: 'Register' }));
    expect(posts.at(-1)?.[1]).toMatchObject({ time_zone: realZone });
  });

  it('sends an empty value and does not fail when the browser gives no zone', async () => {
    vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(function () {
      throw new Error('no Intl');
    } as unknown as typeof Intl.DateTimeFormat);
    render(<RegisterPage turnstileSiteKey={null} />);
    vi.restoreAllMocks();

    expect(document.querySelector<HTMLInputElement>('input[name="time_zone"]')?.value).toBe('');
    await fillIn();
    await userEvent.click(screen.getByRole('button', { name: 'Register' }));
    expect(posts.at(-1)?.[1]).toMatchObject({ time_zone: '' });
  });
});

describe('RegisterPage', () => {
  it('waits for the bot check, then sends its token (#737)', async () => {
    render(<RegisterPage turnstileSiteKey="site-key" />);

    await userEvent.type(screen.getByLabelText('Name'), 'Grace Hopper');
    await userEvent.type(screen.getByLabelText('Email'), 'grace@example.com');
    await userEvent.type(screen.getByLabelText('Password', { exact: true }), 'A-strong-password-2026!');
    await userEvent.type(screen.getByLabelText('Confirm password'), 'A-strong-password-2026!');
    expect(screen.getByRole('button', { name: 'Register' })).toBeDisabled();
    await userEvent.click(screen.getByRole('button', { name: 'Solve the bot check' }));
    expect(screen.getByRole('button', { name: 'Register' })).toBeEnabled();
    await userEvent.click(screen.getByRole('button', { name: 'Register' }));

    expect(posts.at(-1)?.[1]).toMatchObject({ turnstile_token: 'turnstile-ok', website: '' });
  });

  it('has no bot check without Turnstile keys, and hides the honeypot (#737)', () => {
    render(<RegisterPage turnstileSiteKey={null} />);

    expect(screen.queryByRole('button', { name: 'Solve the bot check' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Register' })).toBeEnabled();
    expect(document.querySelector('[aria-hidden="true"] input[name="website"]')).not.toBeNull();
  });

  it('asks the person to confirm that they are 16 or older with the terms (#1350)', () => {
    jetstream.hasTermsAndPrivacyPolicyFeature = true;
    try {
      render(<RegisterPage turnstileSiteKey={null} />);

      const checkbox = screen.getByRole('checkbox', {
        name: 'I am 16 or older and I agree to the Terms of Service and Privacy Policy',
      });
      expect(checkbox).toBeRequired();
      expect(screen.getByRole('link', { name: 'Terms of Service' })).toHaveAttribute('href', '/terms-of-service.show');
      expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/privacy-policy.show');
      // No date of birth: the checkbox is enough
      expect(screen.queryByLabelText(/birth/i)).toBeNull();
    } finally {
      jetstream.hasTermsAndPrivacyPolicyFeature = false;
    }
  });

  describe('the box of the terms and the legal texts of the instance (#1653)', () => {
    const renderWith = (legalPages: string[] | undefined) => {
      jetstream.hasTermsAndPrivacyPolicyFeature = true;
      if (legalPages) {
        shared.legalPages = legalPages;
      } else {
        delete shared.legalPages;
      }
      return render(<RegisterPage turnstileSiteKey={null} />);
    };
    const restore = () => {
      jetstream.hasTermsAndPrivacyPolicyFeature = false;
      shared.legalPages = ['privacy-policy', 'terms-of-service'];
    };

    it('names the terms and the privacy policy when the instance has both', () => {
      try {
        renderWith(['privacy-policy', 'terms-of-service', 'dpa', 'imprint', 'content-policy', 'security']);

        expect(
          screen.getByRole('checkbox', { name: 'I am 16 or older and I agree to the Terms of Service and Privacy Policy' }),
        ).toBeRequired();
      } finally {
        restore();
      }
    });

    it('names only the terms when the instance has no privacy policy', () => {
      try {
        renderWith(['terms-of-service']);

        expect(screen.getByRole('checkbox', { name: 'I am 16 or older and I agree to the Terms of Service' })).toBeRequired();
        expect(screen.getByRole('link', { name: 'Terms of Service' })).toHaveAttribute('href', '/terms-of-service.show');
        expect(screen.queryByRole('link', { name: 'Privacy Policy' })).toBeNull();
      } finally {
        restore();
      }
    });

    it('names only the privacy policy when the instance has no terms', () => {
      try {
        renderWith(['privacy-policy', 'security']);

        expect(screen.getByRole('checkbox', { name: 'I am 16 or older and I agree to the Privacy Policy' })).toBeRequired();
        expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/privacy-policy.show');
        expect(screen.queryByRole('link', { name: 'Terms of Service' })).toBeNull();
      } finally {
        restore();
      }
    });

    it('keeps the box with the minimum age alone when the instance has neither, and without the prop', () => {
      for (const legalPages of [['imprint', 'dpa'], [], undefined]) {
        try {
          const { unmount } = renderWith(legalPages);

          // The box stays required: the server still validates it
          expect(screen.getByRole('checkbox', { name: 'I am 16 or older' })).toBeRequired();
          expect(screen.queryByRole('link', { name: 'Terms of Service' })).toBeNull();
          expect(screen.queryByRole('link', { name: 'Privacy Policy' })).toBeNull();
          expect(screen.queryByText(/I agree/)).toBeNull();
          unmount();
        } finally {
          restore();
        }
      }
    });

    it('sends the box in every case', async () => {
      try {
        renderWith([]);
        const sent = posts.length;

        await userEvent.type(screen.getByLabelText('Name'), 'Ada Lovelace');
        await userEvent.type(screen.getByLabelText('Email'), 'ada@example.com');
        await userEvent.type(screen.getByLabelText('Password', { exact: true }), 'A-strong-password-2026!');
        await userEvent.type(screen.getByLabelText('Confirm password'), 'A-strong-password-2026!');
        await userEvent.click(screen.getByRole('checkbox', { name: 'I am 16 or older' }));
        await userEvent.click(screen.getByRole('button', { name: 'Register' }));

        expect(posts).toHaveLength(sent + 1);
        expect(posts.at(-1)?.[1]).toMatchObject({ name: 'Ada Lovelace', terms: true });
      } finally {
        restore();
      }
    });
  });
});
