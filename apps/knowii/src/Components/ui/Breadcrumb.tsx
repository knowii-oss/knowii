import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { FaChevronRight } from 'react-icons/fa';
import { cn } from './cn';

export interface BreadcrumbItem {
  label: string;
  /**
   * Link target. The last item is the current page and is never a link.
   */
  href?: string;
  icon?: ReactNode;
}

interface BreadcrumbProps {
  items: readonly BreadcrumbItem[];
  className?: string;
}

export function Breadcrumb({ items, className }: BreadcrumbProps) {
  return (
    <nav aria-label="Breadcrumb" className={cn('min-w-0', className)}>
      <ol className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-bold sm:text-base">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          const content = (
            <>
              {item.icon && (
                <span aria-hidden="true" className="shrink-0">
                  {item.icon}
                </span>
              )}
              <span className="max-w-48 truncate sm:max-w-xs md:max-w-md">{item.label}</span>
            </>
          );

          return (
            <li key={`${index}-${item.label}`} className="flex min-w-0 items-center gap-2">
              {isLast || !item.href ? (
                <span className="flex min-w-0 items-center gap-2 text-primary" aria-current={isLast ? 'page' : undefined}>
                  {content}
                </span>
              ) : (
                <Link
                  href={item.href}
                  preserveState={true}
                  className="flex min-w-0 items-center gap-2 rounded text-muted transition-colors hover:text-primary"
                >
                  {content}
                </Link>
              )}
              {!isLast && <FaChevronRight aria-hidden="true" className="size-3 shrink-0 text-secondary-text" />}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
