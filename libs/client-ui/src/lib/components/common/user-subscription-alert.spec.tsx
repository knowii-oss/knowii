import { render } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { UserSubscriptionAlert } from './user-subscription-alert';

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

describe('UserSubscriptionAlert', () => {
  const queryClient = new QueryClient();
  it('should render successfully', () => {
    const { baseElement } = render(
      <QueryClientProvider client={queryClient}>
        <UserSubscriptionAlert />
      </QueryClientProvider>,
    );
    expect(baseElement).toBeTruthy();
  });
});
