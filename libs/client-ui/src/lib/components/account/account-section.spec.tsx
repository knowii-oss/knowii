import { render } from '@testing-library/react';

import { AccountSection } from './account-section';

describe('AccountSection', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<AccountSection title="" />);
    expect(baseElement).toBeTruthy();
  });
});
