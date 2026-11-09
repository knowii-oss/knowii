import { beforeAll, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ApiToken, Permission } from '@knowii/common';
import { AppDataProvider } from '@knowii/common';
import ApiTokensPage from './Index';

const routerDelete = vi.fn();
const post = vi.fn();

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    router: { delete: (...args: unknown[]) => routerDelete(...args) },
    useForm: (initial: Record<string, unknown>) => {
      const [data, setState] = React.useState(initial);
      return {
        data,
        setData: (key: string, value: unknown) => setState((current) => ({ ...current, [key]: value })),
        post: (url: string) => post(url, data),
        put: vi.fn(),
        reset: vi.fn(),
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
const tokens: ApiToken[] = [
  { id: 7, name: 'Extension', abilities: ['resources:create'], last_used_ago: null, created_at: '2026-11-09T10:00:00Z', expires_at: null },
];

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

    expect(post).toHaveBeenCalledWith('/api-tokens.store/', { name: 'Extension 2', permissions: ['resources:create'] });
  });

  it('lists the tokens and deletes one after a confirmation (#689)', async () => {
    renderPage();

    const list = screen.getByRole('list', { name: 'Your API tokens' });
    expect(within(list).getByText('Never used')).toBeInTheDocument();
    await userEvent.click(within(list).getByRole('button', { name: 'Delete Extension' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete Extension?' });
    await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    expect(routerDelete).toHaveBeenCalledWith('/api-tokens.destroy/7', expect.anything());
  });
});
