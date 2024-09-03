import { render } from '@testing-library/react';

import { ForgotPasswordForm } from './forgot-password-form';

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

describe('ForgotPasswordForm', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<ForgotPasswordForm />);
    expect(baseElement).toBeTruthy();
  });
});
