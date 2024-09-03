import { render } from '@testing-library/react';

import { EditableText } from './editable-text';

describe('EditableText', () => {
  it('should render successfully', () => {
    const { baseElement } = render(<EditableText onSubmit={() => {}} defaultValue="" key="null" />);
    expect(baseElement).toBeTruthy();
  });
});
