import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TwoFactorChallengePage from './TwoFactorChallenge';

const post = vi.fn();
let lastData: Record<string, string> = {};

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    Head: () => null,
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    useForm: (initial: Record<string, string>) => {
      const [data, setState] = React.useState(initial);
      lastData = data;
      return {
        data,
        setData: (key: string, value: string) => setState((current) => ({ ...current, [key]: value })),
        post: (url: string) => post(url, lastData),
        processing: false,
        errors: {},
      };
    },
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Components/Footer', () => ({ default: () => null }));

describe('TwoFactorChallengePage', () => {
  it('sends the code of the authenticator app (#690)', async () => {
    render(<TwoFactorChallengePage />);

    expect(screen.getByLabelText('Code')).toHaveFocus();
    await userEvent.type(screen.getByLabelText('Code'), '123456');
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(post).toHaveBeenLastCalledWith('/two-factor.login.store', { code: '123456', recovery_code: '' });
  });

  it('switches to a recovery code and moves the focus (#690)', async () => {
    render(<TwoFactorChallengePage />);

    await userEvent.click(screen.getByRole('button', { name: 'Use a recovery code' }));

    expect(screen.queryByLabelText('Code')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Recovery code')).toHaveFocus();
    expect(screen.getByRole('button', { name: 'Use the code of the app' })).toBeInTheDocument();
  });
});
