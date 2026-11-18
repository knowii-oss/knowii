import type { ReactNode } from 'react';
import { useId } from 'react';
import { cn } from './cn';

export interface NoAccessPanelProps {
  /**
   * Decorative icon, hidden from screen readers
   */
  icon?: ReactNode;
  /**
   * The heading of the page: the panel is the main content of a page, so it is the `h1`
   */
  title: ReactNode;
  /**
   * Why the person may not read the page, in one line
   */
  reason: ReactNode;
  /**
   * The next step: a text, a link, a button
   */
  children?: ReactNode;
  className?: string;
}

/**
 * What a page shows in place of content that the person may not read (#1561): a heading, the reason, and the next step.
 * Pure presentation: the page decides the texts and the next step.
 */
export function NoAccessPanel({ icon, title, reason, children, className }: NoAccessPanelProps) {
  const headingId = useId();

  return (
    <section
      aria-labelledby={headingId}
      className={cn('flex min-w-0 flex-col items-start gap-4 rounded-xl border border-border bg-surface p-6 sm:p-8', className)}
    >
      {icon && (
        <div aria-hidden="true" className="text-4xl text-muted">
          {icon}
        </div>
      )}
      <h1 id={headingId} className="text-2xl font-extrabold tracking-tight break-words sm:text-3xl">
        {title}
      </h1>
      <p className="max-w-prose text-base break-words text-muted">{reason}</p>
      {children && <div className="flex max-w-full min-w-0 flex-col items-start gap-3 break-words">{children}</div>}
    </section>
  );
}
