import { render } from '@testing-library/react';

import { DividerWithText } from './divider-with-text';

describe('DividerWithText', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<DividerWithText text="Hello world" />);
    expect(baseElement).toBeTruthy();
  });
});
