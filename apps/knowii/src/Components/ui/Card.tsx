import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from './cn';

export interface CardProps extends HTMLAttributes<HTMLElement> {
  as?: 'section' | 'article' | 'div';
  /**
   * Hover effect for cards that are links or buttons: slight lift and shadow (no colored border)
   */
  interactive?: boolean;
}

/**
 * Card surface ("Knowii Brand Style": rounded-xl, surface color, subtle border)
 */
export function Card({ as: Element = 'section', interactive = false, className, ...props }: CardProps) {
  return (
    <Element
      className={cn(
        'rounded-xl border border-border bg-surface text-primary',
        interactive && 'transition duration-200 hover:bg-surface-elevated hover:shadow-xl motion-safe:hover:-translate-y-0.5',
        className,
      )}
      {...props}
    />
  );
}

export interface CardHeaderProps {
  title: ReactNode;
  icon?: ReactNode;
  /**
   * Buttons or links shown at the end of the header
   */
  actions?: ReactNode;
  /**
   * Heading level of the title
   */
  titleAs?: 'h2' | 'h3';
  className?: string;
}

/**
 * Card title with the short magenta underline bar of the Knowii section headings
 */
export function CardHeader({ title, icon, actions, titleAs: Title = 'h2', className }: CardHeaderProps) {
  return (
    <div className={cn('flex flex-wrap items-start justify-between gap-4 px-5 pt-5', className)}>
      <div className="min-w-0">
        <Title className="flex items-center gap-2 text-lg leading-tight font-extrabold">
          {icon && (
            <span aria-hidden="true" className="text-secondary-text">
              {icon}
            </span>
          )}
          <span className="truncate">{title}</span>
        </Title>
        <span aria-hidden="true" className="mt-2 block h-1 w-12 rounded-full bg-secondary" />
      </div>
      {/* The actions move under the title when the card is too narrow, instead of overflowing it */}
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  );
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('px-5 pt-4 pb-5', className)} {...props} />;
}
