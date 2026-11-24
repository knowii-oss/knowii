import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, router } from '@inertiajs/react';
import type * as Common from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import UpdatePasswordForm from './UpdatePasswordForm';

/*
 * The errors of the server in the form "Update Password", with the real form of Inertia: only the network is replaced. The
 * server validates the form in the error bag "updatePassword", so the page that comes back holds the errors under that
 * name (tests/Feature/PasswordTriesTest.php proves this shape and this text). A member sees each wrong current password
 * under the field.
 */

const page = vi.hoisted(() => ({ props: { auth: { user: { email: 'ada@example.com' } } } }));

vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));

const WRONG_PASSWORD = 'The provided password does not match your current password.';
const SHORT_PASSWORD = 'The password field must be at least 12 characters.';

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
      <UpdatePasswordForm />
    </AppDataProvider>,
  );
}

const currentPassword = () => screen.getByLabelText('Current Password');
const newPassword = () => screen.getByLabelText('New Password');
const confirmation = () => screen.getByLabelText('Confirm Password');

async function fillAndSave(current: string, next: string) {
  await userEvent.type(currentPassword(), current);
  await userEvent.type(newPassword(), next);
  await userEvent.type(confirmation(), next);
  await userEvent.click(screen.getByRole('button', { name: 'Save' }));
}

describe('the errors of the server in the form "Update Password"', () => {
  it('shows the error of a wrong current password under its field, and ties it to the field', async () => {
    server.errors = { updatePassword: { current_password: WRONG_PASSWORD } };
    renderForm();

    expect(currentPassword()).not.toHaveAttribute('aria-invalid');

    await fillAndSave('not-my-password', 'A-new-password-of-2026!');

    const error = await screen.findByRole('alert');
    expect(error).toHaveTextContent(WRONG_PASSWORD);
    expect(error).toHaveAttribute('id', 'current_password-error');
    expect(currentPassword()).toHaveAttribute('aria-invalid', 'true');
    expect(currentPassword()).toHaveAccessibleDescription(WRONG_PASSWORD);

    // The field is empty again, for the next try
    await waitFor(() => expect(currentPassword()).toHaveValue(''));
    expect(newPassword()).toHaveValue('A-new-password-of-2026!');

    // The save named its error bag: without the name, the error stays under the name of the bag and no field shows it
    expect(server.requests).toHaveLength(1);
    expect(server.requests[0]?.method).toBe('put');
    expect(server.requests[0]?.headers['X-Inertia-Error-Bag']).toBe('updatePassword');
    expect(server.requests[0]?.data).toMatchObject({ current_password: 'not-my-password', password: 'A-new-password-of-2026!' });
  });

  it('shows the error again for each wrong current password', async () => {
    server.errors = { updatePassword: { current_password: WRONG_PASSWORD } };
    renderForm();

    await fillAndSave('not-my-password', 'A-new-password-of-2026!');
    expect(await screen.findByRole('alert')).toHaveTextContent(WRONG_PASSWORD);

    await userEvent.type(currentPassword(), 'also-not-my-password');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(server.requests).toHaveLength(2));
    expect(await screen.findByRole('alert')).toHaveTextContent(WRONG_PASSWORD);
    expect(server.requests[1]?.data).toMatchObject({ current_password: 'also-not-my-password' });
  });

  it('shows the error of a new password under its field', async () => {
    server.errors = { updatePassword: { password: SHORT_PASSWORD } };
    renderForm();

    await fillAndSave('my-password', 'short');

    expect(await screen.findByRole('alert')).toHaveTextContent(SHORT_PASSWORD);
    await waitFor(() => expect(newPassword()).toHaveValue(''));
    expect(confirmation()).toHaveValue('');
    expect(currentPassword()).toHaveValue('my-password');
  });

  it('shows no error after a save that worked, and empties the fields', async () => {
    renderForm();

    await fillAndSave('my-password', 'A-new-password-of-2026!');

    await waitFor(() => expect(currentPassword()).toHaveValue(''));
    expect(newPassword()).toHaveValue('');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
