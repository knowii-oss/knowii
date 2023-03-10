import { render } from '@testing-library/react';

import { SigninMode, SigninModeSwitch } from './signin-mode-switch';

describe('SigninModeSwitch', () => {
  it('should render successfully', () => {
    const { baseElement } = render(
      <SigninModeSwitch
        activeMode={SigninMode.MagicLink}
        onChange={() => {
          return;
        }}
      />,
    );
    expect(baseElement).toBeTruthy();
  });
});
