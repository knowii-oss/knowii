import type { ReactNode } from 'react';
import { Dialog as HeadlessDialog, DialogBackdrop, DialogPanel, DialogTitle, Description, Transition } from '@headlessui/react';
import { FaXmark } from 'react-icons/fa6';
import { cn } from './cn';
import { useReturnFocus } from './use-return-focus';

export type DialogSize = 'sm' | 'md' | 'lg';

const sizeClasses: Record<DialogSize, string> = {
  sm: 'sm:max-w-md',
  md: 'sm:max-w-lg',
  lg: 'sm:max-w-2xl',
};

export interface DialogProps {
  open: boolean;
  /**
   * Called on Escape, on a click outside the panel and on the close button
   */
  onClose: () => void;
  title: ReactNode;
  /**
   * Short text below the title, linked to the dialog with aria-describedby
   */
  description?: ReactNode;
  children?: ReactNode;
  /**
   * Actions (buttons), shown at the bottom
   */
  footer?: ReactNode;
  size?: DialogSize;
  className?: string;
  /**
   * Called once the closing animation is over. Reset the content here, so the user does not see it change while
   * the dialog fades out.
   */
  onAfterClose?: () => void;
}

/**
 * Modal dialog built on the Headless UI Dialog: focus trap, Escape to close, focus goes back to the trigger.
 * Put autoFocus on the field that must get the focus first.
 */
export function Dialog({ open, onClose, title, description, children, footer, size = 'md', className, onAfterClose }: DialogProps) {
  useReturnFocus(open);

  return (
    <Transition show={open} afterLeave={() => onAfterClose?.()}>
      <HeadlessDialog onClose={onClose} className="relative z-50">
        <DialogBackdrop
          transition
          className="fixed inset-0 bg-black/70 transition-opacity duration-200 ease-out data-closed:opacity-0 motion-reduce:transition-none"
        />
        <div className="fixed inset-0 w-screen overflow-y-auto">
          <div className="flex min-h-full items-end justify-center sm:items-center sm:p-4">
            <DialogPanel
              transition
              className={cn(
                'w-full rounded-t-xl border border-border bg-surface text-primary shadow-2xl sm:rounded-xl',
                'transition duration-200 ease-out data-closed:translate-y-4 data-closed:opacity-0 motion-reduce:transition-none',
                sizeClasses[size],
                className,
              )}
            >
              <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                <div className="min-w-0">
                  <DialogTitle className="flex items-center gap-2 text-lg font-extrabold">{title}</DialogTitle>
                  {description && <Description className="mt-1 text-sm text-muted">{description}</Description>}
                </div>
                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Close"
                  className="-mt-1 -mr-2 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-overlay/10 hover:text-primary"
                >
                  <FaXmark aria-hidden="true" className="size-5" />
                </button>
              </div>
              <div className="px-5 py-4">{children}</div>
              {footer && (
                <div className="flex flex-col-reverse gap-2 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">{footer}</div>
              )}
            </DialogPanel>
          </div>
        </div>
      </HeadlessDialog>
    </Transition>
  );
}
