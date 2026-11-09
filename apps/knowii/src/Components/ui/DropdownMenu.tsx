import type { ReactNode } from 'react';
import { Menu, MenuButton, MenuItems, MenuSeparator } from '@headlessui/react';
import { cn } from './cn';

export interface DropdownMenuProps {
  /**
   * Content of the trigger button (for example an avatar)
   */
  trigger: ReactNode;
  /**
   * Accessible name of the trigger button
   */
  label: string;
  /**
   * The menu entries: DropdownMenuItem elements
   */
  children: ReactNode;
  /**
   * Header shown above the entries (not focusable)
   */
  header?: ReactNode;
  triggerClassName?: string;
  align?: 'start' | 'end';
}

/**
 * Menu of actions or links, built on the Headless UI Menu (keyboard: Enter/Space/arrows open it,
 * arrows move, Enter activates, Escape closes).
 */
export function DropdownMenu({ trigger, label, children, header, triggerClassName, align = 'end' }: DropdownMenuProps) {
  return (
    <Menu>
      <MenuButton aria-label={label} className={cn('flex cursor-pointer items-center rounded-full', triggerClassName)}>
        {trigger}
      </MenuButton>
      <MenuItems
        anchor={{ to: align === 'end' ? 'bottom end' : 'bottom start', gap: 8 }}
        transition
        className={cn(
          'z-50 min-w-60 rounded-xl border border-border bg-surface-elevated p-1 text-primary shadow-2xl focus:outline-none',
          'origin-top transition duration-100 ease-out data-closed:scale-95 data-closed:opacity-0 motion-reduce:transition-none',
        )}
      >
        {header && <div className="px-3 pt-2 pb-3">{header}</div>}
        {header && <MenuSeparator className="mx-1 mb-1 h-px bg-border" />}
        {children}
      </MenuItems>
    </Menu>
  );
}

/**
 * Classes of a menu entry. Give them to the element rendered inside a Headless UI <MenuItem>
 * (an Inertia <Link> or a <button>).
 */
export const dropdownMenuItemClasses =
  'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-primary data-focus:bg-overlay/10';

export { MenuItem as DropdownMenuItem, MenuSeparator as DropdownMenuSeparator } from '@headlessui/react';
