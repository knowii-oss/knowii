import type { ReactNode } from 'react';
import { FaChevronDown } from 'react-icons/fa';
import { cn } from './cn';

interface CollapsibleProps {
  title: ReactNode;
  children: ReactNode;
  defaultOpen?: boolean;
  className?: string;
}

/**
 * Section that the user can open and close. Built on the native <details> element (keyboard and screen reader support
 * come from the browser).
 */
export function Collapsible({ title, children, defaultOpen = false, className }: CollapsibleProps) {
  return (
    <details className={cn('group rounded-xl border border-border bg-well/60', className)} open={defaultOpen}>
      <summary className="flex cursor-pointer list-none items-center justify-between gap-4 rounded-xl px-4 py-3 font-bold select-none [&::-webkit-details-marker]:hidden">
        {title}
        <FaChevronDown
          aria-hidden="true"
          className="size-3 text-muted transition-transform group-open:rotate-180 motion-reduce:transition-none"
        />
      </summary>
      <div className="px-4 pb-4">{children}</div>
    </details>
  );
}
