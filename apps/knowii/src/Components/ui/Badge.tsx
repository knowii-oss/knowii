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

/**
 * The palette of labels that people name (the member tags, #1389): a color tells the labels apart and carries no
 * meaning. Each key is a token `--color-tag-<key>` of each theme, at 3:1 or more on every surface.
 */
export const BADGE_COLORS = ['gray', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'purple'] as const;
export type BadgeColor = (typeof BADGE_COLORS)[number];

// Full class names, so that Tailwind finds them
const colorBorderClasses: Record<BadgeColor, string> = {
  gray: 'border-tag-gray',
  red: 'border-tag-red',
  orange: 'border-tag-orange',
  yellow: 'border-tag-yellow',
  green: 'border-tag-green',
  teal: 'border-tag-teal',
  blue: 'border-tag-blue',
  purple: 'border-tag-purple',
};

const colorMarkClasses: Record<BadgeColor, string> = {
  gray: 'bg-tag-gray',
  red: 'bg-tag-red',
  orange: 'bg-tag-orange',
  yellow: 'bg-tag-yellow',
  green: 'bg-tag-green',
  teal: 'bg-tag-teal',
  blue: 'bg-tag-blue',
  purple: 'bg-tag-purple',
};

export interface BadgeProps {
  variant?: BadgeVariant;
  /**
   * A color of the palette in place of the variant, for a label that people name (a member tag): it colors the border
   * and a small mark before the text. The text keeps the color of all text.
   */
  color?: BadgeColor;
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
 * A label that people name (a member tag) takes a `color` of the palette instead: the border and a small mark before
 * the text. The color never carries a meaning: the text says it all.
 *
 * Not a button and not a link (it is a `span`: for an action use a `Button`), not a count (the number of new items has
 * its own mark), and not the status of a save (`SaveStatus`). Never show a meaning by the variant alone.
 */
export function Badge({ variant = 'neutral', color, icon, children }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex max-w-full items-center gap-1.5 rounded-md border bg-overlay/5 px-2 py-0.5 text-xs font-bold text-primary',
        color === undefined ? borderClasses[variant] : colorBorderClasses[color],
      )}
    >
      {color !== undefined && (
        <span aria-hidden="true" data-badge-mark="" className={cn('size-2 shrink-0 rounded-full', colorMarkClasses[color])} />
      )}
      {icon !== undefined && (
        <span aria-hidden="true" className={cn('shrink-0', color === undefined ? iconClasses[variant] : 'text-muted')}>
          {icon}
        </span>
      )}
      <span className="min-w-0 wrap-anywhere">{children}</span>
    </span>
  );
}
