import { cn } from './cn';

/**
 * The number of lines of text in a row
 */
export type SkeletonLines = 1 | 2;

export interface SkeletonProps {
  /**
   * What loads, for screen readers: "Loading your notifications"
   */
  label: string;
  /**
   * The number of rows: about what the list shows when it is there
   */
  rows?: number | undefined;
  /**
   * The lines of text in each row
   */
  lines?: SkeletonLines | undefined;
  /**
   * A round shape before the lines, for a list whose items start with an avatar
   */
  avatar?: boolean | undefined;
}

// A grey shape. The pulse runs only when the person did not ask for less motion. In forced colors the fill is gone: the
// border keeps the shape visible (it changes nothing outside that mode).
const shapeClasses = 'bg-overlay/10 motion-safe:animate-pulse forced-colors:border';

/**
 * The grey lines that hold the place of a list while it loads: the page does not jump when the list comes. Give the
 * number of rows and lines of the list that loads.
 *
 * Not for a short wait without a known shape (`Spinner`), not for a button that waits (the `loading` of `Button`), and
 * not for an empty list (`EmptyState`). Show it only while the first load runs.
 *
 * A polite live region (role="status") with the label as text for screen readers only. The shapes are hidden from
 * screen readers, and nothing takes the focus. It is a `div`: do not put it in a `ul`. The status must not sit inside an
 * element with `aria-busy`: assistive technology may ignore what a busy element holds, and the label with it.
 */
export function Skeleton({ label, rows = 1, lines = 2, avatar = false }: SkeletonProps) {
  return (
    <div role="status" className="flex flex-col gap-2">
      <span className="sr-only">{label}</span>
      {Array.from({ length: rows }, (_, index) => (
        <div key={index} aria-hidden="true" className="flex gap-3 px-2 py-3">
          {avatar && <span className={cn('size-8 shrink-0 rounded-full', shapeClasses)} />}
          <span className="flex grow flex-col gap-2">
            <span className={cn('h-3 w-3/4 rounded', shapeClasses)} />
            {lines === 2 && <span className={cn('h-3 w-full rounded', shapeClasses)} />}
          </span>
        </div>
      ))}
    </div>
  );
}
