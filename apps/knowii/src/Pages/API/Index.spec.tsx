import { beforeAll, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ApiToken, Permission } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import ApiTokensPage from './Index';

const routerDelete = vi.fn();
const post = vi.fn();
// The options of the request (onSuccess): a test calls them to answer the request
const postOptions = vi.fn();

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    router: { delete: (...args: unknown[]) => routerDelete(...args) },
    useForm: (initial: Record<string, unknown>) => {
      const [data, setState] = React.useState(initial);
      return {
        data,
        setData: (key: string, value: unknown) => setState((current) => ({ ...current, [key]: value })),
        post: (url: string, options?: unknown) => {
          post(url, data);
          postOptions(options);
        },
        put: vi.fn(),
        reset: () => setState(initial),
        processing: false,
        errors: {},
      };
    },
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string, params?: Record<string, unknown>) => `/${name}/${params?.['token'] ?? ''}` }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <div>{children}</div> }));

beforeAll(() => {
  // breadcrumbHome() uses the global route() of Ziggy
  Object.defineProperty(globalThis, 'route', { value: (name: string) => `/${name}`, writable: true });
});

const available: Permission[] = ['communities:read', 'resources:create', 'resources:read'];
const extension: ApiToken = {
  id: 7,
  name: 'Extension',
  abilities: ['resources:create'],
  last_used_ago: null,
  created_at: '2026-11-09T10:00:00Z',
  updated_at: '2026-11-09T10:00:00Z',
  expires_at: null,
};
const tokens: ApiToken[] = [extension];

function renderPage() {
  render(
    <AppDataProvider>
      <ApiTokensPage tokens={tokens} availablePermissions={available} defaultPermissions={['communities:read']} />
    </AppDataProvider>,
  );
}

describe('ApiTokensPage', () => {
  it('creates a token with the chosen permissions (#689)', async () => {
    renderPage();

    expect(screen.getByRole('checkbox', { name: /Read communities/ })).toBeChecked();
    await userEvent.type(screen.getByLabelText('Name'), 'Extension 2');
    await userEvent.click(screen.getByRole('checkbox', { name: /Read communities/ }));
    await userEvent.click(screen.getByRole('checkbox', { name: /Add resources only/ }));
    await userEvent.click(screen.getByRole('button', { name: 'Create' }));

    expect(post).toHaveBeenCalledWith('/api-tokens.store/', { name: 'Extension 2', permissions: ['resources:create'], expires_in: '90' });
  });

  it('puts the focus back in the name field after a token is created, also after its dialog closes (#1618)', async () => {
    postOptions.mockImplementationOnce((options: { onSuccess: (response: unknown) => void }) =>
      options.onSuccess({ props: { jetstream: { flash: { token: 'secret-token' } } } }),
    );
    renderPage();

    const name = screen.getByLabelText('Name');
    await userEvent.type(name, 'Script');
    const create = screen.getByRole('button', { name: 'Create' });
    act(() => create.focus());
    await userEvent.keyboard('{Enter}');

    const dialog = await screen.findByRole('dialog', { name: 'Your new API token' });
    expect(within(dialog).getByText('secret-token')).toBeInTheDocument();
    expect(name).toHaveValue('');
    // The focus left the Create button before it became disabled: the dialog cannot give the focus back to it
    expect(create).toBeDisabled();

    await userEvent.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(name).toHaveFocus());
  });

  it('offers an expiry, 90 days by default, and sends the choice (#1267)', async () => {
    renderPage();

    const group = screen.getByRole('radiogroup', { name: 'Expires after' });
    expect(within(group).getByRole('radio', { name: '90 days' })).toBeChecked();
    await userEvent.type(screen.getByLabelText('Name'), 'Script');
    await userEvent.click(within(group).getByRole('radio', { name: 'Never' }));
    await userEvent.click(screen.getByRole('button', { name: 'Create' }));

    expect(post).toHaveBeenLastCalledWith('/api-tokens.store/', { name: 'Script', permissions: ['communities:read'], expires_in: 'never' });
  });

  it('shows the deletion of the account as its own choice, off, with one sentence (#1492)', async () => {
    render(
      <AppDataProvider>
        <ApiTokensPage
          tokens={tokens}
          availablePermissions={['communities:read', 'account:delete']}
          defaultPermissions={['communities:read']}
        />
      </AppDataProvider>,
    );

    const choice = screen.getByRole('checkbox', { name: /Delete your account/ });
    expect(choice).not.toBeChecked();
    expect(screen.getByText('Lets this token delete your account for good, with your password. It cannot be undone.')).toBeInTheDocument();
    expect(screen.getByText('account:delete')).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Name'), 'Cleaner');
    await userEvent.click(choice);
    await userEvent.click(screen.getByRole('button', { name: 'Create' }));

    expect(post).toHaveBeenLastCalledWith('/api-tokens.store/', {
      name: 'Cleaner',
      permissions: ['communities:read', 'account:delete'],
      expires_in: '90',
    });
  });

  it('shows when each token expires (#1267)', () => {
    render(
      <AppDataProvider>
        <ApiTokensPage
          tokens={[
            ...tokens,
            { ...extension, id: 8, name: 'Old', expires_at: '2020-01-01T10:00:00Z' },
            { ...extension, id: 9, name: 'Script', expires_at: '2999-01-01T10:00:00Z' },
          ]}
          availablePermissions={available}
          defaultPermissions={[]}
        />
      </AppDataProvider>,
    );

    const list = screen.getByRole('list', { name: 'Your API tokens' });
    expect(within(list).getByText(/never expires/)).toBeInTheDocument();
    expect(within(list).getByText(/expired on/)).toBeInTheDocument();
    expect(within(list).getByText(/expires on/)).toBeInTheDocument();
  });

  it('lists the tokens and deletes one after a confirmation (#689)', async () => {
    renderPage();

    const list = screen.getByRole('list', { name: 'Your API tokens' });
    expect(within(list).getByText('Never used, never expires')).toBeInTheDocument();
    await userEvent.click(within(list).getByRole('button', { name: 'Delete Extension' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete Extension?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(routerDelete).toHaveBeenCalledWith('/api-tokens.destroy/7', expect.anything());
  });
});
