import { render } from '@testing-library/react';

import ClientUi from './client-ui';

describe('ClientUi', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<ClientUi />);
    expect(baseElement).toBeTruthy();
  });
});
