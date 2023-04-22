import { render } from '@testing-library/react';

import { SigninForm } from './signin-form';

jest.mock('next/router', () => ({
  useRouter() {
    return {
      route: '/',
      pathname: '',
      query: '',
      asPath: '',
      push: jest.fn(),
      events: {
        on: jest.fn(),
        off: jest.fn(),
      },
      beforePopState: jest.fn(() => null),
      prefetch: jest.fn(() => null),
    };
  },
}));

// We mock the supabase client
jest.mock('@supabase/auth-helpers-react', () => ({
  __esModule: true,
  useSupabaseClient: jest.fn().mockReturnValue({
    auth: {
      onAuthStateChange: () => ({
        data: {
          subscription: {
            unsubscribe: () => {
              return;
            },
          },
        },
      }),
    },
  }),
}));

describe('SigninForm', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<SigninForm enabledAuthProviders={['google', 'github', 'twitter']} />);
    expect(baseElement).toBeTruthy();
  });
});
