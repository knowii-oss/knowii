import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import type * as Common from '@knowii/common';
import type { CurrentUser } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import UpdateProfileInformationForm from './UpdateProfileInformationForm';

const page = vi.hoisted(() => ({
  props: {
    jetstream: { hasEmailVerification: true },
    userProfile: { name: 'Ada Lovelace', username: 'ada', bio: null, location: null, phone: null, field_visibility: null },
  },
}));

vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));
vi.mock('@inertiajs/react', () => ({
  Link: (props: { href: string; children: ReactNode }) => <a href={props.href}>{props.children}</a>,
  useForm: (data: Record<string, unknown>) => ({
    data,
    setData: vi.fn(),
    errors: {},
    hasErrors: false,
    processing: false,
    reset: vi.fn(),
    put: vi.fn(),
    clearErrors: vi.fn(),
    setError: vi.fn(),
  }),
}));

const user: CurrentUser = {
  cuid: 'user-cuid',
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  username: 'ada',
  avatar_url: null,
  two_factor_enabled: false,
  two_factor_confirmed_at: null,
  email_verified_at: new Date('2026-11-01T10:00:00Z'),
  created_at: new Date('2026-11-01T10:00:00Z'),
  updated_at: new Date('2026-11-01T10:00:00Z'),
};

const PRIVACY_TEXT =
  'Your name, username and picture show next to what you write. In a public space, visitors see them too. Your email address is never shown to other members. Bio, location and phone: you choose below.';

describe('UpdateProfileInformationForm', () => {
  it('says what the others see of the profile (#1566)', () => {
    render(
      <AppDataProvider>
        <UpdateProfileInformationForm user={user} />
      </AppDataProvider>,
    );

    expect(screen.getByText(PRIVACY_TEXT)).toBeInTheDocument();
  });

  it('no longer says that almost all of the profile is public (#1566)', () => {
    render(
      <AppDataProvider>
        <UpdateProfileInformationForm user={user} />
      </AppDataProvider>,
    );

    const text = document.body.textContent;
    expect(text).not.toContain("Update your account's profile information.");
    expect(text).not.toContain('Apart from your username');
    expect(text).not.toContain('all the information will be publicly visible to other Knowii users');
  });
});
