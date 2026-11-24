import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as Common from '@knowii/common';
import FinishSignUpPage from './FinishSignUp';

const posts: Array<Record<string, unknown>> = [];

vi.mock('@inertiajs/react', async () => {
  const React = await import('react');
  return {
    Head: () => null,
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
    useForm: (initial: Record<string, unknown>) => {
      const [data, setState] = React.useState(initial);
      return {
        data,
        setData: (key: string, value: unknown) => setState((current) => ({ ...current, [key]: value })),
        post: () => posts.push(data),
        reset: vi.fn(),
        processing: false,
        errors: {},
      };
    },
  };
});
vi.mock('ziggy-js', () => ({ useRoute: () => (name: string) => `/${name}` }));
vi.mock('@/Components/Footer', () => ({ default: () => null }));
vi.mock('@knowii/common', async (importOriginal) => ({
  ...(await importOriginal<typeof Common>()),
  useTypedPage: () => ({ props: { jetstream: { hasTermsAndPrivacyPolicyFeature: false } } }),
}));

/**
 * What a password manager does with "Suggest strong password": it sets the value with the native setter of the input,
 * then dispatches an input event. React must see the new value in both fields.
 */
function fillLikeAPasswordManager(input: HTMLElement, value: string) {
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')?.set;
  setter?.call(input, value);
  fireEvent.input(input);
}

describe('password managers (#813)', () => {
  it('a suggested password fills both password fields of the page of the link of a sign-up', async () => {
    render(<FinishSignUpPage token="a-token" valid email="grace@example.com" name="Grace Hopper" />);
    const password = screen.getByLabelText('Password', { exact: true });
    const confirmation = screen.getByLabelText('Confirm password');

    expect(password).toHaveAttribute('autocomplete', 'new-password');
    expect(password).toHaveAttribute('name', 'password');
    expect(confirmation).toHaveAttribute('autocomplete', 'new-password');
    expect(confirmation).toHaveAttribute('name', 'password_confirmation');
    // The address of the account, for the password manager
    expect(document.querySelector('input[name="email"]')).toHaveAttribute('autocomplete', 'username');

    fillLikeAPasswordManager(password, 'Xk8-suggested-Pq2-strong');
    fillLikeAPasswordManager(confirmation, 'Xk8-suggested-Pq2-strong');
    await userEvent.click(screen.getByRole('button', { name: 'Create my account' }));

    expect(posts.at(-1)).toMatchObject({ password: 'Xk8-suggested-Pq2-strong', password_confirmation: 'Xk8-suggested-Pq2-strong' });
  });
});
