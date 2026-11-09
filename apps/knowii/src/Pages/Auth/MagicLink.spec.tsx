import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import MagicLinkPage from './MagicLink';
import LoginPage from './Login';

const posts: Array<[string, unknown]> = [];

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    Head: () => null,
    Link: ({ children, href, className }: { children: React.ReactNode; href: string; className?: string }) => (
      <a href={href} className={className}>
        {children}
      </a>
    ),
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
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string, params?: Record<string, string>) => `/${name}/${params?.['token'] ?? ''}` }));
vi.mock('@/Components/FooterGuest', () => ({ default: () => null }));

describe('MagicLinkPage', () => {
  it('signs in only when the member clicks the button (#703)', async () => {
    render(<MagicLinkPage token="abc" valid={true} email="ada@example.com" />);

    expect(screen.getByText('ada@example.com')).toBeInTheDocument();
    expect(posts).toHaveLength(0);
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(posts.at(-1)).toEqual(['/login.link.consume/abc', {}]);
  });

  it('explains an expired link (#703)', () => {
    render(<MagicLinkPage token="abc" valid={false} email={null} />);

    expect(screen.getByText('This sign-in link expired or was already used.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Sign in' })).not.toBeInTheDocument();
  });
});

describe('LoginPage', () => {
  it('asks for a sign-in link with the address typed for the password (#703)', async () => {
    render(<LoginPage canResetPassword={true} status="" />);

    await userEvent.type(screen.getByLabelText('Email'), 'ada@example.com');
    await userEvent.click(screen.getByRole('button', { name: 'Email me a sign-in link instead' }));

    expect(screen.getByLabelText('Email')).toHaveValue('ada@example.com');
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Email me a link' }));

    expect(posts.at(-1)).toEqual(['/login.link.store/', { email: 'ada@example.com' }]);
  });
});
