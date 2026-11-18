import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Gives the focus to an element and scrolls it into view only when it is not visible: `nearest` does not move a page
 * that already shows the whole element (#1624).
 */
export function focusInView(element: HTMLElement): void {
  element.focus({ preventScroll: true });
  element.scrollIntoView({ block: 'nearest' });
}

/**
 * Moves the focus after the next render (#1624): a form that goes away after a save, a Cancel or Escape takes the focus
 * with it, and the element that must get it (the saved item, the button that opened the form) is only in the page
 * after the render. The returned function takes a finder that runs once, after the commit of the state changes made in
 * the same turn. It does nothing when the finder finds no element.
 */
export function useFocusAfterRender(): (find: () => HTMLElement | null | undefined) => void {
  const pending = useRef<(() => HTMLElement | null | undefined) | null>(null);
  const [request, setRequest] = useState(0);

  useEffect(() => {
    const find = pending.current;
    if (!find) {
      return;
    }
    pending.current = null;
    const element = find();
    if (element) {
      focusInView(element);
    }
  }, [request]);

  return useCallback((find: () => HTMLElement | null | undefined) => {
    pending.current = find;
    setRequest((count) => count + 1);
  }, []);
}
