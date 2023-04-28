import { render } from '@testing-library/react';

import { SignupForm } from './signup-form';

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
      signUp: () => async () => ({
        data: {
          session: null,
          user: null,
        },
        error: null,
      }),
    },
  }),
}));

describe('SignupForm', () => {
  it('should render successfully', () => {
    const { baseElement } = render(
      <SignupForm checkingUsernameAvailability={false} isUsernameAvailable={true} checkUsernameAvailability={() => {}} />,
    );
    expect(baseElement).toBeTruthy();
  });
});
