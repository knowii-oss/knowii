import { QueryClientProvider, QueryClient } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import { SubscriptionTable } from './subscription-table';

describe('SubscriptionTable', () => {
  const queryClient = new QueryClient();

  it('should render successfully', () => {
    const { baseElement } = render(
      <QueryClientProvider client={queryClient}>
        <SubscriptionTable />
      </QueryClientProvider>,
    );
    expect(baseElement).toBeTruthy();
  });
});
