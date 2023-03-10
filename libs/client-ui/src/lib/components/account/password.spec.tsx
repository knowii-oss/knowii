import { render } from '@testing-library/react';

import { Password } from './password';

describe('Password', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<Password />);
    expect(baseElement).toBeTruthy();
  });
});
