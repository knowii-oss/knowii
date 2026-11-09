import type { ReactNode } from 'react';
import { cn } from './cn';

export interface EmptyStateProps {
  /**
   * Decorative icon or emoji, hidden from screen readers
   */
  icon?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  /**
   * Call to action (a button or a link)
   */
  action?: ReactNode;
  className?: string;
}

/**
 * What a list or a page shows when it has nothing yet: what will be here, and what to do first.
 */
export function EmptyState({ icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div className={cn('flex flex-col items-center gap-3 rounded-xl border border-dashed border-border px-6 py-10 text-center', className)}>
      {icon && (
        <div aria-hidden="true" className="text-4xl text-muted">
          {icon}
        </div>
      )}
      <p className="text-lg font-bold text-primary">{title}</p>
      {description && <p className="max-w-prose text-sm text-muted">{description}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}
