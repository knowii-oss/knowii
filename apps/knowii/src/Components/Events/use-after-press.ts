import { useCallback, useEffect, useRef } from 'react';

// A tap on a touch screen sends its mouse events and its click a moment after the finger goes up
const TAP_CLICK_MS = 400;

/**
 * Runs a change of the page when no press of a pointer is in progress (#1514).
 *
 * A press on a button takes the focus away from the field that had it before the click is over. When leaving that field
 * changes the page above the button (a sentence or an error appears), the button moves away from under the pointer and
 * the click is lost. The returned function runs the change at once when no pointer is pressed (the keyboard), and after
 * the release and its click when one is.
 */
export function useAfterPress(): (run: () => void) => void {
  const pressed = useRef(false);
  const releasedAt = useRef(0);
  const waiting = useRef<(() => void) | null>(null);

  useEffect(() => {
    const down = () => {
      pressed.current = true;
    };
    const up = () => {
      pressed.current = false;
      releasedAt.current = Date.now();
      const run = waiting.current;
      waiting.current = null;
      if (run) {
        // After the click that follows the release
        window.setTimeout(run, 0);
      }
    };
    window.addEventListener('pointerdown', down, true);
    window.addEventListener('pointerup', up, true);
    window.addEventListener('pointercancel', up, true);
    return () => {
      window.removeEventListener('pointerdown', down, true);
      window.removeEventListener('pointerup', up, true);
      window.removeEventListener('pointercancel', up, true);
    };
  }, []);

  return useCallback((run: () => void) => {
    if (pressed.current) {
      waiting.current = run;
      return;
    }
    if (Date.now() - releasedAt.current < TAP_CLICK_MS) {
      window.setTimeout(run, 0);
      return;
    }
    run();
  }, []);
}
