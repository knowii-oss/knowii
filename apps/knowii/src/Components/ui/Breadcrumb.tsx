import type { ComponentType, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { FaChevronRight, FaEllipsis } from 'react-icons/fa6';
import { cn } from './cn';
import type { ToolkitLinkProps } from './ToolkitContext';
import { useToolkit } from './ToolkitContext';

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
  /**
   * The link component of this path only. Default: the link of the ToolkitProvider (the Inertia link in the application)
   */
  linkComponent?: ComponentType<ToolkitLinkProps>;
}

/**
 * The path to the open page. On a phone (#909), a long path shows its first item, a "show the full path" button, the
 * parent and the open page; the button shows the hidden items. Links are 44 px high on small screens (`touch-target`).
 */
export function Breadcrumb({ items, className, linkComponent }: BreadcrumbProps) {
  const toolkit = useToolkit();
  const Link = linkComponent ?? toolkit.linkComponent;
  const [expanded, setExpanded] = useState(false);
  const listRef = useRef<HTMLOListElement>(null);
  // The button goes away with the focus (#1651): the focus moves to the link of the first item that it showed, else to
  // the list itself, never to the page
  useEffect(() => {
    if (expanded) {
      const list = listRef.current;
      const link = list?.querySelectorAll<HTMLElement>(':scope > li')[1]?.querySelector<HTMLElement>('a');
      (link ?? list)?.focus();
    }
  }, [expanded]);
  // The items between the first one and the parent of the open page
  const collapsible = items.length > 3 && !expanded;
  const isHiddenOnPhone = (index: number) => collapsible && index > 0 && index < items.length - 2;

  return (
    <nav aria-label="Breadcrumb" className={cn('min-w-0', className)}>
      <ol
        ref={listRef}
        // The focus target after "Show the full path" when the first item that it shows has no link
        tabIndex={-1}
        className="flex flex-wrap items-center gap-x-2 gap-y-1 rounded text-sm font-bold outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary sm:text-base"
      >
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          const content = (
            <>
              {item.icon && (
                <span aria-hidden="true" className="shrink-0">
                  {item.icon}
                </span>
              )}
              {/* The open page can use the full line on a phone; the other items stay short */}
              <span className={cn('truncate sm:max-w-xs md:max-w-md', !isLast && 'max-w-48')}>{item.label}</span>
            </>
          );

          return (
            <li
              key={`${index}-${item.label}`}
              className={cn('flex min-w-0 items-center gap-2', isHiddenOnPhone(index) && 'hidden sm:flex')}
              data-collapsed={isHiddenOnPhone(index) ? '' : undefined}
            >
              {isLast || !item.href ? (
                <span className="flex min-w-0 items-center gap-2 text-primary" aria-current={isLast ? 'page' : undefined}>
                  {content}
                </span>
              ) : (
                <Link
                  href={item.href}
                  preserveState={true}
                  className="touch-target min-w-0 gap-2 rounded text-muted transition-colors hover:text-primary"
                >
                  {content}
                </Link>
              )}
              {!isLast && <FaChevronRight aria-hidden="true" className="size-3 shrink-0 text-secondary-text" />}
              {index === 0 && collapsible && (
                <span className="flex items-center gap-2 sm:hidden">
                  <button
                    type="button"
                    aria-label="Show the full path"
                    onClick={() => setExpanded(true)}
                    className="flex size-11 cursor-pointer items-center justify-center rounded text-muted transition-colors hover:text-primary"
                  >
                    <FaEllipsis aria-hidden="true" />
                  </button>
                  <FaChevronRight aria-hidden="true" className="size-3 shrink-0 text-secondary-text" />
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
