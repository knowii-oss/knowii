import type { ReactNode } from 'react';
import { cn } from './cn';

/**
 * What the label means. The text says it: the variant only supports the text with the color of the border and of the
 * icon.
 */
export type BadgeVariant = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

const borderClasses: Record<BadgeVariant, string> = {
  neutral: 'border-border-strong',
  info: 'border-secondary-text',
  success: 'border-success',
  warning: 'border-warning',
  danger: 'border-danger-text',
};

const iconClasses: Record<BadgeVariant, string> = {
  neutral: 'text-muted',
  info: 'text-secondary-text',
  success: 'text-success',
  warning: 'text-warning',
  danger: 'text-danger-text',
};

export interface BadgeProps {
  variant?: BadgeVariant;
  /**
   * Decorative icon before the text, hidden from screen readers
   */
  icon?: ReactNode;
  /**
   * The text of the label, a few words: it carries the meaning alone
   */
  children: ReactNode;
}

/**
 * A short text label on an item: its state ("Draft", "Cancelled", "Happening now") or its kind. The text is in the
 * color of all text on every variant, so it reads on every surface of both themes; the variant colors the border and
 * the icon.
 *
 * Not a button and not a link (it is a `span`: for an action use a `Button`), not a count (the number of new items has
 * its own mark), and not the status of a save (`SaveStatus`). Never show a meaning by the variant alone.
 */
export function Badge({ variant = 'neutral', icon, children }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex max-w-full items-center gap-1.5 rounded-md border bg-overlay/5 px-2 py-0.5 text-xs font-bold text-primary',
        borderClasses[variant],
      )}
    >
      {icon !== undefined && (
        <span aria-hidden="true" className={cn('shrink-0', iconClasses[variant])}>
          {icon}
        </span>
      )}
      <span className="min-w-0 wrap-anywhere">{children}</span>
    </span>
  );
}
