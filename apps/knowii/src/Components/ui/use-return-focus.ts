import { useEffect, useRef } from 'react';

// The leave transition of the dialogs and drawers takes 200 ms
const RETURN_DELAY_MS = 250;

// The element that had the focus last, outside every dialog: the trigger of the next dialog that opens
let lastFocusOutsideDialogs: HTMLElement | null = null;

if (typeof document !== 'undefined') {
  document.addEventListener(
    'focusin',
    (event) => {
      const target = event.target;
      if (target instanceof HTMLElement && !target.closest('[role="dialog"]')) {
        lastFocusOutsideDialogs = target;
      }
    },
    true,
  );
}

/**
 * Give the focus back to the element that opened a dialog or a drawer once it closes (#1301). Headless UI should do
 * it, but with our transitions the focus stays on the body of the page, and keyboard users lose their place. Does
 * nothing when the focus already went somewhere else.
 */
export function useReturnFocus(open: boolean): void {
  const trigger = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (open) {
      trigger.current = lastFocusOutsideDialogs;
      return;
    }
    const element = trigger.current;
    trigger.current = null;
    if (!element) {
      return;
    }
    const timer = setTimeout(() => {
      const active = document.activeElement;
      if (element.isConnected && (active === null || active === document.body)) {
        element.focus();
      }
    }, RETURN_DELAY_MS);
    return () => clearTimeout(timer);
  }, [open]);
}
