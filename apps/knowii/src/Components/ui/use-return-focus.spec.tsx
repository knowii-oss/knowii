import { afterEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { useReturnFocus } from './use-return-focus';

function Harness({ open }: { open: boolean }) {
  useReturnFocus(open);
  return (
    <>
      <button type="button">Open</button>
      <button type="button">Other</button>
      {open && (
        <div role="dialog" aria-label="Panel">
          <button type="button">Inside</button>
        </div>
      )}
    </>
  );
}

afterEach(() => {
  vi.useRealTimers();
});

describe('useReturnFocus (#1301)', () => {
  it('gives the focus back to the trigger when the dialog leaves it on the body', () => {
    vi.useFakeTimers();
    const { rerender } = render(<Harness open={false} />);
    screen.getByRole('button', { name: 'Open' }).focus();

    rerender(<Harness open />);
    screen.getByRole('button', { name: 'Inside' }).focus();

    rerender(<Harness open={false} />);
    (document.activeElement as HTMLElement | null)?.blur();
    act(() => vi.advanceTimersByTime(300));

    expect(screen.getByRole('button', { name: 'Open' })).toHaveFocus();
  });

  it('leaves the focus alone when it already went somewhere else', () => {
    vi.useFakeTimers();
    const { rerender } = render(<Harness open={false} />);
    screen.getByRole('button', { name: 'Open' }).focus();
    rerender(<Harness open />);

    rerender(<Harness open={false} />);
    screen.getByRole('button', { name: 'Other' }).focus();
    act(() => vi.advanceTimersByTime(300));

    expect(screen.getByRole('button', { name: 'Other' })).toHaveFocus();
  });
});
