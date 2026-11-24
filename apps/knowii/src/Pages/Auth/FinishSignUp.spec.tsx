import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as Common from '@knowii/common';
import FinishSignUpPage from './FinishSignUp';

const posts: Array<[string, Record<string, unknown>]> = [];
// A test sets the errors of the page
const formErrors = vi.hoisted((): { current: Record<string, string> } => ({ current: {} }));

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
        errors: formErrors.current,
      };
    },
  };
});
vi.mock('ziggy-js', () => ({
  useRoute: () => (name: string, parameters?: Record<string, string>) =>
    `/${name}${parameters?.['token'] ? `/${parameters['token']}` : ''}`,
}));
vi.mock('@/Components/Footer', () => ({ default: () => null }));
// A test turns the terms checkbox on
const jetstream = vi.hoisted(() => ({ hasTermsAndPrivacyPolicyFeature: false }));
// The legal texts of the instance (#1653): a test sets the ones that exist, or removes the prop
const shared = vi.hoisted((): { legalPages?: string[] } => ({ legalPages: ['privacy-policy', 'terms-of-service'] }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => ({ props: { jetstream, ...shared } }),
}));

const renderValid = () => render(<FinishSignUpPage token="a-token" valid email="grace@example.com" name="Grace Hopper" />);

const fillIn = async () => {
  await userEvent.type(screen.getByLabelText('Password', { exact: true }), 'A-strong-password-2026!');
  await userEvent.type(screen.getByLabelText('Confirm password'), 'A-strong-password-2026!');
};

describe('FinishSignUpPage (ADR 0065)', () => {
  it('names the address, keeps the name of the form, and sends the page to its link', async () => {
    renderValid();

    expect(screen.getByText('Finish your sign-up')).toBeInTheDocument();
    expect(screen.getByText(/Choose a password to create your account for/)).toHaveTextContent(
      'Choose a password to create your account for grace@example.com.',
    );
    expect(screen.getByLabelText('Name')).toHaveValue('Grace Hopper');

    await fillIn();
    await userEvent.click(screen.getByRole('button', { name: 'Create my account' }));

    expect(posts.at(-1)?.[0]).toBe('/register.link.store/a-token');
    expect(posts.at(-1)?.[1]).toMatchObject({
      name: 'Grace Hopper',
      password: 'A-strong-password-2026!',
      password_confirmation: 'A-strong-password-2026!',
    });
  });

  it('shows the words of a link that does not work, with a link to the sign-up', () => {
    render(<FinishSignUpPage token="a-token" valid={false} email={null} name={null} />);

    expect(screen.getByText('This link expired or was already used. Sign up again.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sign up' })).toHaveAttribute('href', '/register');
    expect(screen.queryByRole('button', { name: 'Create my account' })).toBeNull();
  });

  it('shows the error of a sign-up that did not work in the place of the words of the link', () => {
    formErrors.current = { email: 'We could not create this account. Please try again.' };
    try {
      render(<FinishSignUpPage token="a-token" valid={false} email={null} name={null} />);

      expect(screen.getByText('We could not create this account. Please try again.')).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Sign up' })).toBeInTheDocument();
    } finally {
      formErrors.current = {};
    }
  });
});

describe('FinishSignUpPage and the time zone (#1476)', () => {
  const realZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  it('sends the zone of the browser in a hidden field that shows nothing', async () => {
    renderValid();

    const field = document.querySelector<HTMLInputElement>('input[name="time_zone"]');
    expect(field).not.toBeNull();
    expect(field?.type).toBe('hidden');
    expect(field?.value).toBe(realZone);
    expect(screen.queryByLabelText(/time zone/i)).toBeNull();

    await fillIn();
    await userEvent.click(screen.getByRole('button', { name: 'Create my account' }));
    expect(posts.at(-1)?.[1]).toMatchObject({ time_zone: realZone });
  });

  it('sends an empty value and does not fail when the browser gives no zone', async () => {
    vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(function () {
      throw new Error('no Intl');
    } as unknown as typeof Intl.DateTimeFormat);
    renderValid();
    vi.restoreAllMocks();

    expect(document.querySelector<HTMLInputElement>('input[name="time_zone"]')?.value).toBe('');
    await fillIn();
    await userEvent.click(screen.getByRole('button', { name: 'Create my account' }));
    expect(posts.at(-1)?.[1]).toMatchObject({ time_zone: '' });
  });
});

describe('FinishSignUpPage, the box of the terms and the legal texts of the instance (#1350, #1653)', () => {
  const renderWith = (legalPages: string[] | undefined) => {
    jetstream.hasTermsAndPrivacyPolicyFeature = true;
    if (legalPages) {
      shared.legalPages = legalPages;
    } else {
      delete shared.legalPages;
    }
    return renderValid();
  };
  const restore = () => {
    jetstream.hasTermsAndPrivacyPolicyFeature = false;
    shared.legalPages = ['privacy-policy', 'terms-of-service'];
  };

  it('asks the person to confirm that they are 16 or older with the terms, and names both texts', () => {
    try {
      renderWith(['privacy-policy', 'terms-of-service', 'dpa', 'imprint', 'content-policy', 'security']);

      const checkbox = screen.getByRole('checkbox', {
        name: 'I am 16 or older and I agree to the Terms of Service and Privacy Policy',
      });
      expect(checkbox).toBeRequired();
      expect(screen.getByRole('link', { name: 'Terms of Service' })).toHaveAttribute('href', '/terms-of-service.show');
      expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/privacy-policy.show');
      // No date of birth: the checkbox is enough
      expect(screen.queryByLabelText(/birth/i)).toBeNull();
    } finally {
      restore();
    }
  });

  it('names only the terms when the instance has no privacy policy', () => {
    try {
      renderWith(['terms-of-service']);

      expect(screen.getByRole('checkbox', { name: 'I am 16 or older and I agree to the Terms of Service' })).toBeRequired();
      expect(screen.queryByRole('link', { name: 'Privacy Policy' })).toBeNull();
    } finally {
      restore();
    }
  });

  it('names only the privacy policy when the instance has no terms', () => {
    try {
      renderWith(['privacy-policy', 'security']);

      expect(screen.getByRole('checkbox', { name: 'I am 16 or older and I agree to the Privacy Policy' })).toBeRequired();
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
        expect(screen.queryByText(/I agree/)).toBeNull();
        unmount();
      } finally {
        restore();
      }
    }
  });

  it('sends the box', async () => {
    try {
      renderWith([]);
      const sent = posts.length;

      await fillIn();
      await userEvent.click(screen.getByRole('checkbox', { name: 'I am 16 or older' }));
      await userEvent.click(screen.getByRole('button', { name: 'Create my account' }));

      expect(posts).toHaveLength(sent + 1);
      expect(posts.at(-1)?.[1]).toMatchObject({ name: 'Grace Hopper', terms: true });
    } finally {
      restore();
    }
  });
});
