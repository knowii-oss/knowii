import { render } from '@testing-library/react';
import { MobileDrawerMenu } from './mobile-drawer-menu';

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

jest.mock('./layout', () => ({
  useMobileBreakpoint: () => false,
}));

describe('MobileDrawerMenu', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<MobileDrawerMenu isOpen={true} onClose={() => {}} />);
    expect(baseElement).toBeTruthy();
  });
});
