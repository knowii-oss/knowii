import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as Common from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import TwoFactorAuthenticationForm from './TwoFactorAuthenticationForm';

const routerPost = vi.fn();
const routerDelete = vi.fn();
let user: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
  router: { post: (...args: unknown[]) => routerPost(...args), delete: (...args: unknown[]) => routerDelete(...args) },
  useForm: () => ({ data: { code: '' }, setData: vi.fn(), post: vi.fn(), processing: false, errors: {} }),
}));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('axios', () => ({
  default: { get: vi.fn().mockResolvedValue({ data: { confirmed: true } }), post: vi.fn().mockResolvedValue({ data: {} }) },
}));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => ({ props: { auth: { user } } }),
}));

function renderForm() {
  render(
    <AppDataProvider>
      <TwoFactorAuthenticationForm requiresConfirmation={true} />
    </AppDataProvider>,
  );
}

beforeEach(() => {
  routerPost.mockReset();
  routerDelete.mockReset();
});

describe('TwoFactorAuthenticationForm', () => {
  it('turns two-factor authentication on after the password check (#690)', async () => {
    user = { two_factor_enabled: false, two_factor_confirmed_at: null };
    renderForm();

    expect(screen.getByText('Two-factor authentication is off.')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Turn on' }));

    await waitFor(() => expect(routerPost).toHaveBeenCalledWith('/two-factor.enable', {}, expect.anything()));
  });

  it('offers to finish a setup that was left before the confirmation (#690)', () => {
    user = { two_factor_enabled: true, two_factor_confirmed_at: null };
    renderForm();

    expect(screen.getByText('Finish turning on two-factor authentication.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show the QR code' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
  });

  it('shows the recovery codes and turns two-factor authentication off when it is on (#690)', async () => {
    user = { two_factor_enabled: true, two_factor_confirmed_at: '2026-11-09T10:00:00Z' };
    renderForm();

    expect(screen.getByText('Two-factor authentication is on.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show the recovery codes' })).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Turn off' }));

    await waitFor(() => expect(routerDelete).toHaveBeenCalledWith('/two-factor.disable', expect.anything()));
  });
});
