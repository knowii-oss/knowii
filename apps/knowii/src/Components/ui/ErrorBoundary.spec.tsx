import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { MockInstance } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ErrorBoundary } from './ErrorBoundary';

let shouldThrow = true;

function Fragile() {
  if (shouldThrow) {
    throw new Error('RelativeTime received a string');
  }
  return <p>Content is back</p>;
}

describe('ErrorBoundary', () => {
  let consoleError: MockInstance;

  beforeEach(() => {
    shouldThrow = true;
    // React and the boundary both log the error: keep the test output clean
    consoleError = vi.spyOn(console, 'error').mockImplementation(() => undefined);
  });

  afterEach(() => {
    consoleError.mockRestore();
  });

  it('shows its children when nothing fails', () => {
    shouldThrow = false;
    render(
      <ErrorBoundary>
        <Fragile />
      </ErrorBoundary>,
    );
    expect(screen.getByText('Content is back')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('shows the page fallback, logs the error and calls onError when a child throws', () => {
    const onError = vi.fn();
    render(
      <ErrorBoundary onError={onError}>
        <Fragile />
      </ErrorBoundary>,
    );
    expect(screen.getByRole('alert')).toHaveTextContent('Something went wrong on this page');
    expect(screen.getByRole('button', { name: 'Reload the page' })).toBeInTheDocument();
    expect(consoleError).toHaveBeenCalledWith('[Knowii] A component could not be shown', expect.any(Error), expect.any(String));
    expect(onError).toHaveBeenCalledWith(expect.objectContaining({ message: 'RelativeTime received a string' }), expect.anything());
  });

  it('renders the children again on "Try again", with the keyboard', async () => {
    const user = userEvent.setup();
    render(
      <ErrorBoundary>
        <Fragile />
      </ErrorBoundary>,
    );
    shouldThrow = false;
    await user.tab();
    expect(screen.getByRole('button', { name: 'Try again' })).toHaveFocus();
    await user.keyboard('{Enter}');
    expect(screen.getByText('Content is back')).toBeInTheDocument();
  });

  it('clears the error when the reset key changes (a page visit)', () => {
    const { rerender } = render(
      <ErrorBoundary resetKey="/community/a">
        <Fragile />
      </ErrorBoundary>,
    );
    expect(screen.getByRole('alert')).toBeInTheDocument();

    shouldThrow = false;
    rerender(
      <ErrorBoundary resetKey="/community/b">
        <Fragile />
      </ErrorBoundary>,
    );
    expect(screen.getByText('Content is back')).toBeInTheDocument();
  });

  it('shows a one-line fallback for a block of member content', () => {
    render(
      <>
        <ErrorBoundary variant="block" label="this post">
          <Fragile />
        </ErrorBoundary>
        <p>The next post</p>
      </>,
    );
    expect(screen.getByRole('alert')).toHaveTextContent('We could not show this post.');
    expect(screen.getByText('The next post')).toBeInTheDocument();
  });
});
