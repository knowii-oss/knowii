import { render } from '@testing-library/react';

import { AuthFormWrapper } from './auth-form-wrapper';

describe('AuthFormWrapper', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<AuthFormWrapper title="" />);
    expect(baseElement).toBeTruthy();
  });
});
