import type { ReactNode } from 'react';
import { Dialog as HeadlessDialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { FaXmark } from 'react-icons/fa6';
import { cn } from './cn';
import { useReturnFocus } from './use-return-focus';

export interface DrawerProps {
  open: boolean;
  onClose: () => void;
  /**
   * Accessible name of the drawer, shown at the top
   */
  title: ReactNode;
  side?: 'left' | 'right';
  // The accessible name of the close button: "Close menu" for the navigation drawers
  closeLabel?: string;
  children?: ReactNode;
  className?: string;
}

/**
 * Panel that slides in from the side of the screen (navigation menus). Modal: same keyboard behavior as the Dialog.
 */
export function Drawer({ open, onClose, title, side = 'left', closeLabel = 'Close menu', children, className }: DrawerProps) {
  useReturnFocus(open);

  return (
    <HeadlessDialog open={open} onClose={onClose} className="relative z-50">
      <DialogBackdrop
        transition
        className="fixed inset-0 bg-black/60 transition-opacity duration-200 ease-out data-closed:opacity-0 motion-reduce:transition-none"
      />
      <div className={cn('fixed inset-y-0 flex w-full sm:max-w-sm', side === 'left' ? 'left-0' : 'right-0')}>
        <DialogPanel
          transition
          className={cn(
            'flex h-full w-full flex-col border-border bg-surface text-primary shadow-2xl',
            side === 'left' ? 'border-r' : 'border-l',
            'transition duration-200 ease-out motion-reduce:transition-none',
            side === 'left' ? 'data-closed:-translate-x-full' : 'data-closed:translate-x-full',
            className,
          )}
        >
          <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
            <DialogTitle className="flex items-center gap-3 text-xl font-extrabold">{title}</DialogTitle>
            <button
              type="button"
              onClick={onClose}
              aria-label={closeLabel}
              className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-overlay/10 hover:text-primary"
            >
              <FaXmark aria-hidden="true" className="size-6" />
            </button>
          </div>
          <div className="flex grow flex-col overflow-y-auto px-5 py-4">{children}</div>
        </DialogPanel>
      </div>
    </HeadlessDialog>
  );
}
