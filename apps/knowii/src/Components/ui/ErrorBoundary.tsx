import { Component } from 'react';
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
}

/**
 * Catches a rendering error in its children and shows a fallback instead of unmounting the whole page.
 * The error is logged to the console.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  override state: ErrorBoundaryState = { error: null, resetKey: this.props.resetKey };

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

  private readonly reset = (): void => {
    this.setState({ error: null });
  };

  override render(): ReactNode {
    if (this.state.error === null) {
      return this.props.children;
    }

    if (this.props.variant === 'block') {
      return (
        <div
          role="alert"
          className={cn(
            'flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-border px-3 py-2 text-sm text-muted',
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
        role="alert"
        className={cn(
          'mx-auto flex max-w-prose flex-col items-center gap-3 rounded-xl border border-dashed border-border px-6 py-10 text-center',
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
