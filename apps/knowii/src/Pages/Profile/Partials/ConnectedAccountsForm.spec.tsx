import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as Common from '@knowii/common';
import { AppDataProvider, knowiiApiClient } from '@knowii/common';
import type { ConnectedAccount } from '@knowii/common';
import ConnectedAccountsForm from './ConnectedAccountsForm';

const reload = vi.hoisted(() => vi.fn());
const post = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({ router: { reload, post } }));
// The password confirmation asks the server first: here the password was confirmed a moment ago
const axiosGet = vi.hoisted(() => vi.fn());
vi.mock('axios', () => ({ default: { get: axiosGet, post: vi.fn() } }));
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/route/${name}` }));

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => page,
}));

const account: ConnectedAccount = {
  cuid: 'identity-cuid',
  issuer: 'https://auth.example.test',
  label: 'Knowii account',
  linked_at: '2026-11-15T10:00:00+00:00',
  last_used_at: null,
  current: true,
};

function renderCard(accounts: ConnectedAccount[], hasPassword = true) {
  return render(
    <AppDataProvider>
      <ConnectedAccountsForm accounts={accounts} hasPassword={hasPassword} />
    </AppDataProvider>,
  );
}

describe('ConnectedAccountsForm (#1385)', () => {
  it('shows nothing when the instance has no provider', () => {
    page.props = { oidc: null };
    const { container } = renderCard([]);

    expect(container).toBeEmptyDOMElement();
  });

  it('says that the account is not connected, and Connect posts after the password was confirmed, never a link', async () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    post.mockClear();
    axiosGet.mockResolvedValue({ data: { confirmed: true } });
    renderCard([]);

    expect(screen.getByRole('heading', { name: 'Connected accounts' })).toBeInTheDocument();
    expect(screen.getByText('Not connected')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Connect/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Disconnect/ })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Connect Knowii account' }));

    expect(axiosGet).toHaveBeenCalledWith('/route/password.confirmation');
    await waitFor(() => expect(post).toHaveBeenCalledWith('/route/oidc.connect'));
  });

  it('asks for the password first when it was not confirmed a moment ago', async () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    post.mockClear();
    axiosGet.mockResolvedValue({ data: { confirmed: false } });
    renderCard([]);

    await userEvent.click(screen.getByRole('button', { name: 'Connect Knowii account' }));

    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
  });

  it('an account without a password connects at once', async () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    post.mockClear();
    axiosGet.mockClear();
    renderCard([], false);

    await userEvent.click(screen.getByRole('button', { name: 'Connect Knowii account' }));

    expect(axiosGet).not.toHaveBeenCalled();
    expect(post).toHaveBeenCalledWith('/route/oidc.connect');
  });

  it('shows the date of the connection and Disconnect, and no Connect', () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    renderCard([account]);

    expect(screen.getByText(/Connected on/)).toHaveTextContent('2026');
    expect(screen.getByRole('button', { name: 'Disconnect Knowii account' })).toBeEnabled();
    expect(screen.queryByRole('link', { name: /Connect/ })).not.toBeInTheDocument();
    expect(screen.queryByText('Not connected')).not.toBeInTheDocument();
  });

  it('disconnects through the API, then reloads the card', async () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    const remove = vi.spyOn(knowiiApiClient.users.identities, 'remove').mockResolvedValueOnce({ ok: true, message: '', data: undefined });
    renderCard([account]);

    await userEvent.click(screen.getByRole('button', { name: 'Disconnect Knowii account' }));

    expect(remove).toHaveBeenCalledWith('identity-cuid');
    await waitFor(() => expect(reload).toHaveBeenCalledWith({ only: ['connectedAccounts', 'hasPassword', 'entitlements'] }));
  });

  it('keeps the card as it was when the API refuses', async () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    reload.mockClear();
    vi.spyOn(knowiiApiClient.users.identities, 'remove').mockResolvedValueOnce({
      ok: false,
      status: 500,
      message: 'Failed to disconnect the account.',
      errors: {},
    });
    renderCard([account]);

    await userEvent.click(screen.getByRole('button', { name: 'Disconnect Knowii account' }));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Disconnect Knowii account' })).toBeEnabled());
    expect(reload).not.toHaveBeenCalled();
  });

  it('says in one sentence that the account has no password, and only then', () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    const { unmount } = renderCard([account], false);

    expect(screen.getByText(/This account has no password/)).toHaveTextContent('Forgot your password?');
    unmount();

    renderCard([account], true);
    expect(screen.queryByText(/This account has no password/)).not.toBeInTheDocument();
  });

  it('shows the error and the status of the callback', () => {
    page.props = {
      oidc: { label: 'Knowii account', error: 'This account is already connected to another member of this site.', status: null },
    };
    const { unmount } = renderCard([]);

    expect(screen.getByRole('alert')).toHaveTextContent('already connected to another member');
    unmount();

    page.props = { oidc: { label: 'Knowii account', error: null, status: 'Connected.' } };
    renderCard([account]);
    expect(screen.getByRole('status')).toHaveTextContent('Connected.');
  });

  it('lists an account of an earlier provider so that it can be removed, and still offers Connect', () => {
    page.props = { oidc: { label: 'Knowii account', error: null, status: null } };
    renderCard([{ ...account, cuid: 'old', issuer: 'https://old.example.test', label: 'old.example.test', current: false }]);

    expect(screen.getByRole('button', { name: 'Disconnect old.example.test' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Connect Knowii account' })).toBeInTheDocument();
  });
});
