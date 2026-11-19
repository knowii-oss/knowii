import { cn } from './cn';
import { useToolkit } from './ToolkitContext';

export interface LinkTabItem {
  label: string;
  href: string;
  /**
   * The view that is open: its link gets `aria-current="page"` and the marker
   */
  current?: boolean;
}

export interface LinkTabsProps {
  items: readonly LinkTabItem[];
  /**
   * The name of the navigation, for screen readers: what the views show ("Events")
   */
  ariaLabel: string;
}

/**
 * A row of links to the views of one page, where each view has its own address ("Upcoming" and "Past" of the events).
 * A navigation landmark with a list of links: the link of the open view has `aria-current="page"`, a line under it and
 * a heavier text, so the marker is never a color alone. On a narrow screen the links wrap to more lines, and each one
 * is 44 px high.
 *
 * Not the tabs of ARIA: there is no `tablist`, no arrow key and no panel, because each link loads a view. For a choice
 * that changes the page without a new address, use a `SegmentedControl`. For the path to the open page, use a
 * `Breadcrumb`.
 */
export function LinkTabs({ items, ariaLabel }: LinkTabsProps) {
  const Link = useToolkit().linkComponent;

  return (
    <nav aria-label={ariaLabel} className="min-w-0">
      <ul className="flex flex-wrap gap-x-6 gap-y-1">
        {items.map((item) => (
          <li key={item.href} className="flex">
            <Link
              href={item.href}
              preserveState={true}
              {...(item.current ? { 'aria-current': 'page' as const } : {})}
              // The link that is not open has no bottom border at all, not a transparent one: forced colors would draw it
              className={cn(
                'touch-target px-1 pt-1 text-sm transition-colors',
                item.current ? 'border-b-2 border-secondary-text pb-1 font-bold text-primary' : 'pb-1.5 text-muted hover:text-primary',
              )}
            >
              {item.label}
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  );
}
