import { useState } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useFocusAfterRender } from './use-focus-after-render';

function Harness({ target }: { target: string }) {
  const [saved, setSaved] = useState(false);
  const focusAfterRender = useFocusAfterRender();
  return saved ? (
    <article id="saved" tabIndex={-1} aria-label="Saved item">
      Saved
    </article>
  ) : (
    <button
      type="button"
      onClick={() => {
        setSaved(true);
        focusAfterRender(() => document.getElementById(target));
      }}
    >
      Save
    </button>
  );
}

describe('useFocusAfterRender (#1624)', () => {
  beforeEach(() => {
    Element.prototype.scrollIntoView = vi.fn();
  });

  it('gives the focus to the element that the render shows, and scrolls only as far as needed', async () => {
    render(<Harness target="saved" />);

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    const saved = screen.getByRole('article', { name: 'Saved item' });
    expect(saved).toHaveFocus();
    expect(saved.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' });
  });

  it('does nothing when the element is not in the page', async () => {
    render(<Harness target="missing" />);

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(document.body).toHaveFocus();
    expect(Element.prototype.scrollIntoView).not.toHaveBeenCalled();
  });
});
