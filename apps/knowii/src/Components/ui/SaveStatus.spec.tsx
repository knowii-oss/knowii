import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SaveStatus } from './SaveStatus';

describe('SaveStatus (#1569)', () => {
  it('is a polite live region that stays in the page when it is empty, with the height of one line', () => {
    render(<SaveStatus state={{ status: 'idle' }} />);

    const status = screen.getByRole('status');
    expect(status).toHaveTextContent('');
    expect(status).toHaveAttribute('aria-live', 'polite');
    expect(status).toHaveAttribute('aria-atomic', 'true');
    expect(status).toHaveClass('min-h-5');
  });

  it('says "Saving", then "Saved", in the same region', () => {
    const { rerender } = render(<SaveStatus state={{ status: 'saving' }} />);
    const status = screen.getByRole('status');
    expect(status).toHaveTextContent('Saving');

    rerender(<SaveStatus state={{ status: 'saved' }} />);

    expect(screen.getByRole('status')).toBe(status);
    expect(status).toHaveTextContent('Saved');
  });

  it('says the error in the danger text color', () => {
    render(<SaveStatus state={{ status: 'error', message: 'Failed to save who can join.' }} />);

    const status = screen.getByRole('status');
    expect(status).toHaveTextContent('Failed to save who can join.');
    expect(status).toHaveClass('text-danger-text');
  });
});
