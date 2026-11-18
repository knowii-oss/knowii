import { Component, createRef } from 'react';
import type { ErrorInfo, ReactNode } from 'react';
import { FaRedo, FaSyncAlt } from 'react-icons/fa';
import { Button } from './Button';
import { cn } from './cn';

export interface ErrorBoundaryProps {
  children: ReactNode;
  /**
   * When this value changes, the boundary shows its children again (for example the URL of the page, so a visit clears the error)
   */
  resetKey?: unknown;
  /**
   * page: a message with "Try again" and "Reload the page" (default). block: one line, for a piece of member content
   */
  variant?: 'page' | 'block';
  /**
   * What the block shows, for the message of the block variant: "this post", "this comment"
   */
  label?: string;
  /**
   * Called after the error is logged
   */
  onError?: (error: Error, info: ErrorInfo) => void;
  className?: string;
}

interface ErrorBoundaryState {
  error: Error | null;
  resetKey: unknown;
  // "Try again" was pressed: the next render moves the focus (#1651)
  retried: boolean;
}

/**
 * Gives the focus to an element that is not focusable by itself, for this one time: its tabindex goes when the focus
 * leaves it
 */
function focusOnce(element: HTMLElement): void {
  if (!element.hasAttribute('tabindex')) {
    element.setAttribute('tabindex', '-1');
    element.addEventListener('blur', () => element.removeAttribute('tabindex'), { once: true });
  }
  element.focus();
}

/**
 * Catches a rendering error in its children and shows a fallback instead of unmounting the whole page.
 * The error is logged to the console.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  override state: ErrorBoundaryState = { error: null, resetKey: this.props.resetKey, retried: false };
  // The start of the children, after "Try again": the element after it gets the focus
  private readonly start = createRef<HTMLSpanElement>();
  // The message, which gets the focus when "Try again" fails again
  private readonly message = createRef<HTMLDivElement>();

  static getDerivedStateFromError(error: unknown): Partial<ErrorBoundaryState> {
    return { error: error instanceof Error ? error : new Error(String(error)) };
  }

  static getDerivedStateFromProps(props: ErrorBoundaryProps, state: ErrorBoundaryState): Partial<ErrorBoundaryState> | null {
    return Object.is(props.resetKey, state.resetKey) ? null : { error: null, resetKey: props.resetKey };
  }

  override componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('[Knowii] A component could not be shown', error, info.componentStack);
    this.props.onError?.(error, info);
  }

  // "Try again" goes away with the focus (#1651): the focus goes to the start of the content that is back, or to the
  // message when the content fails again, never to the page
  override componentDidUpdate(): void {
    if (!this.state.retried) {
      return;
    }
    if (this.state.error === null) {
      const first = this.start.current?.nextElementSibling;
      if (first instanceof HTMLElement) {
        focusOnce(first);
      }
    } else if (this.message.current) {
      focusOnce(this.message.current);
    }
    this.setState({ retried: false });
  }

  private readonly reset = (): void => {
    this.setState({ error: null, retried: true });
  };

  override render(): ReactNode {
    if (this.state.error === null) {
      // The marker of the start is there only for the render after "Try again", so it changes nothing in the layout.
      // The shape of the tree stays the same with or without it: the children are not mounted again when it goes
      return (
        <>
          {this.state.retried && <span ref={this.start} hidden />}
          {this.props.children}
        </>
      );
    }

    if (this.props.variant === 'block') {
      return (
        <div
          ref={this.message}
          role="alert"
          tabIndex={-1}
          className={cn(
            'flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-border px-3 py-2 text-sm text-muted',
            'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
            this.props.className,
          )}
        >
          <span>{this.props.label ? `We could not show ${this.props.label}.` : 'We could not show this content.'}</span>
          <Button size="sm" variant="ghost" onClick={this.reset}>
            <FaRedo aria-hidden="true" />
            Try again
          </Button>
        </div>
      );
    }

    return (
      <div
        ref={this.message}
        role="alert"
        tabIndex={-1}
        className={cn(
          'mx-auto flex max-w-prose flex-col items-center gap-3 rounded-xl border border-dashed border-border px-6 py-10 text-center',
          'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
          this.props.className,
        )}
      >
        <p aria-hidden="true" className="text-4xl text-muted">
          ⚠️
        </p>
        <p className="text-lg font-bold text-primary">Something went wrong on this page</p>
        <p className="text-sm text-muted">Try again, or reload the page. If it happens again, tell us what you were doing.</p>
        <div className="mt-2 flex flex-wrap justify-center gap-2">
          <Button variant="secondary" onClick={this.reset}>
            <FaRedo aria-hidden="true" />
            Try again
          </Button>
          <Button onClick={() => window.location.reload()}>
            <FaSyncAlt aria-hidden="true" />
            Reload the page
          </Button>
        </div>
      </div>
    );
  }
}
