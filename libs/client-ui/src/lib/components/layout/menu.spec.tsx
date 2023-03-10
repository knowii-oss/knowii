import { render } from '@testing-library/react';
import { Menu } from './menu';

jest.mock('./layout', () => ({
  useMobileBreakpoint: () => false,
}));

describe('Menu', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<Menu />);
    expect(baseElement).toBeTruthy();
  });
});
