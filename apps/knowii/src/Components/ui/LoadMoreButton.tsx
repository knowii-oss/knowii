import type { ReactNode } from 'react';
import { Button } from './Button';
import { cn } from './cn';

export type LoadMoreButtonSize = 'sm' | 'md';
export type LoadMoreButtonAlign = 'center' | 'start';

const alignClasses: Record<LoadMoreButtonAlign, string> = {
  center: 'justify-center',
  start: 'justify-start',
};

export interface LoadMoreButtonProps {
  /**
   * Loads the next part of the list
   */
  onClick: () => void;
  /**
   * The next part loads: the button is busy and keeps the focus
   */
  loading?: boolean | undefined;
  /**
   * The words of the button: "Load more", "Load older"
   */
  children: ReactNode;
  size?: LoadMoreButtonSize | undefined;
  /**
   * Where the button sits in its row
   */
  align?: LoadMoreButtonAlign | undefined;
  /**
   * The name of the button when the page has several of them: "Load more in Following"
   */
  'aria-label'?: string | undefined;
  /**
   * Classes of the row (its margins, its place in a flex column)
   */
  className?: string | undefined;
}

/**
 * The button under a list (or above it, for a list that grows upward) that loads its next part: a row with one
 * secondary `Button`.
 *
 * Not for the result: show the block only while the list has more, and say what loaded in a status region of the page
 * ("20 more loaded"). Not a link to another page (`LinkButton`).
 *
 * A `button` with its words as its name. While it loads it has `aria-busy` and `aria-disabled`, keeps the focus, and a
 * second click, Enter or Space does nothing (the `loading` of `Button`).
 */
export function LoadMoreButton({
  onClick,
  loading = false,
  children,
  size = 'md',
  align = 'center',
  'aria-label': ariaLabel,
  className,
}: LoadMoreButtonProps) {
  return (
    <div className={cn('flex', alignClasses[align], className)}>
      <Button variant="secondary" size={size} loading={loading} aria-label={ariaLabel} onClick={() => onClick()}>
        {children}
      </Button>
    </div>
  );
}
