import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import PageContentWrapper from './PageContentWrapper';

describe('PageContentWrapper (#1264)', () => {
  it('puts the page in the shared frame', () => {
    render(<PageContentWrapper>Content</PageContentWrapper>);

    expect(screen.getByRole('main')).toHaveClass('page-content-boundaries');
    expect(screen.getByRole('main')).toHaveTextContent('Content');
  });

  it('centers settings forms in a narrower column', () => {
    render(
      <PageContentWrapper width="form">
        <p>Form</p>
      </PageContentWrapper>,
    );

    expect(screen.getByText('Form').parentElement).toHaveClass('mx-auto', 'max-w-6xl');
  });
});
