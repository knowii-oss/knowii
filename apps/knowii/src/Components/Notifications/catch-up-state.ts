import { useSyncExternalStore } from 'react';

/**
 * The dates of the "Catch up" card that the member cleared with "I am caught up" (#1476). The server forgets the date in the
 * session, but the shared prop of the page that is open still holds it: this keeps the card hidden everywhere until the next
 * page load. A module value, not a React state, so that every card of the page hides at once.
 */
let cleared: ReadonlySet<string> = new Set();
const listeners = new Set<() => void>();

const subscribe = (listener: () => void) => {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
};

export function clearCatchUp(since: string): void {
  cleared = new Set([...cleared, since]);
  listeners.forEach((listener) => listener());
}

/**
 * For the tests: forget what was cleared
 */
export function resetCatchUpState(): void {
  cleared = new Set();
  listeners.forEach((listener) => listener());
}

/**
 * The date of the card, unless the member cleared it: null hides every card
 */
export function useActiveCatchUp(since: string | null | undefined): string | null {
  const isCleared = useSyncExternalStore(
    subscribe,
    () => (since ? cleared.has(since) : false),
    () => false,
  );
  return since && !isCleared ? since : null;
}
