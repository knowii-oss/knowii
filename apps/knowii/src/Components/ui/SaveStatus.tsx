import { FaCheck, FaTriangleExclamation } from 'react-icons/fa6';
import { cn } from './cn';

/**
 * The state of a setting that saves at once. `error` holds the text to show; the control shows the old value again.
 */
export type SaveState = { status: 'idle' } | { status: 'saving' } | { status: 'saved' } | { status: 'error'; message: string };

export interface SaveStatusProps {
  state: SaveState;
  className?: string | undefined;
}

/**
 * The status of a setting that saves at once, next to its control (#1569): "Saving", then "Saved", or the error when
 * the save fails (the control shows the old value again).
 *
 * A polite live region that stays in the page, also when it is empty, so that a screen reader reads each change. It
 * keeps the height of one line, so the page does not move when the text comes and goes.
 */
export function SaveStatus({ state, className }: SaveStatusProps) {
  return (
    <p
      role="status"
      aria-live="polite"
      aria-atomic="true"
      className={cn(
        'mt-1 flex min-h-5 items-center gap-1.5 text-sm',
        state.status === 'error' ? 'text-danger-text' : 'text-muted',
        className,
      )}
    >
      {state.status === 'saving' && (
        <>
          <span
            aria-hidden="true"
            className="size-3.5 shrink-0 animate-spin rounded-full border-2 border-overlay/20 border-t-secondary motion-reduce:animate-none"
          />
          Saving
        </>
      )}
      {state.status === 'saved' && (
        <>
          <FaCheck aria-hidden="true" className="size-3.5 shrink-0 text-success" />
          Saved
        </>
      )}
      {state.status === 'error' && (
        <>
          <FaTriangleExclamation aria-hidden="true" className="size-3.5 shrink-0" />
          {state.message}
        </>
      )}
    </p>
  );
}
