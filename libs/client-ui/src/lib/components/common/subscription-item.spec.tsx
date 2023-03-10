import { render } from '@testing-library/react';

import { SubscriptionItem } from './subscription-item';

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

describe('SubscriptionItem', () => {
  it('should render successfully', () => {
    const { baseElement } = render(
      <SubscriptionItem
        billingInterval="month"
        hideSubscribeButton={false}
        isHighlighted={false}
        plan={{ id: 'one', features: [], prices: [], name: 'Foo', description: 'Bar' }}
      />,
    );
    expect(baseElement).toBeTruthy();
  });
});
