import type { FocusEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Dialog as HeadlessDialog, DialogBackdrop, DialogPanel, DialogTitle, Description, Transition, useClose } from '@headlessui/react';
import { FaXmark } from 'react-icons/fa6';
import type { ButtonProps } from './Button';
import { Button } from './Button';
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
   * Called on Escape, on a click outside the panel, on the close button and on a DialogCloseButton. With
   * hasUnsavedChanges, it is called only after "Discard".
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
  /**
   * The dialog holds changes that are not saved (#1563), for example the isDirty of a react-hook-form form. While it
   * is true, Escape, a click outside the panel, the close button and a DialogCloseButton do not close the dialog: a
   * question asks "Discard your text?" first. Give false while the form is sent and once it is saved. A close that the
   * caller makes itself (onClose after a save, open={false}) never asks.
   */
  hasUnsavedChanges?: boolean | undefined;
}

/**
 * Modal dialog built on the Headless UI Dialog: focus trap, Escape to close, focus goes back to the trigger.
 * Put autoFocus on the field that must get the focus first.
 *
 * A dialog that holds a form with text gives `hasUnsavedChanges` (#1563). Then every close by the person asks first:
 * Escape, a click outside the panel, the close button, and the "Cancel" button of the footer when it is a
 * `<DialogCloseButton>`. "Keep writing" (the focus, and Escape) closes the question and gives the focus back to the
 * form. "Discard" calls `onClose`.
 *
 * ```tsx
 * <Dialog
 *   open={open}
 *   onClose={onClose}
 *   title="New post"
 *   hasUnsavedChanges={form.formState.isDirty && !form.formState.isSubmitting}
 *   footer={<DialogCloseButton>Cancel</DialogCloseButton>}
 * >
 * ```
 */
export function Dialog({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  size = 'md',
  className,
  onAfterClose,
  hasUnsavedChanges = false,
}: DialogProps) {
  useReturnFocus(open);
  // The question "Discard your text?" is open
  const [asking, setAsking] = useState(false);
  // The last element of the panel that had the focus. Escape takes the focus away before the dialog hears it, so the
  // element is kept here, and it gets the focus again after "Keep writing".
  const lastFocus = useRef<HTMLElement | null>(null);
  const focusAfterQuestion = useRef(false);

  // After "Keep writing": the question is closing and the page is no longer inert, so the form can take the focus
  useEffect(() => {
    if (asking || !focusAfterQuestion.current) {
      return;
    }
    focusAfterQuestion.current = false;
    const element = lastFocus.current;
    if (element?.isConnected) {
      element.focus();
    }
  }, [asking]);

  const requestClose = () => {
    if (hasUnsavedChanges) {
      setAsking(true);
      return;
    }
    onClose();
  };

  const keepWriting = () => {
    focusAfterQuestion.current = true;
    setAsking(false);
  };

  const discard = () => {
    setAsking(false);
    onClose();
  };

  const rememberFocus = (event: FocusEvent<HTMLElement>) => {
    if (event.target instanceof HTMLElement && event.currentTarget.contains(event.target)) {
      lastFocus.current = event.target;
    }
  };

  return (
    <Transition
      show={open}
      afterLeave={() => {
        setAsking(false);
        lastFocus.current = null;
        onAfterClose?.();
      }}
    >
      <HeadlessDialog onClose={requestClose} className="relative z-50">
        <DialogBackdrop
          transition
          className="fixed inset-0 bg-black/70 transition-opacity duration-200 ease-out data-closed:opacity-0 motion-reduce:transition-none"
        />
        <div className="fixed inset-0 w-screen overflow-y-auto">
          <div className="flex min-h-full items-end justify-center sm:items-center sm:p-4">
            <DialogPanel
              transition
              onFocus={rememberFocus}
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
                  onClick={requestClose}
                  aria-label="Close"
                  className="-mt-1 -mr-2 flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-overlay/10 hover:text-primary"
                >
                  <FaXmark aria-hidden="true" className="size-5" />
                </button>
              </div>
              {children !== undefined && children !== null && <div className="px-5 py-4">{children}</div>}
              {footer && (
                // Sticky: on a small screen, a long form scrolls under the actions, and the actions stay in view
                <div className="sticky bottom-0 flex flex-col-reverse gap-2 border-t border-border bg-surface px-5 py-4 sm:flex-row sm:justify-end sm:rounded-b-xl">
                  {footer}
                </div>
              )}
            </DialogPanel>
          </div>
        </div>
        {(hasUnsavedChanges || asking) && (
          // A dialog nested in this one: Headless UI makes this dialog inert and gives Escape and the clicks outside
          // to the question while it is open
          <Dialog
            open={open && asking}
            onClose={keepWriting}
            size="sm"
            title="Discard your text?"
            description="You have changes that are not saved. If you close now, they are lost."
            footer={
              <>
                <Button variant="secondary" data-autofocus onClick={keepWriting}>
                  Keep writing
                </Button>
                <Button variant="danger" onClick={discard}>
                  Discard
                </Button>
              </>
            }
          />
        )}
      </HeadlessDialog>
    </Transition>
  );
}

export type DialogCloseButtonProps = Omit<ButtonProps, 'onClick'>;

/**
 * A button that closes the dialog that holds it, the way Escape does: with hasUnsavedChanges, it asks first. Use it
 * for the "Cancel" of the footer. A secondary button by default.
 */
export function DialogCloseButton({ variant = 'secondary', ...props }: DialogCloseButtonProps) {
  const close = useClose();
  return <Button variant={variant} {...props} onClick={() => close()} />;
}
