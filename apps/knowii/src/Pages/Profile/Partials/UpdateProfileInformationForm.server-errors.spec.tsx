import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, router } from '@inertiajs/react';
import type * as Common from '@knowii/common';
import type { CurrentUser } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import UpdateProfileInformationForm from './UpdateProfileInformationForm';

/*
 * The errors of the server in the profile form (#1688), with the real form of Inertia: only the network is replaced. The
 * server validates the profile in the error bag "updateProfileInformation", so the page that comes back holds the errors
 * under that name (tests/Feature/ProfileHeadlineTest.php proves this shape and these texts). The other spec of the form
 * replaces the form of Inertia, so it cannot prove that an error of the server reaches its field.
 */

const page = vi.hoisted(() => ({
  props: {
    jetstream: { hasEmailVerification: true },
    userProfile: { name: 'Ada Lovelace', username: 'ada', headline: null, bio: null, location: null, phone: null, field_visibility: null },
  },
}));

vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));

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

const HEADLINE_ERROR = 'The headline field must not be greater than 80 characters.';
const EMAIL_ERROR = 'The email has already been taken.';

const PROFILE_PAGE = {
  component: 'Profile/Show',
  url: '/user/profile',
  version: 'spec',
  props: { errors: {} },
  flash: {},
  rescuedProps: [],
  rememberedState: {},
};

// The server of this spec: what it answers to the next save, and the requests that it got
const server = {
  errors: {} as Record<string, Record<string, string>>,
  requests: [] as { method: string; headers: Record<string, unknown>; data: Record<string, unknown> }[],
};

beforeAll(() => {
  router.init({
    initialPage: PROFILE_PAGE,
    resolveComponent: () => Promise.resolve(() => null),
    swapComponent: () => Promise.resolve(),
  });
  http.setClient({
    request: (config) => {
      server.requests.push({
        method: config.method,
        headers: { ...config.headers },
        data: config.data as Record<string, unknown>,
      });
      // The answer of the profile page after the redirect of the save, as the browser gets it
      return Promise.resolve({
        status: 200,
        headers: { 'x-inertia': 'true' },
        data: JSON.stringify({ ...PROFILE_PAGE, props: { errors: server.errors } }),
      });
    },
  });
});

afterEach(() => {
  server.errors = {};
  server.requests = [];
});

function renderForm() {
  return render(
    <AppDataProvider>
      <UpdateProfileInformationForm user={user} />
    </AppDataProvider>,
  );
}

const headlineField = () => screen.getByRole('textbox', { name: 'Headline' });
const saveButton = () => screen.getByRole('button', { name: 'Save' });
// "Save" is off with `disabled`, or with `aria-disabled` while it has the focus (a disabled button loses the focus, #1618)
const saveIsOff = () => saveButton().matches(':disabled, [aria-disabled="true"]');

describe('the errors of the server in the profile form (#1688)', () => {
  it('shows the error of the headline under its field, and ties it to the field', async () => {
    server.errors = { updateProfileInformation: { headline: HEADLINE_ERROR } };
    renderForm();

    expect(headlineField()).not.toHaveAttribute('aria-invalid');
    expect(headlineField()).toHaveAttribute('aria-describedby', 'headline-hint');

    await userEvent.type(headlineField(), 'Product designer');
    await userEvent.click(saveButton());

    const error = await screen.findByRole('alert');
    expect(error).toHaveTextContent(HEADLINE_ERROR);
    expect(error).toHaveAttribute('id', 'headline-error');
    expect(headlineField()).toHaveAttribute('aria-invalid', 'true');
    expect(headlineField()).toHaveAttribute('aria-describedby', 'headline-hint headline-error');
    expect(headlineField()).toHaveAccessibleDescription(`One line about you. It shows under your name. ${HEADLINE_ERROR}`);

    // The save named its error bag: without the name, the error stays under the name of the bag and no field shows it
    expect(server.requests).toHaveLength(1);
    expect(server.requests[0]?.method).toBe('put');
    expect(server.requests[0]?.headers['X-Inertia-Error-Bag']).toBe('updateProfileInformation');
  });

  it('removes the error of the headline when the member changes the headline, and saves again', async () => {
    server.errors = { updateProfileInformation: { headline: HEADLINE_ERROR } };
    renderForm();

    await userEvent.type(headlineField(), 'Product designer');
    await userEvent.click(saveButton());
    expect(await screen.findByRole('alert')).toHaveTextContent(HEADLINE_ERROR);
    expect(saveIsOff()).toBe(true);

    // "Save" does nothing while the error is there
    await userEvent.click(saveButton());
    expect(server.requests).toHaveLength(1);

    // The change of another field keeps the error of the headline
    await userEvent.type(screen.getByRole('textbox', { name: 'Bio' }), 'I write');
    expect(screen.getByRole('alert')).toHaveTextContent(HEADLINE_ERROR);
    expect(saveIsOff()).toBe(true);

    await userEvent.type(headlineField(), '!');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(headlineField()).not.toHaveAttribute('aria-invalid');
    expect(headlineField()).toHaveAttribute('aria-describedby', 'headline-hint');
    expect(saveIsOff()).toBe(false);

    server.errors = {};
    await userEvent.click(saveButton());

    await waitFor(() => expect(server.requests).toHaveLength(2));
    expect(server.requests[1]?.data).toMatchObject({ headline: 'Product designer!', bio: 'I write' });
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('removes the error of an email address that is already taken when the member changes the address, and saves again', async () => {
    server.errors = { updateProfileInformation: { email: EMAIL_ERROR } };
    renderForm();
    const email = screen.getByRole('textbox', { name: 'Email' });

    await userEvent.clear(email);
    await userEvent.type(email, 'taken@example.com');
    // A change of the address asks for the current password (#1706)
    await userEvent.type(screen.getByLabelText('Current password'), 'the-password');
    await userEvent.click(saveButton());
    expect(await screen.findByRole('alert')).toHaveTextContent(EMAIL_ERROR);
    expect(saveIsOff()).toBe(true);

    await userEvent.clear(email);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    await userEvent.type(email, 'free@example.com');
    expect(saveIsOff()).toBe(false);

    server.errors = {};
    await userEvent.click(saveButton());

    await waitFor(() => expect(server.requests).toHaveLength(2));
    expect(server.requests[1]?.data).toMatchObject({ email: 'free@example.com', current_password: 'the-password' });
  });

  it('removes the errors with "Reset", which puts the stored values back', async () => {
    server.errors = { updateProfileInformation: { headline: HEADLINE_ERROR, email: EMAIL_ERROR } };
    renderForm();

    await userEvent.type(headlineField(), 'Product designer');
    await userEvent.click(saveButton());
    expect(await screen.findAllByRole('alert')).toHaveLength(2);

    await userEvent.click(screen.getByRole('button', { name: 'Reset' }));

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(headlineField()).toHaveValue('');
    expect(saveIsOff()).toBe(false);
  });
});
