import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ConfirmsPassword from './ConfirmsPassword';

const axiosGet = vi.fn();
const axiosPost = vi.fn();

const routerGet = vi.fn();

vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@inertiajs/react', () => ({ router: { get: (...args: unknown[]) => routerGet(...args) } }));
vi.mock('axios', () => ({
  default: { get: (...args: unknown[]) => axiosGet(...args), post: (...args: unknown[]) => axiosPost(...args) },
}));

const onConfirm = vi.fn();

function renderDialog() {
  render(<ConfirmsPassword onConfirm={onConfirm}>{(start) => <button onClick={start}>Turn on</button>}</ConfirmsPassword>);
}

async function typeThePassword(password: string) {
  await userEvent.click(screen.getByRole('button', { name: 'Turn on' }));
  await userEvent.type(await screen.findByLabelText('Password'), password);
  await userEvent.click(screen.getByRole('button', { name: 'Confirm' }));
}

beforeEach(() => {
  axiosGet.mockReset().mockResolvedValue({ data: { confirmed: false } });
  axiosPost.mockReset();
  routerGet.mockReset();
  onConfirm.mockReset();
});

describe('ConfirmsPassword', () => {
  it('goes on without the dialog when the password was confirmed a moment ago', async () => {
    axiosGet.mockResolvedValue({ data: { confirmed: true } });
    renderDialog();

    await userEvent.click(screen.getByRole('button', { name: 'Turn on' }));

    expect(onConfirm).toHaveBeenCalledTimes(1);
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
  });

  it('goes on after the right password', async () => {
    axiosPost.mockResolvedValue({ data: {} });
    renderDialog();

    await typeThePassword('the-password');

    expect(axiosPost).toHaveBeenCalledWith('/password.confirm', { password: 'the-password' });
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('shows the message of the server for a wrong password, and keeps the dialog open', async () => {
    axiosPost.mockRejectedValue({
      response: { status: 400, data: { errors: { password: ['This password does not match our records.'] } } },
    });
    renderDialog();

    await typeThePassword('a-wrong-password');

    expect(await screen.findByText('This password does not match our records.')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toHaveFocus();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  it('goes to the sign-in page when the session ended at its last wrong password (401)', async () => {
    axiosPost.mockRejectedValue({ response: { status: 401, data: { message: 'Too many wrong passwords. Sign in again.' } } });
    renderDialog();

    await typeThePassword('a-wrong-password');

    await vi.waitFor(() => expect(routerGet).toHaveBeenCalledWith('/login'));
    await vi.waitFor(() => expect(screen.queryByLabelText('Password')).not.toBeInTheDocument());
    expect(onConfirm).not.toHaveBeenCalled();
  });
});
